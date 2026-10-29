import { Controller } from '@hotwired/stimulus';
import {
    columnFilteringFeature,
    columnVisibilityFeature,
    constructTable,
    createFilteredRowModel,
    createPaginatedRowModel,
    createSortedRowModel,
    filterFns,
    globalFilteringFeature,
    rowPaginationFeature,
    rowSelectionFeature,
    rowSortingFeature,
    sortFns,
    tableFeatures,
} from '@tanstack/table-core';
import { FlexRender } from '@tanstack/table-core/flex-render';
import { storeReactivityBindings } from '@tanstack/table-core/store-reactivity-bindings';

// Structural classes use CSS vars (--tstdt-*) set from TanstackDataTable::colors().
const HEAD_CLASS = 'h-8 px-2 text-start align-middle font-medium whitespace-nowrap text-[color:var(--tstdt-header-text)] border-r border-[color:var(--tstdt-border)] last:border-r-0 bg-[var(--tstdt-header-bg)]';
const CELL_CLASS = 'p-1 align-middle whitespace-nowrap border-r border-[color:var(--tstdt-border)] last:border-r-0';
const SELECT_CELL_CLASS = `${CELL_CLASS} w-[35px] min-w-[35px] max-w-[35px] text-center align-middle`;
const HEADER_ROW_CLASS = 'border-b border-[color:var(--tstdt-border)] bg-[var(--tstdt-header-bg)]';
const ROW_CLASS = 'border-b border-[color:var(--tstdt-border)] transition-colors hover:bg-[var(--tstdt-row-hover)] data-[state=selected]:bg-[var(--tstdt-row-selected)] odd:bg-[var(--tstdt-row-odd)] even:bg-[var(--tstdt-row-even)]';
const CHECKBOX_CLASS = 'size-4 cursor-pointer accent-primary';
const DEFAULT_FILTER_OPERATOR = 'startsWith';
const DEFAULT_COLORS = {
    border: 'border',
    headerBg: 'muted',
    headerText: 'foreground',
    rowOdd: 'background',
    rowEven: 'muted',
    rowHover: 'accent',
    rowSelected: 'primary/15',
};
const SEMANTIC_COLORS = new Set([
    'background',
    'foreground',
    'card',
    'popover',
    'primary',
    'secondary',
    'muted',
    'accent',
    'destructive',
    'border',
    'input',
    'ring',
]);

function toComparable(value) {
    if (value == null || value === '') {
        return null;
    }
    if (typeof value === 'number') {
        return value;
    }
    const text = String(value).trim();
    if (text === '') {
        return null;
    }
    if (/^\d{4}-\d{2}-\d{2}/.test(text)) {
        return text.slice(0, 10);
    }
    if (!Number.isNaN(Number(text)) && text !== '') {
        return Number(text);
    }
    return text.toLowerCase();
}

function operatorFilter(row, columnId, filterValue) {
    const raw = filterValue && typeof filterValue === 'object' ? filterValue : { query: filterValue };
    const operator = raw.operator ?? DEFAULT_FILTER_OPERATOR;
    const query = String(raw.query ?? '').trim();
    const queryTo = String(raw.queryTo ?? '').trim();
    if (!query && !queryTo) {
        return true;
    }

    const cell = row.getValue(columnId);

    if (operator === 'in') {
        const parts = query
            .split(',')
            .map((part) => part.trim().toLowerCase())
            .filter(Boolean);
        return parts.includes(String(cell ?? '').toLowerCase());
    }

    if (['startsWith', 'endsWith', 'equals', 'contains'].includes(operator)) {
        const value = String(cell ?? '').toLowerCase();
        const needle = query.toLowerCase();
        switch (operator) {
            case 'startsWith':
                return value.startsWith(needle);
            case 'endsWith':
                return value.endsWith(needle);
            case 'equals':
                return value === needle;
            case 'contains':
            default:
                return value.includes(needle);
        }
    }

    const left = toComparable(cell);
    const right = toComparable(query);
    const rightTo = queryTo ? toComparable(queryTo) : null;
    if (left == null) {
        return false;
    }

    switch (operator) {
        case 'equals':
            return right != null && left == right;
        case 'gt':
        case 'after':
            return right != null && left > right;
        case 'gte':
            return right != null && left >= right;
        case 'lt':
        case 'before':
            return right != null && left < right;
        case 'lte':
            return right != null && left <= right;
        case 'between':
            return (right == null || left >= right) && (rightTo == null || left <= rightTo);
        default:
            return true;
    }
}

export default class extends Controller {
    static targets = [
        'table',
        'filter',
        'pageInput',
        'pageCount',
        'totalCount',
        'first',
        'previous',
        'next',
        'last',
        'searchPanel',
        'searchToggle',
        'searchSubmit',
        'searchReset',
        'advancedFilterPanel',
        'advancedFilterToggle',
        'advancedFilterBadge',
        'advancedFilterApply',
        'advancedFilterReset',
        'columnToggle',
        'loading',
        'refreshButton',
        'bulkAction',
        'bulkActionTrigger',
        'bulkActionRun',
        'bulkSelectedCount',
    ];

    static values = {
        searchOpen: { type: Boolean, default: false },
        advancedFiltersOpen: { type: Boolean, default: false },
        loading: { type: Boolean, default: false },
    };

    connect() {
        this.config = this.parseConfig();
        this._i18n = this.parseI18n();
        this.applyColors();
        this.rows = Array.isArray(this.config.data) ? this.config.data : [];
        this.columnDefs = this.buildColumns(this.config.columns ?? []);
        this.filterQuery = '';
        this.filterColumn = this.defaultFilterColumnId();
        this.filterOperator = DEFAULT_FILTER_OPERATOR;
        this.advancedFilters = [];
        this.syncSearchSubmitEnabled();
        this.syncAdvancedFilterBadge();
        this.selectedBulkActionId = '';
        this.selectedRowsById = new Map();
        this.abortController = null;
        this.totalRows = this.rows.length;
        this.serverPagination = this.hasAjaxUrl;

        const features = {
            coreReactivityFeature: storeReactivityBindings(),
            rowSortingFeature,
            sortFns,
            globalFilteringFeature,
            columnFilteringFeature,
            filteredRowModel: createFilteredRowModel(),
            filterFns: {
                ...filterFns,
                operatorFilter,
            },
            rowPaginationFeature,
            // Client-side sort/page only when not loading via AJAX.
            ...(this.serverPagination
                ? {}
                : {
                    sortedRowModel: createSortedRowModel(),
                    paginatedRowModel: createPaginatedRowModel(),
                }),
        };

        if (this.columnVisibilityEnabled) {
            features.columnVisibilityFeature = columnVisibilityFeature;
        }

        if (this.bulkSelectionEnabled) {
            features.rowSelectionFeature = rowSelectionFeature;
        }

        this.table = constructTable({
            features: tableFeatures(features),
            data: this.rows,
            columns: this.columnDefs,
            manualPagination: this.serverPagination,
            manualSorting: this.serverPagination,
            rowCount: this.totalRows,
            enableRowSelection: this.bulkSelectionEnabled,
            initialState: {
                pagination: {
                    pageIndex: 0,
                    pageSize: this.pageSize,
                },
                columnVisibility: this.initialColumnVisibility(this.config.columns ?? []),
                rowSelection: {},
                sorting: [],
            },
            getRowId: (row, index) => String(row.id ?? index),
        });

        this.subscription = this.table.store.subscribe(() => this.render());
        /** @type {Map<string, number>} tab url → workspace slot index (1-based) */
        this.openTabSlotsByUrl = new Map();
        this._boundWorkspaceOpened = this._onWorkspaceTabOpened.bind(this);
        this._boundWorkspaceClosed = this._onWorkspaceTabClosed.bind(this);
        this._boundContextMenu = this.onRowActionContextMenu.bind(this);
        this._boundDismissContextMenu = this.dismissRowActionContextMenu.bind(this);
        document.addEventListener('workspace-tabs:opened', this._boundWorkspaceOpened);
        document.addEventListener('workspace-tabs:closed', this._boundWorkspaceClosed);
        this.element.addEventListener('contextmenu', this._boundContextMenu);
        document.addEventListener('click', this._boundDismissContextMenu);
        document.addEventListener('keydown', this._boundDismissContextMenu);

        this.render();
        this.syncLoadingUi();

        if (this.serverPagination) {
            this.fetchRows();
        }
    }

    disconnect() {
        this.abortController?.abort();
        this.abortController = null;
        this.subscription?.unsubscribe();
        this.subscription = null;
        this.table = null;
        document.removeEventListener('workspace-tabs:opened', this._boundWorkspaceOpened);
        document.removeEventListener('workspace-tabs:closed', this._boundWorkspaceClosed);
        this.element.removeEventListener('contextmenu', this._boundContextMenu);
        document.removeEventListener('click', this._boundDismissContextMenu);
        document.removeEventListener('keydown', this._boundDismissContextMenu);
        this.dismissRowActionContextMenu();
        this.dismissDeleteConfirm();
        this.dismissAddDrawer();
        this.closeAdvancedFilterChoicePopups();
        this._unbindAdvancedFilterChoiceOutsideClick();
    }

    get hasWorkspaceTabs() {
        if (this.config?.opensTab === true) {
            return true;
        }
        // WorkspaceTabButton serializes workspaceTab: true on a column button.
        const columns = this.config?.columns ?? [];
        return columns.some((column) =>
            (column?.meta?.buttons ?? []).some((button) => button?.workspaceTab === true)
        );
    }

    /**
     * Reads `data-config` (JSON from TanstackDataTable::toArray()).
     */
    parseConfig() {
        const raw = this.element.getAttribute('data-config') ?? '{}';
        try {
            const config = JSON.parse(raw);
            return config && typeof config === 'object' ? config : {};
        } catch (error) {
            console.error('[tanstack-table] Invalid data-config JSON', error);
            return {};
        }
    }

    /**
     * Reads `data-table-i18n` (English message id → translated string from Twig).
     */
    parseI18n() {
        const raw = this.element.getAttribute('data-table-i18n') ?? '{}';
        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            console.error('[tanstack-table] Invalid data-table-i18n JSON', error);
            return {};
        }
    }

    /** Translate a UI string; {@code key} is the English message id. */
    t(key, replacements = {}) {
        let message = String(this._i18n?.[key] ?? key);
        for (const [token, value] of Object.entries(replacements ?? {})) {
            message = message.replaceAll(String(token), String(value));
        }
        return message;
    }

    get pageSize() {
        const value = Number(this.config?.pageSize);
        return Number.isFinite(value) && value > 0 ? value : 25;
    }

    get emptyMessage() {
        return this.t(this.config?.emptyMessage || 'No rows found.');
    }

    get columnVisibilityEnabled() {
        return this.config?.columnVisibility !== false;
    }

    get bulkActions() {
        return Array.isArray(this.config?.bulkActions) ? this.config.bulkActions : [];
    }

    get bulkSelectionEnabled() {
        return this.bulkActions.length > 0;
    }

    get ajaxUrl() {
        return String(this.config?.url ?? '').trim();
    }

    get hasAjaxUrl() {
        return Boolean(this.ajaxUrl);
    }

    get colors() {
        const fromConfig =
            this.config?.colors && typeof this.config.colors === 'object' ? this.config.colors : {};

        return { ...DEFAULT_COLORS, ...fromConfig };
    }

    /**
     * Resolves TanstackDataTable color tokens to CSS values and sets --tstdt-* vars.
     */
    applyColors() {
        const colors = this.colors;
        const map = {
            '--tstdt-border': colors.border,
            '--tstdt-header-bg': colors.headerBg,
            '--tstdt-header-text': colors.headerText,
            '--tstdt-row-odd': colors.rowOdd,
            '--tstdt-row-even': colors.rowEven,
            '--tstdt-row-hover': colors.rowHover,
            '--tstdt-row-selected': colors.rowSelected,
        };

        for (const [property, token] of Object.entries(map)) {
            this.element.style.setProperty(property, this.resolveColor(token));
        }
    }

    /**
     * @param {string} token Tailwind/theme token (slate-400, muted/50) or raw CSS color
     */
    resolveColor(token) {
        const value = String(token ?? '').trim();
        if (!value) {
            return '';
        }

        if (
            value.startsWith('#') ||
            value.startsWith('rgb') ||
            value.startsWith('hsl') ||
            value.startsWith('oklch') ||
            value.startsWith('var(') ||
            value.startsWith('color-mix(')
        ) {
            return value;
        }

        const [name, alphaRaw] = value.split('/');
        const alpha = alphaRaw && !Number.isNaN(Number(alphaRaw)) ? Number(alphaRaw) : null;

        let base;
        if (name === 'white' || name === 'black' || name === 'transparent' || name === 'currentColor') {
            base = name;
        } else if (SEMANTIC_COLORS.has(name)) {
            base = `var(--${name})`;
        } else {
            base = `var(--color-${name})`;
        }

        if (alpha === null) {
            return base;
        }

        return `color-mix(in oklch, ${base} ${alpha}%, transparent)`;
    }

    toggleSearch(event) {
        event?.preventDefault?.();
        this.searchOpenValue = !this.searchOpenValue;
        if (this.searchOpenValue) {
            this.advancedFiltersOpenValue = false;
        }
    }

    closeSearch(event) {
        event?.preventDefault?.();
        if (!this.searchOpenValue) {
            return;
        }
        this.searchOpenValue = false;
    }

    searchOpenValueChanged() {
        this.syncSearchPanel();
    }

    syncSearchPanel() {
        const open = this.searchOpenValue;
        const state = open ? 'open' : 'closed';

        if (this.hasSearchPanelTarget) {
            const panel = this.searchPanelTarget;
            panel.dataset.state = state;
            panel.setAttribute('aria-hidden', String(!open));
            if (open) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        }

        if (this.hasSearchToggleTarget) {
            const toggle = this.searchToggleTarget;
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute(
                'aria-label',
                open ? this.t('Hide search') : this.t('Show search')
            );
            toggle.title = open ? this.t('Hide search') : this.t('Search');
            toggle.dataset.state = state;
        }

        if (open && this.hasFilterTarget) {
            requestAnimationFrame(() => this.filterTarget.focus());
        }
    }

    toggleAdvancedFilters(event) {
        event?.preventDefault?.();
        this.advancedFiltersOpenValue = !this.advancedFiltersOpenValue;
        if (this.advancedFiltersOpenValue) {
            this.searchOpenValue = false;
        }
    }

    closeAdvancedFilters(event) {
        event?.preventDefault?.();
        if (!this.advancedFiltersOpenValue) {
            return;
        }
        this.advancedFiltersOpenValue = false;
    }

    advancedFiltersOpenValueChanged() {
        this.syncAdvancedFilterPanel();
    }

    syncAdvancedFilterPanel() {
        const open = this.advancedFiltersOpenValue;
        const state = open ? 'open' : 'closed';

        if (this.hasAdvancedFilterPanelTarget) {
            const panel = this.advancedFilterPanelTarget;
            panel.dataset.state = state;
            panel.setAttribute('aria-hidden', String(!open));
            if (open) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
                this.closeAdvancedFilterChoicePopups();
                this._unbindAdvancedFilterChoiceOutsideClick();
            }
        }

        if (this.hasAdvancedFilterToggleTarget) {
            const toggle = this.advancedFilterToggleTarget;
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute(
                'aria-label',
                open ? this.t('Hide advanced filters') : this.t('Show advanced filters')
            );
            toggle.title = open ? this.t('Hide advanced filters') : this.t('Advanced filters');
            toggle.dataset.state = state;
        }

        if (open) {
            requestAnimationFrame(() => {
                const firstValue = this.advancedFilterPanelTarget?.querySelector?.(
                    '[data-advanced-filter="value"]'
                );
                firstValue?.focus?.();
            });
        }
    }

    onAdvancedFilterOperatorChange(event) {
        const row = event?.currentTarget?.closest?.('[data-advanced-filter-row]');
        if (!row) {
            return;
        }
        this.syncAdvancedFilterValueTo(row);
    }

    syncAdvancedFilterValueTo(row) {
        const operator = String(row.querySelector('[data-advanced-filter="operator"]')?.value ?? '').trim();
        const type = String(row.dataset.filterType ?? 'string');
        const valueTo = row.querySelector('[data-advanced-filter="valueTo"]');
        if (!valueTo || type === 'dateRange') {
            return;
        }
        const show = operator === 'between';
        valueTo.classList.toggle('hidden', !show);
        if (show) {
            valueTo.removeAttribute('hidden');
        } else {
            valueTo.setAttribute('hidden', '');
            valueTo.value = '';
        }
    }

    toggleAdvancedFilterChoicePopup(event) {
        event?.preventDefault?.();
        event?.stopPropagation?.();

        const host = event?.currentTarget?.closest?.('[data-advanced-filter="values"]');
        const panel = host?.querySelector?.('[data-advanced-filter="values-panel"]');
        const trigger = host?.querySelector?.('[data-advanced-filter="values-trigger"]');
        if (!panel || !trigger) {
            return;
        }

        const willOpen = panel.hasAttribute('hidden');
        this.closeAdvancedFilterChoicePopups({ except: host });

        if (willOpen) {
            panel.removeAttribute('hidden');
            trigger.setAttribute('aria-expanded', 'true');
            this._bindAdvancedFilterChoiceOutsideClick();
        } else {
            panel.setAttribute('hidden', '');
            trigger.setAttribute('aria-expanded', 'false');
            this._unbindAdvancedFilterChoiceOutsideClick();
        }
    }

    toggleAdvancedFilterChoice(event) {
        event?.preventDefault?.();
        event?.stopPropagation?.();

        const button = event?.currentTarget;
        if (!button) {
            return;
        }

        const selected = button.getAttribute('aria-selected') !== 'true';
        button.setAttribute('aria-selected', String(selected));
        const indicator = button.querySelector('[data-choice-indicator]');
        if (indicator) {
            if (selected) {
                indicator.removeAttribute('hidden');
            } else {
                indicator.setAttribute('hidden', '');
            }
        }

        const row = button.closest('[data-advanced-filter-row]');
        if (row) {
            this.updateAdvancedFilterChoiceLabel(row);
        }
    }

    closeAdvancedFilterChoicePopups({ except = null } = {}) {
        const panelRoot = this.hasAdvancedFilterPanelTarget ? this.advancedFilterPanelTarget : this.element;
        for (const host of panelRoot.querySelectorAll('[data-advanced-filter="values"]')) {
            if (except && host === except) {
                continue;
            }
            const panel = host.querySelector('[data-advanced-filter="values-panel"]');
            const trigger = host.querySelector('[data-advanced-filter="values-trigger"]');
            panel?.setAttribute('hidden', '');
            trigger?.setAttribute('aria-expanded', 'false');
        }
    }

    _bindAdvancedFilterChoiceOutsideClick() {
        if (this._onAdvancedFilterChoiceOutsideClick) {
            return;
        }
        this._onAdvancedFilterChoiceOutsideClick = (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            if (target.closest('[data-advanced-filter="values"]')) {
                return;
            }
            this.closeAdvancedFilterChoicePopups();
            this._unbindAdvancedFilterChoiceOutsideClick();
        };
        document.addEventListener('click', this._onAdvancedFilterChoiceOutsideClick, true);
    }

    _unbindAdvancedFilterChoiceOutsideClick() {
        if (!this._onAdvancedFilterChoiceOutsideClick) {
            return;
        }
        document.removeEventListener('click', this._onAdvancedFilterChoiceOutsideClick, true);
        this._onAdvancedFilterChoiceOutsideClick = null;
    }

    updateAdvancedFilterChoiceLabel(row) {
        const label = row.querySelector('[data-advanced-filter="values-label"]');
        if (!label) {
            return;
        }

        const selected = this.readAdvancedFilterChoices(row);
        if (selected.length === 0) {
            label.textContent = this.t('Any');
            label.removeAttribute('title');
            return;
        }

        const names = selected
            .map((item) => item.label || item.value)
            .filter(Boolean);
        label.textContent =
            names.length <= 2
                ? names.join(', ')
                : this.t('%count% selected', { '%count%': names.length });
        label.title = names.join(', ');
    }

    readAdvancedFilterChoices(row) {
        return Array.from(row.querySelectorAll('[data-advanced-filter-choice][aria-selected="true"]')).map(
            (button) => ({
                value: String(button.dataset.advancedFilterChoice ?? '').trim(),
                label: String(button.dataset.choiceLabel ?? '').trim(),
            })
        ).filter((item) => item.value);
    }

    readAdvancedFilterRows() {
        const panel = this.hasAdvancedFilterPanelTarget ? this.advancedFilterPanelTarget : null;
        if (!panel) {
            return [];
        }

        const filters = [];
        for (const row of panel.querySelectorAll('[data-advanced-filter-row]')) {
            const field = String(row.dataset.fieldId ?? '').trim();
            if (!field) {
                continue;
            }

            const operatorInput = row.querySelector('[data-advanced-filter="operator"]');
            const operator = String(operatorInput?.value ?? 'contains').trim() || 'contains';
            const valuesHost = row.querySelector('[data-advanced-filter="values"]');
            let value = '';
            let valueTo = String(row.querySelector('[data-advanced-filter="valueTo"]')?.value ?? '').trim();

            if (valuesHost) {
                value = this.readAdvancedFilterChoices(row)
                    .map((item) => item.value)
                    .join(',');
            } else {
                value = String(row.querySelector('[data-advanced-filter="value"]')?.value ?? '').trim();
            }

            if (!value && !valueTo) {
                continue;
            }

            filters.push({ field, operator, value, valueTo });
        }

        return filters;
    }

    applyAdvancedFilters(event) {
        event?.preventDefault?.();
        if (!this.table) {
            return;
        }

        this.advancedFilters = this.readAdvancedFilterRows();
        this.filterQuery = '';
        if (this.hasFilterTarget) {
            this.filterTarget.value = '';
        }
        this.syncSearchSubmitEnabled();
        this.syncAdvancedFilterBadge();

        if (this.serverPagination) {
            this.table.setGlobalFilter('');
            this.table.setColumnFilters([]);
            this.fetchRows({ resetPage: true });
            return;
        }

        this.table.setGlobalFilter('');
        this.table.setColumnFilters(
            this.advancedFilters.map((filter) => ({
                id: filter.field,
                value: {
                    query: filter.value,
                    queryTo: filter.valueTo ?? '',
                    operator: filter.operator || DEFAULT_FILTER_OPERATOR,
                },
            }))
        );
    }

    resetAdvancedFilters(event) {
        event?.preventDefault?.();

        if (this.hasAdvancedFilterPanelTarget) {
            for (const row of this.advancedFilterPanelTarget.querySelectorAll('[data-advanced-filter-row]')) {
                const valueInput = row.querySelector('[data-advanced-filter="value"]');
                const valueToInput = row.querySelector('[data-advanced-filter="valueTo"]');
                const operatorInput = row.querySelector('[data-advanced-filter="operator"]');
                if (valueInput) {
                    valueInput.value = '';
                }
                if (valueToInput) {
                    valueToInput.value = '';
                }
                for (const choice of row.querySelectorAll('[data-advanced-filter-choice]')) {
                    choice.setAttribute('aria-selected', 'false');
                    const indicator = choice.querySelector('[data-choice-indicator]');
                    if (indicator) {
                        indicator.setAttribute('hidden', '');
                    }
                }
                this.updateAdvancedFilterChoiceLabel(row);
                this.closeAdvancedFilterChoicePopups();
                this._unbindAdvancedFilterChoiceOutsideClick();
                if (operatorInput && operatorInput.tagName === 'SELECT') {
                    operatorInput.selectedIndex = 0;
                }
                this.syncAdvancedFilterValueTo(row);
            }
        }

        this.advancedFilters = [];
        this.syncAdvancedFilterBadge();

        if (!this.table) {
            return;
        }

        this.table.setGlobalFilter('');
        this.table.setColumnFilters([]);

        if (this.serverPagination) {
            this.fetchRows({ resetPage: true });
        }
    }

    syncAdvancedFilterBadge() {
        if (!this.hasAdvancedFilterBadgeTarget) {
            return;
        }
        const active = (this.advancedFilters ?? []).length > 0;
        this.advancedFilterBadgeTarget.classList.toggle('hidden', !active);
    }

    onFilterInput() {
        this.syncSearchSubmitEnabled();
    }

    syncSearchSubmitEnabled() {
        const inputQuery = this.hasFilterTarget ? String(this.filterTarget.value ?? '').trim() : '';
        const hasInputQuery = inputQuery !== '';
        const hasActiveFilter = String(this.filterQuery ?? '').trim() !== '';

        if (this.hasSearchSubmitTarget) {
            this.searchSubmitTarget.disabled = this.loadingValue || !hasInputQuery;
        }

        if (this.hasSearchResetTarget) {
            this.searchResetTarget.disabled = this.loadingValue || (!hasInputQuery && !hasActiveFilter);
        }
    }

    applyFilter(event) {
        event?.preventDefault?.();

        if (this.hasFilterTarget) {
            this.filterQuery = this.filterTarget.value ?? '';
        }

        const query = (this.filterQuery ?? '').trim();
        if (!query) {
            this.syncSearchSubmitEnabled();
            return;
        }

        if (!this.table) {
            return;
        }

        // Quick search replaces advanced rules.
        this.advancedFilters = [];
        if (this.hasAdvancedFilterPanelTarget) {
            for (const row of this.advancedFilterPanelTarget.querySelectorAll('[data-advanced-filter-row]')) {
                const valueInput = row.querySelector('[data-advanced-filter="value"]');
                if (valueInput) {
                    valueInput.value = '';
                }
            }
        }
        this.syncAdvancedFilterBadge();

        if (this.serverPagination) {
            // Server applies filter — keep client filters clear so we don't double-filter the page.
            this.table.setGlobalFilter('');
            this.table.setColumnFilters([]);
            this.fetchRows({ resetPage: true });
            return;
        }

        const columnId = this.filterColumn || this.defaultFilterColumnId();

        this.table.setGlobalFilter('');
        this.table.setColumnFilters(
            columnId
                ? [{
                    id: columnId,
                    value: {
                        query,
                        operator: this.filterOperator || DEFAULT_FILTER_OPERATOR,
                    },
                }]
                : []
        );
    }

    resetFilter(event) {
        event?.preventDefault?.();

        this.filterQuery = '';
        if (this.hasFilterTarget) {
            this.filterTarget.value = '';
        }

        this.syncSearchSubmitEnabled();

        if (!this.table) {
            return;
        }

        // Keep advanced filters if active; otherwise clear column filters.
        if ((this.advancedFilters ?? []).length > 0) {
            if (this.serverPagination) {
                this.fetchRows({ resetPage: true });
            } else {
                this.table.setGlobalFilter('');
                this.table.setColumnFilters(
                    this.advancedFilters.map((filter) => ({
                        id: filter.field,
                        value: {
                            query: filter.value,
                            queryTo: filter.valueTo ?? '',
                            operator: filter.operator || DEFAULT_FILTER_OPERATOR,
                        },
                    }))
                );
            }
            return;
        }

        this.table.setGlobalFilter('');
        this.table.setColumnFilters([]);

        if (this.serverPagination) {
            this.fetchRows({ resetPage: true });
        }
    }

    changeFilterField(event) {
        this.filterColumn = event.detail?.value || this.defaultFilterColumnId();
    }

    changeFilterOperator(event) {
        this.filterOperator = event.detail?.value || DEFAULT_FILTER_OPERATOR;
    }

    defaultFilterColumnId() {
        const columns = this.config?.columns ?? this.columnDefs ?? [];
        for (const column of columns) {
            if (column?.meta?.format === 'buttons') {
                continue;
            }
            const id = column.accessorKey ?? column.id ?? null;
            if (id) {
                return id;
            }
        }
        return null;
    }

    toggleColumnVisibility(event) {
        const item = event.currentTarget;
        if (!this.columnVisibilityEnabled || !this.table || item?.dataset?.disabled === 'true') {
            return;
        }

        const columnId = item.dataset.columnId;
        const column = columnId ? this.table.getColumn(columnId) : null;
        if (!column || !column.getCanHide()) {
            return;
        }

        const visible = item.getAttribute('aria-checked') === 'true';

        if (!visible && this.table.getVisibleLeafColumns().length <= 1) {
            item.setAttribute('aria-checked', 'true');
            const indicator = item.querySelector('[data-checkbox-indicator]');
            if (indicator) {
                indicator.hidden = false;
            }
            return;
        }

        column.toggleVisibility(visible);

        const filterColumnHidden = !visible && this.filterColumn === columnId;
        if (filterColumnHidden) {
            const next = this.table
                .getVisibleLeafColumns()
                .map((col) => col.id)
                .find((id) => id && id !== columnId);
            this.filterColumn = next ?? this.defaultFilterColumnId();
        }

        // Re-fetch so the JSON payload only includes currently selected fields.
        if (this.serverPagination) {
            this.fetchRows();
        } else {
            if (filterColumnHidden) {
                this.applyFilter();
            }
            this.render();
        }
    }

    /**
     * Visible data column ids (accessorKeys) for the server {@code fields[]} query param.
     * Skips button/action columns that are not row JSON keys.
     *
     * @returns {string[]}
     */
    visibleDataFieldIds() {
        if (!this.table) {
            return [];
        }

        const columns = this.columnVisibilityEnabled
            ? this.table.getVisibleLeafColumns()
            : this.table.getAllLeafColumns();

        const fields = [];
        for (const column of columns) {
            const id = column?.id;
            if (!id) {
                continue;
            }
            if (column.columnDef?.meta?.format === 'buttons') {
                continue;
            }
            fields.push(id);
        }

        return fields;
    }

    /**
     * Build TanStack initial {@code columnVisibility} from column defs with {@code defaultVisible: false}.
     * Those columns stay in the Columns menu ({@code enableHiding}) but start unchecked/hidden.
     *
     * @param {Array<Record<string, mixed>>} definitions
     * @returns {Record<string, boolean>}
     */
    initialColumnVisibility(definitions) {
        const visibility = {};
        for (const definition of definitions) {
            if (definition?.defaultVisible === false) {
                const id = definition.id ?? definition.accessorKey;
                if (id) {
                    visibility[id] = false;
                }
            }
        }

        return visibility;
    }

    changePageSize(event) {
        const value = Number(event.detail?.value);
        if (!Number.isFinite(value) || value <= 0 || !this.table) {
            return;
        }

        this.table.setPageSize(value);
        this.table.setPageIndex(0);

        if (this.serverPagination) {
            this.fetchRows();
            return;
        }

        this.render();
    }

    firstPage() {
        this.goToPageIndex(0);
    }

    previousPage() {
        if (!this.table) {
            return;
        }
        this.goToPageIndex(Math.max(this.table.store.state.pagination.pageIndex - 1, 0));
    }

    nextPage() {
        if (!this.table) {
            return;
        }
        const maxIndex = Math.max(this.table.getPageCount() - 1, 0);
        this.goToPageIndex(Math.min(this.table.store.state.pagination.pageIndex + 1, maxIndex));
    }

    lastPage() {
        if (!this.table) {
            return;
        }
        this.goToPageIndex(Math.max(this.table.getPageCount() - 1, 0));
    }

    goToPage(event) {
        event?.preventDefault?.();
        if (!this.table || !this.hasPageInputTarget) {
            return;
        }

        const pageCount = Math.max(this.table.getPageCount(), 1);
        const raw = Number(this.pageInputTarget.value);
        const page = Number.isFinite(raw) ? Math.trunc(raw) : 1;
        const clamped = Math.min(Math.max(page, 1), pageCount);

        this.pageInputTarget.value = String(clamped);
        this.goToPageIndex(clamped - 1);
    }

    goToPageIndex(pageIndex) {
        if (!this.table) {
            return;
        }

        const current = this.table.store.state.pagination.pageIndex;
        if (pageIndex === current && !this.serverPagination) {
            return;
        }

        this.table.setPageIndex(pageIndex);

        if (this.serverPagination) {
            this.fetchRows();
            return;
        }

        this.render();
    }

    refresh() {
        if (this.serverPagination) {
            this.fetchRows({ resetPage: true });
            return;
        }

        if (window.Turbo?.visit) {
            window.Turbo.visit(window.location.href, { action: 'replace' });
            return;
        }

        window.location.reload();
    }

    /**
     * Fetch a page from the JSON endpoint.
     * Expects `{ data, total, page, pageSize }` (bare `data[]` still supported).
     */
    async fetchRows({ resetPage = false } = {}) {
        if (!this.hasAjaxUrl || !this.table) {
            return;
        }

        if (resetPage) {
            this.table.setPageIndex(0);
        }

        const { pageIndex, pageSize } = this.table.store.state.pagination;
        const url = new URL(this.ajaxUrl, window.location.origin);
        url.searchParams.set('page', String(pageIndex + 1));
        url.searchParams.set('pageSize', String(pageSize));

        const fields = this.visibleDataFieldIds();
        fields.forEach((field) => {
            url.searchParams.append('fields[]', field);
        });

        const advanced = Array.isArray(this.advancedFilters) ? this.advancedFilters : [];
        const requestFilters = advanced.length > 0
            ? advanced
            : (() => {
                const value = (this.filterQuery ?? '').trim();
                const field = this.filterColumn || this.defaultFilterColumnId();
                return value && field
                    ? [{
                        field,
                        operator: this.filterOperator || DEFAULT_FILTER_OPERATOR,
                        value,
                    }]
                    : [];
            })();

        requestFilters.forEach((filter, index) => {
            url.searchParams.set(`filters[${index}][field]`, filter.field);
            url.searchParams.set(`filters[${index}][operator]`, filter.operator || 'contains');
            url.searchParams.set(`filters[${index}][value]`, filter.value ?? '');
            if (filter.valueTo) {
                url.searchParams.set(`filters[${index}][valueTo]`, filter.valueTo);
            }
        });

        const sorting = Array.isArray(this.table.store.state.sorting)
            ? this.table.store.state.sorting
            : [];
        sorting.forEach((sort, index) => {
            const field = typeof sort?.id === 'string' ? sort.id.trim() : '';
            if (!field) {
                return;
            }
            url.searchParams.set(`sorts[${index}][field]`, field);
            url.searchParams.set(`sorts[${index}][direction]`, sort.desc ? 'desc' : 'asc');
        });

        this.abortController?.abort();
        this.abortController = new AbortController();
        this.loadingValue = true;

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: this.abortController.signal,
            });

            if (!response.ok) {
                throw new Error(`Failed to load table data (${response.status})`);
            }

            const body = await response.json();
            const rows = Array.isArray(body) ? body : (body.data ?? []);
            const total = Array.isArray(body)
                ? rows.length
                : Number(body.total ?? body.pagination?.total ?? rows.length);

            this.setRows(rows, { total });

            this.element.dispatchEvent(
                new CustomEvent('tanstack-table:loaded', {
                    detail: { rows, total, page: pageIndex + 1, pageSize },
                    bubbles: true,
                })
            );
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            console.error('[tanstack-table]', error);
            this.element.dispatchEvent(
                new CustomEvent('tanstack-table:error', {
                    detail: { error },
                    bubbles: true,
                })
            );
        } finally {
            this.loadingValue = false;
            this.abortController = null;
        }
    }

    setRows(rows, { total = null } = {}) {
        if (!this.table) {
            return;
        }

        this.rows = Array.isArray(rows) ? rows : [];
        this.totalRows = total == null ? this.rows.length : Math.max(0, Number(total) || 0);

        // options.data / rowCount changes do not notify table.store — render explicitly.
        this.table.setOptions({
            data: this.rows,
            rowCount: this.totalRows,
            manualPagination: this.serverPagination,
            manualSorting: this.serverPagination,
        });

        this.render();
    }

    loadingValueChanged() {
        this.syncLoadingUi();
    }

    syncLoadingUi() {
        const loading = this.loadingValue;

        this.element.setAttribute('aria-busy', String(loading));
        this.element.dataset.loading = String(loading);

        if (this.hasLoadingTarget) {
            const overlay = this.loadingTarget;
            overlay.hidden = !loading;
            overlay.setAttribute('aria-hidden', String(!loading));
        }

        if (this.hasRefreshButtonTarget) {
            this.refreshButtonTarget.disabled = loading;
            const icon =
                this.refreshButtonTarget.querySelector('[data-refresh-icon]') ??
                this.refreshButtonTarget.querySelector('svg');
            if (icon) {
                icon.classList.toggle('animate-spin', loading);
            }
        }

        if (this.hasSearchSubmitTarget) {
            const idle = this.searchSubmitTarget.querySelector('[data-search-submit-idle]');
            const spinning = this.searchSubmitTarget.querySelector('[data-search-submit-loading]');
            if (idle) {
                idle.hidden = loading;
            }
            if (spinning) {
                spinning.hidden = !loading;
            }
        }

        this.syncSearchSubmitEnabled();
    }

    /**
     * Generic header-action click handler. Dispatches a customizable DOM event
     * (see data-header-action-event). Built-in ids may also run side effects
     * (e.g. add-row inserts an empty client-side row).
     */
    headerAction(event) {
        event?.preventDefault?.();
        const button = event?.currentTarget;
        const actionId = String(button?.dataset?.headerAction ?? '');
        const eventName =
            button?.dataset?.headerActionEvent ||
            (actionId ? `tanstack-table:${actionId}` : 'tanstack-table:header-action');

        const detail = {
            action: actionId,
            button,
            rows: this.rows,
            config: this.config,
            table: this.table,
        };

        this.dispatchHeaderActionEvent(eventName, detail);
    }

    /**
     * AddButton: open a large dialog and load {@code data-url}.
     */
    addAction(event) {
        event?.preventDefault?.();
        event?.stopPropagation?.();
        const button = event?.currentTarget;
        if (!button || button.dataset.addButton !== 'true') {
            return;
        }

        const url = String(button.dataset.url ?? '').trim();
        if (!url) {
            console.warn('[tstdt] AddButton requires url() in PHP.');
            return;
        }

        this.openAddDrawer({
            url,
            title: String(button.dataset.drawerTitle ?? button.getAttribute('aria-label') ?? 'Add').trim() || 'Add',
            button,
        });
    }

    /** @deprecated Prefer {@see addAction} / AddButton::url(). Kept for BC. */
    addRow(event) {
        const button = event?.currentTarget;
        if (button?.dataset?.addButton === 'true') {
            this.addAction(event);
            return;
        }

        event?.preventDefault?.();
        const row = this.createEmptyRow();
        if (!row) {
            return;
        }

        this.setRows([row, ...this.rows], { resetPage: true });
        this.dispatchHeaderActionEvent(
            button?.dataset?.headerActionEvent || 'tanstack-table:add-row',
            {
                action: 'add-row',
                button,
                row,
                rows: this.rows,
                config: this.config,
                table: this.table,
            }
        );
    }

    openAddDrawer({ url, title, button }) {
        this.dismissAddDrawer();

        const dialog = document.createElement('dialog');
        dialog.dataset.slot = 'tanstack-add-drawer';
        dialog.dataset.maximized = 'false';
        dialog.className = this.addDrawerClass({ maximized: false });
        dialog.innerHTML = `
            <div data-slot="tanstack-add-drawer-header" class="flex h-14 shrink-0 items-center justify-between gap-3 border-b px-4 sm:px-6">
                <h2 class="truncate text-base font-semibold tracking-tight">${this.escapeHtml(title)}</h2>
                <div class="flex shrink-0 items-center gap-1">
                    <button type="button" data-slot="button" data-variant="ghost" data-size="icon-sm" data-add-drawer="resize" class="${this.buttonClass('ghost', 'icon-sm')}" aria-label="${this.escapeAttr(this.t('Maximize'))}" title="${this.escapeAttr(this.t('Maximize'))}">
                        ${this.rowActionIcon('lucide:maximize-2') || '⛶'}
                    </button>
                    <button type="button" data-slot="button" data-variant="ghost" data-size="icon-sm" data-add-drawer="close" class="${this.buttonClass('ghost', 'icon-sm')}" aria-label="${this.escapeAttr(this.t('Close'))}">
                        ${this.rowActionIcon('lucide:x') || '×'}
                    </button>
                </div>
            </div>
            <div data-slot="tanstack-add-drawer-body" class="min-h-0 flex-1 overflow-auto p-4 sm:p-6">
                <div class="flex items-center gap-2 text-sm text-muted-foreground" role="status">${this.escapeHtml(this.t('Loading…'))}</div>
            </div>
        `;

        const body = dialog.querySelector('[data-slot="tanstack-add-drawer-body"]');
        const close = () => this.dismissAddDrawer();
        dialog.querySelector('[data-add-drawer="close"]')?.addEventListener('click', close);
        dialog.querySelector('[data-add-drawer="resize"]')?.addEventListener('click', () => this.toggleAddDrawerSize());
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            close();
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                close();
            }
        });
        body?.addEventListener('submit', (event) => this._onAddDrawerSubmit(event));

        this._addDrawer = dialog;
        this._addDrawerUrl = url;
        document.body.appendChild(dialog);
        dialog.showModal?.();

        this.dispatchHeaderActionEvent(button?.dataset?.headerActionEvent || 'tanstack-table:add-row', {
            action: 'add-row',
            button,
            url,
            title,
            rows: this.rows,
            config: this.config,
            table: this.table,
        });

        this.loadAddDrawer(url);
    }

    toggleAddDrawerSize() {
        const dialog = this._addDrawer;
        if (!dialog) {
            return;
        }

        const maximized = dialog.dataset.maximized !== 'true';
        dialog.dataset.maximized = maximized ? 'true' : 'false';
        dialog.className = this.addDrawerClass({ maximized });

        const resizeBtn = dialog.querySelector('[data-add-drawer="resize"]');
        if (resizeBtn) {
            const label = maximized ? this.t('Restore size') : this.t('Maximize');
            resizeBtn.setAttribute('aria-label', label);
            resizeBtn.setAttribute('title', label);
            resizeBtn.innerHTML =
                this.rowActionIcon(maximized ? 'lucide:minimize-2' : 'lucide:maximize-2') || (maximized ? '⛶' : '⛶');
        }
    }

    addDrawerClass({ maximized = false } = {}) {
        const size = maximized
            ? 'h-[95vh] w-[95vw] max-h-[95vh] max-w-[95vw] rounded-lg'
            : 'h-[80vh] w-[60vw] max-h-[80vh] max-w-[60vw] rounded-xl';

        return [
            'fixed top-1/2 left-1/2 z-[100] m-0 flex -translate-x-1/2 -translate-y-1/2',
            size,
            'flex-col gap-0 overflow-hidden border border-border bg-background p-0',
            'text-sm text-foreground shadow-xl outline-none open:flex backdrop:bg-black/40',
        ].join(' ');
    }

    async loadAddDrawer(url) {
        const body = this._addDrawer?.querySelector?.('[data-slot="tanstack-add-drawer-body"]');
        if (!body) {
            return;
        }

        body.innerHTML =
            `<div class="flex items-center gap-2 text-sm text-muted-foreground" role="status">${this.escapeHtml(this.t('Loading…'))}</div>`;

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Add-Drawer': '1',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(`Failed to load form (${response.status})`);
            }
            body.innerHTML = await response.text();
        } catch (error) {
            body.innerHTML = `<div class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert">${this.escapeHtml(error?.message ?? this.t('Failed to load'))}</div>`;
        }
    }

    async _onAddDrawerSubmit(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !this._addDrawer?.contains(form)) {
            return;
        }

        event.preventDefault();

        const body = this._addDrawer.querySelector('[data-slot="tanstack-add-drawer-body"]');
        const action = form.getAttribute('action') || this._addDrawerUrl || window.location.href;
        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        const payload = new FormData(form);

        if (body) {
            body.innerHTML =
                '<div class="flex items-center gap-2 text-sm text-muted-foreground" role="status">Saving…</div>';
        }

        try {
            const response = await fetch(action, {
                method,
                body: payload,
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Add-Drawer': '1',
                },
                credentials: 'same-origin',
                redirect: 'follow',
            });

            if (!response.ok) {
                throw new Error(`Save failed (${response.status})`);
            }

            const contentType = String(response.headers.get('content-type') ?? '');
            const html = await response.text();

            // Successful create typically redirects to the list page (full HTML document).
            if (response.redirected || (contentType.includes('text/html') && /<html[\s>]/i.test(html))) {
                this.dismissAddDrawer();
                if (this.hasAjaxUrl) {
                    await this.fetchRows();
                } else {
                    window.location.reload();
                }
                return;
            }

            if (body) {
                body.innerHTML = html;
            }
        } catch (error) {
            if (body) {
                body.innerHTML = `<div class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert">${this.escapeHtml(error?.message ?? this.t('Save failed'))}</div>`;
            }
        }
    }

    dismissAddDrawer() {
        const dialog = this._addDrawer;
        this._addDrawer = null;
        this._addDrawerUrl = null;
        if (!dialog) {
            return;
        }
        if (dialog.open) {
            dialog.close();
        }
        dialog.remove();
    }

    createEmptyRow() {
        if (!this.table) {
            return null;
        }

        const row = {};
        for (const column of this.columnDefs) {
            const key = column.accessorKey ?? column.id;
            if (!key || key === 'id') {
                continue;
            }
            row[key] = '';
        }
        row.id = `new-${Date.now()}`;

        return row;
    }

    dispatchHeaderActionEvent(eventName, detail) {
        const custom = new CustomEvent(eventName, {
            detail,
            bubbles: true,
        });
        this.element.dispatchEvent(custom);
    }

    changeBulkAction(event) {
        this.selectedBulkActionId = String(event.detail?.value ?? '').trim();
        this.syncBulkSelectionUi();
    }

    cloneRowData(row) {
        const data = row && typeof row === 'object' && 'original' in row ? row.original : row;
        if (!data || typeof data !== 'object') {
            return data;
        }

        return { ...data };
    }

    rememberSelectedRow(row) {
        const id = String(row?.id ?? '');
        if (!id) {
            return;
        }
        this.selectedRowsById.set(id, this.cloneRowData(row));
    }

    forgetSelectedRow(rowOrId) {
        const id = typeof rowOrId === 'string' || typeof rowOrId === 'number'
            ? String(rowOrId)
            : String(rowOrId?.id ?? '');
        if (!id) {
            return;
        }
        this.selectedRowsById.delete(id);
    }

    /**
     * Full selected row objects (not just ids), including rows selected on other pages.
     *
     * @returns {array<object>}
     */
    getSelectedRowsData() {
        const selection = this.table?.store?.state?.rowSelection ?? {};
        const selectedIds = Object.keys(selection).filter((id) => selection[id]);

        const loadedById = new Map();
        for (const row of this.table?.getRowModel?.().rows ?? []) {
            loadedById.set(String(row.id), this.cloneRowData(row));
        }
        for (const row of this.rows ?? []) {
            const id = String(row?.id ?? '');
            if (id && !loadedById.has(id)) {
                loadedById.set(id, { ...row });
            }
        }

        return selectedIds.map((id) => {
            if (loadedById.has(id)) {
                const data = loadedById.get(id);
                this.selectedRowsById.set(id, data);
                return data;
            }

            return this.selectedRowsById.get(id) ?? null;
        }).filter((row) => row != null);
    }

    runBulkAction(event) {
        event?.preventDefault?.();
        const actionId = String(this.selectedBulkActionId ?? '').trim();
        if (!actionId || !this.table || !this.bulkSelectionEnabled) {
            return;
        }

        const rows = this.getSelectedRowsData();
        if (rows.length === 0) {
            return;
        }

        const action = this.bulkActions.find((item) => item.id === actionId);
        const eventName = String(action?.event || `tanstack-table:bulk-${actionId}`).trim();
        const rowIds = rows.map((row) => String(row?.id ?? ''));
        const url = String(action?.url ?? '').trim() || null;
        const csrfToken = String(action?.csrfToken ?? '').trim() || null;
        const csrfField = String(action?.csrfField ?? '_token').trim() || '_token';

        const tableId = String(this.config?.id ?? '').trim() || null;

        const detail = {
            action: actionId,
            event: eventName,
            tableId,
            url,
            csrfToken,
            csrfField,
            rows,
            rowIds,
            config: this.config,
            table: this.table,
        };

        this.dispatchHeaderActionEvent(eventName, detail);
        // Also fire the shared bulk-action event for app-level listeners.
        if (eventName !== 'tanstack-table:bulk-action') {
            this.dispatchHeaderActionEvent('tanstack-table:bulk-action', detail);
        }
    }

    resetBulkActionSelect() {
        this.selectedBulkActionId = '';
        if (!this.hasBulkActionTarget) {
            return;
        }

        const selectController = this.application?.getControllerForElementAndIdentifier?.(
            this.bulkActionTarget,
            'select'
        );
        if (selectController) {
            selectController.valueValue = '';
        }
        this.syncBulkSelectionUi();
    }

    syncBulkSelectionUi() {
        if (!this.bulkSelectionEnabled || !this.table) {
            return;
        }

        const selectedCount = Object.keys(this.table.store?.state?.rowSelection ?? {}).filter(
            (id) => this.table.store.state.rowSelection[id]
        ).length;
        const hasAction = Boolean(this.selectedBulkActionId);

        if (this.hasBulkActionTriggerTarget) {
            this.bulkActionTriggerTarget.disabled = selectedCount === 0;
        }

        if (this.hasBulkActionRunTarget) {
            this.bulkActionRunTarget.disabled = selectedCount === 0 || !hasAction;
        }

        if (this.hasBulkSelectedCountTarget) {
            if (selectedCount > 0) {
                this.bulkSelectedCountTarget.hidden = false;
                this.bulkSelectedCountTarget.textContent =
                    selectedCount === 1
                        ? this.t('1 selected')
                        : this.t('%count% selected', { '%count%': selectedCount });
            } else {
                this.bulkSelectedCountTarget.hidden = true;
                this.bulkSelectedCountTarget.textContent = '';
            }
        }
    }

    createSelectionCheckbox({ checked, indeterminate = false, ariaLabel, onChange }) {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = CHECKBOX_CLASS;
        checkbox.checked = Boolean(checked);
        checkbox.indeterminate = Boolean(indeterminate);
        checkbox.setAttribute('aria-label', ariaLabel);
        checkbox.addEventListener('click', (event) => event.stopPropagation());
        checkbox.addEventListener('change', (event) => {
            event.stopPropagation();
            onChange?.(event);
        });

        return checkbox;
    }

    buildColumns(definitions) {
        return definitions.map((definition) => {
            const meta = definition.meta ?? {};
            const column = {
                id: definition.id ?? definition.accessorKey,
                // Preserve explicit empty string headers (icon-only action columns).
                header:
                    definition.header !== undefined && definition.header !== null
                        ? definition.header
                        : (definition.accessorKey ?? definition.id),
                enableSorting: definition.enableSorting !== false,
                // Hideable by default whenever column visibility is enabled.
                enableHiding:
                    this.columnVisibilityEnabled &&
                    definition.enableHiding !== false &&
                    meta.hideable !== false,
                filterFn: 'operatorFilter',
                meta,
                cell: (info) =>
                    this.formatCell(info.getValue(), meta, info.row, info.column?.id ?? definition.id),
            };

            if (definition.accessorKey) {
                column.accessorKey = definition.accessorKey;
            } else {
                column.accessorFn = (row) => row[definition.id];
            }

            return column;
        });
    }

    formatCell(value, meta, row = null, columnId = null) {
        if (meta.format === 'currency') {
            const amount = Number(value ?? 0);
            return `$${amount.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        }

        if (meta.format === 'number') {
            return Number(value ?? 0).toLocaleString();
        }

        if (meta.format === 'badge') {
            const map = meta.badgeMap ?? {};
            const key = String(value ?? '');
            const variant = map[key] ?? meta.badgeVariant ?? 'secondary';
            const label = meta.badgeLabels?.[key] ?? this.titleCase(key);
            return `<span data-slot="badge" data-variant="${this.escapeAttr(variant)}" class="${this.badgeClass(variant)}">${this.escapeHtml(label)}</span>`;
        }

        if (meta.format === 'switch') {
            return this.formatSwitchCell(value, meta, row, columnId);
        }

        if (meta.format === 'buttons') {
            return this.formatButtonsCell(meta.buttons ?? [], row);
        }

        if (meta.format === 'html') {
            // Escaped by default. Opt in with Column::rawHtml() / meta.htmlTrusted.
            if (meta.htmlTrusted === true) {
                return String(value ?? '');
            }

            return this.escapeHtml(String(value ?? ''));
        }

        if (value === null || value === undefined || value === '') {
            return '—';
        }

        return this.escapeHtml(String(value));
    }

    /**
     * Action columns are sized to buttonCount × 35px.
     */
    applyButtonsColumnWidth(el, meta) {
        if (meta?.format !== 'buttons') {
            return;
        }

        const count = Array.isArray(meta.buttons) ? meta.buttons.length : 0;
        if (count < 1) {
            return;
        }

        const width = `${count * 35}px`;
        el.style.width = width;
        el.style.minWidth = width;
        el.style.maxWidth = width;
    }

    /**
     * Map column meta.align ({@code start}|{@code center}|{@code end}) to Tailwind classes.
     * @param {unknown} align
     * @param {{ head?: boolean }} [options]
     */
    alignClass(align, { head = false } = {}) {
        const key = String(align ?? '')
            .trim()
            .toLowerCase();
        if (key === 'end' || key === 'right') {
            return head ? 'text-end' : 'text-end tabular-nums';
        }
        if (key === 'center') {
            return 'text-center';
        }
        if (key === 'start' || key === 'left') {
            return 'text-start';
        }
        return '';
    }

    formatSwitchCell(value, meta, row = null, columnId = null) {
        const checkedValue = String(meta.switchCheckedValue ?? '1');
        const uncheckedValue = String(meta.switchUncheckedValue ?? '0');
        const current = String(value ?? '');
        const checked =
            current === checkedValue ||
            current === 'true' ||
            current === '1' ||
            current.toLowerCase() === 'yes';
        const labels = meta.switchLabels ?? {};
        const checkedLabel = String(labels[checkedValue] ?? checkedValue);
        const uncheckedLabel = String(labels[uncheckedValue] ?? uncheckedValue);
        const label = checked ? checkedLabel : uncheckedLabel;
        const url = this.resolveButtonUrl(meta.switchUrl, row);
        const recordId = this.escapeAttr(String(row?.id ?? row?.original?.id ?? ''));
        const fieldKey = String(columnId ?? '').trim();
        const csrfToken = String(meta.switchCsrfToken ?? '').trim();
        const csrfField = String(meta.switchCsrfField ?? '_token').trim() || '_token';
        const valueField = String(meta.switchValueField ?? 'value').trim() || 'value';
        const interactive = Boolean(url);
        const state = checked ? 'checked' : 'unchecked';
        const attrs = [
            `data-slot="switch"`,
            `data-state="${state}"`,
            `aria-checked="${checked ? 'true' : 'false'}"`,
            `aria-label="${this.escapeAttr(label)}"`,
            `title="${this.escapeAttr(label)}"`,
            `data-checked-value="${this.escapeAttr(checkedValue)}"`,
            `data-unchecked-value="${this.escapeAttr(uncheckedValue)}"`,
            `data-checked-label="${this.escapeAttr(checkedLabel)}"`,
            `data-unchecked-label="${this.escapeAttr(uncheckedLabel)}"`,
            `data-value-field="${this.escapeAttr(valueField)}"`,
            `data-row-id="${recordId}"`,
            fieldKey ? `data-column-key="${this.escapeAttr(fieldKey)}"` : '',
            url ? `data-url="${this.escapeAttr(url)}"` : '',
            csrfToken ? `data-csrf-token="${this.escapeAttr(csrfToken)}"` : '',
            `data-csrf-field="${this.escapeAttr(csrfField)}"`,
            interactive
                ? 'data-action="click->tstdt--tanstack-table#switchAction"'
                : 'disabled',
        ]
            .filter(Boolean)
            .join(' ');

        return `<div data-slot="switch-cell" class="inline-flex items-center gap-2">
            <button
                type="button"
                role="switch"
                ${attrs}
                class="${this.switchClass()}"
            ><span data-slot="switch-thumb" class="${this.switchThumbClass()}"></span></button>
            <span data-slot="switch-label" class="text-sm text-foreground">${this.escapeHtml(label)}</span>
        </div>`;
    }

    switchClass() {
        return [
            'group/switch inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent shadow-xs transition-colors',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
            'disabled:cursor-not-allowed disabled:opacity-50',
            'data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
        ].join(' ');
    }

    switchThumbClass() {
        return [
            'pointer-events-none block size-4 rounded-full bg-background shadow-lg ring-0 transition-transform',
            'group-data-[state=checked]/switch:translate-x-4 group-data-[state=unchecked]/switch:translate-x-0',
        ].join(' ');
    }

    /**
     * Toggle a {@see Column::switch()} cell: POST new value, then refresh the table.
     */
    async switchAction(event) {
        event?.preventDefault?.();
        event?.stopPropagation?.();
        const button = event?.currentTarget;
        if (!(button instanceof HTMLElement) || button.dataset.slot !== 'switch') {
            return;
        }
        if (button.disabled || button.dataset.loading === 'true') {
            return;
        }

        const url = String(button.dataset.url ?? '').trim();
        if (!url) {
            return;
        }

        const checkedValue = String(button.dataset.checkedValue ?? '1');
        const uncheckedValue = String(button.dataset.uncheckedValue ?? '0');
        const valueField = String(button.dataset.valueField ?? 'value').trim() || 'value';
        const csrfField = String(button.dataset.csrfField ?? '_token').trim() || '_token';
        const csrfToken = String(button.dataset.csrfToken ?? '').trim();
        const wasChecked = button.getAttribute('aria-checked') === 'true';
        const nextChecked = !wasChecked;
        const nextValue = nextChecked ? checkedValue : uncheckedValue;

        this._setSwitchDomState(button, nextChecked);
        button.dataset.loading = 'true';
        button.disabled = true;

        const body = new FormData();
        body.set(valueField, nextValue);
        if (csrfToken) {
            body.set(csrfField, csrfToken);
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                body,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(`Switch update failed (${response.status})`);
            }

            let payload = null;
            const contentType = String(response.headers.get('content-type') ?? '');
            if (contentType.includes('application/json')) {
                payload = await response.json();
            }

            const rowId = String(button.dataset.rowId ?? '').trim();
            const resolvedValue =
                payload?.status != null
                    ? String(payload.status)
                    : payload?.[valueField] != null
                      ? String(payload[valueField])
                      : nextValue;

            if (rowId) {
                const field =
                    String(button.dataset.columnKey ?? '').trim() ||
                    this._switchColumnKeyForButton(button);
                this._patchRowField(rowId, field, resolvedValue);
            }

            this.element.dispatchEvent(
                new CustomEvent('tanstack-table:switch', {
                    bubbles: true,
                    detail: {
                        url,
                        rowId,
                        value: resolvedValue,
                        checked: resolvedValue === checkedValue,
                        payload,
                        config: this.config,
                    },
                })
            );
        } catch (error) {
            console.error('[tanstack-table] switch', error);
            this._setSwitchDomState(button, wasChecked);
            this.element.dispatchEvent(
                new CustomEvent('tanstack-table:switch-error', {
                    bubbles: true,
                    detail: { error, url, config: this.config },
                })
            );
        } finally {
            button.dataset.loading = 'false';
            button.disabled = false;
        }
    }

    _setSwitchDomState(button, checked) {
        if (!(button instanceof HTMLElement)) {
            return;
        }
        const state = checked ? 'checked' : 'unchecked';
        const label = checked
            ? String(button.dataset.checkedLabel ?? button.dataset.checkedValue ?? '1')
            : String(button.dataset.uncheckedLabel ?? button.dataset.uncheckedValue ?? '0');
        button.dataset.state = state;
        button.setAttribute('aria-checked', checked ? 'true' : 'false');
        button.setAttribute('aria-label', label);
        button.title = label;
        const labelEl =
            button.parentElement?.querySelector?.('[data-slot="switch-label"]') ??
            button.closest?.('[data-slot="switch-cell"]')?.querySelector?.('[data-slot="switch-label"]');
        if (labelEl instanceof HTMLElement) {
            labelEl.textContent = label;
        }
    }

    _switchColumnKeyForButton(button) {
        const cell = button.closest?.('[data-slot="tstdt_table-cell"]');
        // Prefer patching via known switch columns from config.
        const columns = this.config?.columns ?? [];
        for (const column of columns) {
            if (column?.meta?.format === 'switch') {
                return String(column.accessorKey ?? column.id ?? '');
            }
        }
        return cell?.dataset?.columnKey ? String(cell.dataset.columnKey) : 'status';
    }

    _patchRowField(rowId, field, value) {
        if (!rowId || !field || !this.table) {
            return;
        }
        const nextRows = (this.rows ?? []).map((row) => {
            if (String(row?.id ?? '') !== String(rowId)) {
                return row;
            }
            return { ...row, [field]: value };
        });
        this.setRows(nextRows, { total: this.totalRows });
    }

    formatButtonsCell(buttons, data) {
        if (!Array.isArray(buttons) || buttons.length === 0) {
            return '';
        }

        const recordId = this.escapeAttr(String(data?.id ?? data?.original?.id ?? ''));
        const parts = buttons.map((button) => {
            const workspaceTab = button?.workspaceTab === true;
            const deleteButton = button?.deleteButton === true;
            const id = workspaceTab ? 'tab' : deleteButton ? 'delete' : String(button?.id ?? '');
            const label = String(button?.label ?? id);
            const variant = button?.variant ?? 'outline';
            const size = button?.size ?? 'icon-sm';
            const eventName = workspaceTab
                ? 'workspace-tabs:open'
                : deleteButton
                  ? 'tanstack-table:row-delete'
                  : button?.event || (id ? `tanstack-table:row-${id}` : 'tanstack-table:row-action');
            const iconName = button?.icon ?? null;
            const icon = iconName ? this.rowActionIcon(iconName) : '';
            const showLabel = !String(size).startsWith('icon');
            const content = icon
                ? `${icon}${showLabel ? `<span>${this.escapeHtml(label)}</span>` : ''}`
                : this.escapeHtml(label);
            const url = this.resolveButtonUrl(button?.url, data);
            const tabLabel = workspaceTab ? this.resolveWorkspaceTabLabel(data, url) : '';
            const confirmMessage = deleteButton
                ? this.resolveButtonTemplate(button?.confirm ?? this.t('Delete this row?'), data)
                : '';
            const clickAction = workspaceTab
                ? 'workspaceTabAction'
                : deleteButton
                  ? 'deleteAction'
                  : 'rowAction';
            const extraAttrs = [
                workspaceTab ? 'data-workspace-tab="true"' : '',
                workspaceTab ? 'data-tab-state="closed"' : '',
                workspaceTab ? `data-tab-label="${this.escapeAttr(tabLabel || label)}"` : '',
                workspaceTab ? `data-tab-closed-label="${this.escapeAttr(label)}"` : '',
                workspaceTab && iconName ? `data-tab-closed-icon="${this.escapeAttr(iconName)}"` : '',
                workspaceTab ? 'data-context-menu="true"' : '',
                workspaceTab && button?.nested === true ? 'data-workspace-nested="true"' : '',
                deleteButton ? 'data-delete-button="true"' : '',
                deleteButton ? `data-confirm-message="${this.escapeAttr(confirmMessage)}"` : '',
                deleteButton && button?.csrfToken
                    ? `data-csrf-token="${this.escapeAttr(String(button.csrfToken))}"`
                    : '',
                deleteButton && button?.csrfField
                    ? `data-csrf-field="${this.escapeAttr(String(button.csrfField))}"`
                    : '',
                deleteButton && button?.idField
                    ? `data-id-field="${this.escapeAttr(String(button.idField))}"`
                    : '',
                url ? `data-url="${this.escapeAttr(url)}"` : '',
            ]
                .filter(Boolean)
                .join(' ');

            return `<button
                type="button"
                data-slot="button"
                data-variant="${this.escapeAttr(variant)}"
                data-size="${this.escapeAttr(size)}"
                data-row-action="${this.escapeAttr(id)}"
                data-row-action-event="${this.escapeAttr(eventName)}"
                ${workspaceTab ? '' : `data-row-id="${recordId}"`}
                ${extraAttrs}
                data-action="click->tstdt--tanstack-table#${clickAction}"
                class="${this.buttonClass(variant, size)}"
                aria-label="${this.escapeAttr(workspaceTab ? tabLabel || label : label)}"
                title="${this.escapeAttr(workspaceTab ? tabLabel || label : label)}"
            >${content}</button>`;
        });

        return `<div class="inline-flex items-center justify-end gap-1">${parts.join('')}</div>`;
    }

    /**
     * Fill {@code {id}} / {@code {field}} placeholders in a button URL from record data.
     */
    resolveButtonUrl(pattern, data) {
        return this.resolveButtonTemplate(pattern, data, { encode: true });
    }

    /**
     * Fill {@code {field}} placeholders in a string from record data.
     */
    resolveButtonTemplate(pattern, data, { encode = false } = {}) {
        const template = String(pattern ?? '');
        if (!template.trim()) {
            return '';
        }
        const source =
            data?.original && typeof data.original === 'object' ? { ...data.original, ...data } : (data ?? {});
        return template.replace(/\{([a-zA-Z0-9_]+)\}/g, (_, key) => {
            const value = source?.[key];
            if (value === null || value === undefined || value === '') {
                return '';
            }
            const text = String(value);
            return encode ? encodeURIComponent(text) : text;
        });
    }

    /**
     * Confirm, then POST to {@see DeleteButton::url()} with the row id.
     */
    deleteAction(event) {
        event?.preventDefault?.();
        event?.stopPropagation?.();
        const button = event?.currentTarget;
        if (!button || button.dataset.deleteButton !== 'true') {
            return;
        }

        const url = String(button.dataset.url ?? '').trim();
        const rowId = String(button.dataset.rowId ?? '').trim();
        if (!url) {
            console.warn('[tstdt] DeleteButton requires url() in PHP.');
            return;
        }

        this.openDeleteConfirm({
            url,
            rowId,
            message:
                String(button.dataset.confirmMessage ?? this.t('Delete this row?')).trim() ||
                this.t('Delete this row?'),
            csrfToken: String(button.dataset.csrfToken ?? '').trim(),
            csrfField: String(button.dataset.csrfField ?? '_token').trim() || '_token',
            idField: String(button.dataset.idField ?? 'id').trim() || 'id',
        });
    }

    openDeleteConfirm({ url, rowId, message, csrfToken, csrfField, idField }) {
        this.dismissDeleteConfirm();

        const dialog = document.createElement('dialog');
        dialog.dataset.slot = 'tanstack-delete-confirm';
        dialog.className =
            'fixed top-1/2 left-1/2 z-[100] w-full max-w-sm -translate-x-1/2 -translate-y-1/2 rounded-xl border border-border bg-popover p-4 text-sm text-popover-foreground shadow-lg backdrop:bg-black/20 open:block';
        dialog.innerHTML = `
            <div class="space-y-1 pb-3">
                <h2 class="text-base font-semibold">Confirm delete</h2>
                <p class="text-muted-foreground">${this.escapeHtml(message)}</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-slot="button" data-variant="outline" data-size="default" data-delete-confirm="cancel" class="${this.buttonClass('outline', 'default')}">Cancel</button>
                <button type="button" data-slot="button" data-variant="destructive" data-size="default" data-delete-confirm="ok" class="${this.buttonClass('destructive', 'default')}">Delete</button>
            </div>
        `;

        const onCancel = () => this.dismissDeleteConfirm();
        const onConfirm = () => {
            this.dismissDeleteConfirm();
            this.submitDeletePost({ url, rowId, csrfToken, csrfField, idField });
        };
        const onBackdrop = (event) => {
            if (event.target === dialog) {
                onCancel();
            }
        };

        dialog.querySelector('[data-delete-confirm="cancel"]')?.addEventListener('click', onCancel);
        dialog.querySelector('[data-delete-confirm="ok"]')?.addEventListener('click', onConfirm);
        dialog.addEventListener('cancel', onCancel);
        dialog.addEventListener('click', onBackdrop);

        this._deleteConfirmDialog = dialog;
        this.element.appendChild(dialog);
        dialog.showModal?.();
    }

    dismissDeleteConfirm() {
        const dialog = this._deleteConfirmDialog;
        if (!dialog) {
            return;
        }
        if (dialog.open) {
            dialog.close();
        }
        dialog.remove();
        this._deleteConfirmDialog = null;
    }

    submitDeletePost({ url, rowId, csrfToken, csrfField, idField }) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        const appendField = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };

        if (rowId) {
            appendField(idField, rowId);
        }
        if (csrfToken) {
            appendField(csrfField, csrfToken);
        }

        document.body.appendChild(form);
        form.submit();
    }

    onRowActionContextMenu(event) {
        const button = event.target?.closest?.(
            '[data-workspace-tab="true"][data-context-menu="true"]'
        );
        if (!button || !this.element.contains(button)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const isOpen = button.dataset.tabState === 'open';
        const actionLabel = isOpen ? this.t('Close tab') : this.t('Open tab');
        const intent = isOpen ? 'close' : 'open';

        this.dismissRowActionContextMenu();

        const menu = document.createElement('div');
        menu.dataset.slot = 'workspace-tab-context-menu';
        menu.setAttribute('role', 'menu');
        menu.className =
            'fixed z-[100] min-w-36 overflow-hidden rounded-lg border border-border bg-popover p-1 text-sm text-popover-foreground shadow-md';
        menu.style.left = `${event.clientX}px`;
        menu.style.top = `${event.clientY}px`;

        const item = document.createElement('button');
        item.type = 'button';
        item.setAttribute('role', 'menuitem');
        item.className =
            'flex w-full cursor-pointer items-center rounded-md px-2 py-1.5 text-left text-sm outline-none hover:bg-muted';
        item.textContent = actionLabel;
        item.addEventListener('click', (clickEvent) => {
            clickEvent.preventDefault();
            clickEvent.stopPropagation();
            this.dismissRowActionContextMenu();
            this.emitWorkspaceTab(intent, {
                button,
                fromContextMenu: true,
                forceActivate: false,
            });
        });

        menu.appendChild(item);
        document.body.appendChild(menu);
        this._rowActionContextMenu = menu;

        // Keep menu inside viewport.
        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            menu.style.left = `${Math.max(8, window.innerWidth - rect.width - 8)}px`;
        }
        if (rect.bottom > window.innerHeight) {
            menu.style.top = `${Math.max(8, window.innerHeight - rect.height - 8)}px`;
        }
    }

    dismissRowActionContextMenu(event) {
        if (event?.type === 'keydown' && event.key !== 'Escape') {
            return;
        }
        this._rowActionContextMenu?.remove();
        this._rowActionContextMenu = null;
    }

    /**
     * Click handler for {@see WorkspaceTabButton} controls.
     */
    workspaceTabAction(event) {
        event?.preventDefault?.();
        event?.stopPropagation?.();
        const button = event?.currentTarget;
        if (!button || button.dataset.workspaceTab !== 'true') {
            return;
        }

        const intent = button.dataset.tabState === 'open' ? 'focus' : 'open';
        this.emitWorkspaceTab(intent, {
            button,
            ctrlKey: Boolean(event?.ctrlKey),
            metaKey: Boolean(event?.metaKey),
            forceActivate: false,
            fromContextMenu: false,
        });
    }

    /**
     * Dispatch {@code workspace-tabs:open|focus|close} from a tab button.
     *
     * @param {'open'|'focus'|'close'} intent
     */
    emitWorkspaceTab(intent, { button, ctrlKey = false, metaKey = false, forceActivate = false, fromContextMenu = false } = {}) {
        const url = String(button?.dataset?.url ?? '').trim();
        if (!url) {
            return;
        }

        const eventName =
            intent === 'focus'
                ? 'workspace-tabs:focus'
                : intent === 'close'
                  ? 'workspace-tabs:close'
                  : 'workspace-tabs:open';

        document.dispatchEvent(
            new CustomEvent(eventName, {
                bubbles: true,
                detail: {
                    url,
                    label: String(button?.dataset?.tabLabel ?? '').trim() || url,
                    button,
                    forceActivate,
                    fromContextMenu,
                    ctrlKey,
                    metaKey,
                    nested: button?.dataset?.workspaceNested === 'true',
                },
            })
        );
    }

    /**
     * Build a tab label from {@code tabTitle} pattern, e.g. "{name} · {category}".
     * Accepts a plain row object or a TanStack {@code Row} ({@code original} unwrapped).
     */
    resolveWorkspaceTabLabel(data, url) {
        const source =
            data?.original && typeof data.original === 'object' ? data.original : (data ?? {});
        const pattern = String(this.config?.tabTitle ?? '').trim();
        if (pattern) {
            let label = pattern.replace(/\{([a-zA-Z0-9_]+)\}/g, (_, key) => {
                const value = source?.[key];
                if (value === null || value === undefined || value === '') {
                    return '';
                }
                return String(value);
            });

            label = label
                .replace(/\s*[·\-–—|/]\s*(?=\s*[·\-–—|/]|$)/g, '')
                .replace(/^\s*[·\-–—|/]\s*/g, '')
                .replace(/\s{2,}/g, ' ')
                .trim();

            if (label) {
                return label;
            }
        }

        return (
            source?.name ||
            source?.sku ||
            source?.title ||
            source?.label ||
            source?.email ||
            url
        );
    }

    /**
     * Row action click handler. Dispatches a customizable DOM event
     * (see data-row-action-event) with the full row object.
     */
    rowAction(event) {
        event?.preventDefault?.();
        event?.stopPropagation?.();
        const button = event?.currentTarget;
        if (button?.dataset?.workspaceTab === 'true') {
            this.workspaceTabAction(event);
            return;
        }
        const actionId = String(button?.dataset?.rowAction ?? '');
        const eventName =
            button?.dataset?.rowActionEvent ||
            (actionId ? `tanstack-table:row-${actionId}` : 'tanstack-table:row-action');
        const rowId = String(button?.dataset?.rowId ?? '');
        const row = this.findRowById(rowId);

        const detail = {
            action: actionId,
            button,
            row,
            rowId,
            config: this.config,
            table: this.table,
            ctrlKey: Boolean(event?.ctrlKey),
            metaKey: Boolean(event?.metaKey),
            shiftKey: Boolean(event?.shiftKey),
            altKey: Boolean(event?.altKey),
        };

        this.dispatchHeaderActionEvent(eventName, detail);
        if (eventName !== 'tanstack-table:row-action') {
            this.dispatchHeaderActionEvent('tanstack-table:row-action', detail);
        }
    }

    findRowById(rowId) {
        if (!rowId) {
            return null;
        }

        const fromTable = this.table?.getRowModel?.().rows?.find((row) => String(row.id) === rowId);
        if (fromTable) {
            return this.cloneRowData(fromTable);
        }

        const fromRows = (this.rows ?? []).find((row) => String(row?.id ?? '') === rowId);
        return fromRows ? { ...fromRows } : null;
    }

    rowActionIcon(name) {
        const paths = {
            'lucide:pencil':
                '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
            'lucide:trash-2':
                '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
            'lucide:eye':
                '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
            'lucide:copy':
                '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
            'lucide:panel-top':
                '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/>',
            'lucide:x': '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
            'lucide:maximize-2':
                '<path d="M15 3h6v6"/><path d="m21 3-7 7"/><path d="m3 21 7-7"/><path d="M9 21H3v-6"/>',
            'lucide:minimize-2':
                '<path d="m14 10 7-7"/><path d="M20 10V4h-6"/><path d="m3 21 7-7"/><path d="M4 14v6h6"/>',
        };
        const body = paths[name];
        if (!body) {
            return '';
        }

        return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true">${body}</svg>`;
    }

    /** Panel icon with a notification-style badge for the workspace tab number. */
    openTabPanelIcon(slotIndex) {
        const n = Number(slotIndex);
        const label = Number.isFinite(n) && n > 0 ? String(Math.trunc(n)) : '';
        const svg = this.rowActionIcon('lucide:panel-top');
        if (!label) {
            return svg;
        }

        return `<span data-slot="open-tab-icon" class="relative inline-flex shrink-0">${svg}<span data-slot="open-tab-badge" class="pointer-events-none absolute -top-1.5 -end-1.5 z-10 inline-flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-primary px-0.5 text-[9px] font-bold leading-none text-primary-foreground shadow-sm">${this.escapeHtml(label)}</span></span>`;
    }

    badgeClass(variant) {
        const base =
            'inline-flex h-5 w-fit shrink-0 items-center justify-center rounded-4xl border border-transparent px-2 py-0.5 text-xs font-medium whitespace-nowrap';
        const variants = {
            default: 'bg-primary text-primary-foreground',
            secondary: 'bg-secondary text-secondary-foreground',
            outline: 'border-border text-foreground',
            destructive: 'bg-destructive/10 text-destructive',
        };
        return `${base} ${variants[variant] ?? variants.secondary}`;
    }

    buttonClass(variant, size) {
        const base =
            "group/button inline-flex shrink-0 cursor-pointer items-center justify-center rounded-lg border border-transparent bg-clip-padding text-sm font-medium whitespace-nowrap transition-all outline-none select-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 active:not-aria-[haspopup]:translate-y-px disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4";
        const variants = {
            default: 'bg-primary text-primary-foreground hover:bg-primary/80',
            outline:
                'border-border bg-background hover:bg-muted hover:text-foreground dark:border-input dark:bg-input/30 dark:hover:bg-input/50',
            secondary:
                'bg-secondary text-secondary-foreground hover:bg-[color-mix(in_oklch,var(--secondary),var(--foreground)_5%)]',
            ghost: 'hover:bg-muted hover:text-foreground dark:hover:bg-muted/50',
            destructive:
                'bg-destructive/10 text-destructive hover:bg-destructive/20 focus-visible:border-destructive/40 focus-visible:ring-destructive/20',
            link: 'text-primary underline-offset-4 hover:underline',
        };
        const sizes = {
            default: 'h-8 gap-1.5 px-2.5',
            xs: 'h-6 gap-1 rounded-[min(var(--radius-md),10px)] px-2 text-xs [&_svg:not([class*=\'size-\'])]:size-3',
            sm: 'h-7 gap-1 rounded-[min(var(--radius-md),12px)] px-2.5 text-[0.8rem] [&_svg:not([class*=\'size-\'])]:size-3.5',
            lg: 'h-9 gap-1.5 px-2.5',
            icon: 'size-8',
            'icon-xs': 'size-6 rounded-[min(var(--radius-md),10px)] [&_svg:not([class*=\'size-\'])]:size-3',
            'icon-sm': 'size-7 rounded-[min(var(--radius-md),12px)]',
            'icon-lg': 'size-9',
        };

        return `${base} ${variants[variant] ?? variants.outline} ${sizes[size] ?? sizes['icon-sm']}`;
    }

    render() {
        if (!this.table) {
            return;
        }

        this.renderTable();
        this.renderPagination();
        this.syncColumnVisibilityMenu();
        this.syncBulkSelectionUi();
        this.syncOpenTabButtons();
    }

    _onWorkspaceTabOpened(event) {
        if (!this.hasWorkspaceTabs) {
            return;
        }
        const url = String(event.detail?.url ?? event.detail?.tabId ?? '').trim();
        if (!url) {
            return;
        }
        const slotIndex = Number(event.detail?.slotIndex);
        this.openTabSlotsByUrl.set(
            url,
            Number.isFinite(slotIndex) && slotIndex > 0 ? Math.trunc(slotIndex) : 0
        );
        this.syncOpenTabButtons();
    }

    _onWorkspaceTabClosed(event) {
        if (!this.hasWorkspaceTabs) {
            return;
        }
        const url = String(event.detail?.url ?? event.detail?.tabId ?? '').trim();
        if (!url) {
            return;
        }
        this.openTabSlotsByUrl.delete(url);
        this.syncOpenTabButtons();
    }

    syncOpenTabButtons() {
        if (!this.hasWorkspaceTabs) {
            return;
        }

        this.element.querySelectorAll('[data-workspace-tab="true"]').forEach((button) => {
            const url = String(button.dataset.url ?? '').trim();
            const slotIndex = url ? this.openTabSlotsByUrl.get(url) : undefined;
            this.applyWorkspaceTabState(button, slotIndex !== undefined, slotIndex);
        });
    }

    applyWorkspaceTabState(button, isOpen, slotIndex = 0) {
        const size = button.dataset.size || 'icon-sm';
        const showLabel = !String(size).startsWith('icon');
        const closedLabel = String(
            button.dataset.tabClosedLabel ?? button.dataset.tabLabel ?? this.t('Open')
        );
        const closedIcon = String(button.dataset.tabClosedIcon ?? 'lucide:panel-top');

        if (isOpen) {
            const tabNo = Number(slotIndex) > 0 ? Math.trunc(Number(slotIndex)) : '';
            button.dataset.tabState = 'open';
            button.dataset.slotIndex = tabNo ? String(tabNo) : '';
            button.dataset.variant = button.dataset.variant || 'outline';
            const aria = tabNo
                ? this.t('Open tab %number%', { '%number%': tabNo })
                : this.t('Open tab');
            button.setAttribute('aria-label', aria);
            button.title = aria;
            button.classList.add('relative');
            const icon = this.openTabPanelIcon(tabNo);
            button.innerHTML = showLabel ? `${icon}<span>${this.escapeHtml(aria)}</span>` : icon;
            return;
        }

        delete button.dataset.slotIndex;
        button.classList.remove('relative');
        button.dataset.tabState = 'closed';
        button.setAttribute('aria-label', closedLabel);
        button.title = closedLabel;
        const icon = this.rowActionIcon(closedIcon);
        button.innerHTML = showLabel
            ? `${icon}<span>${this.escapeHtml(closedLabel)}</span>`
            : icon || this.escapeHtml(closedLabel);
    }

    syncColumnVisibilityMenu() {
        if (!this.hasColumnToggleTarget) {
            return;
        }

        for (const item of this.columnToggleTargets) {
            const column = this.table.getColumn(item.dataset.columnId);
            if (!column) {
                continue;
            }

            const visible = column.getIsVisible();
            item.setAttribute('aria-checked', String(visible));
            item.dataset.disabled = column.getCanHide() ? 'false' : 'true';
            item.setAttribute('aria-disabled', column.getCanHide() ? 'false' : 'true');
            if (!column.getCanHide()) {
                item.setAttribute('disabled', '');
            } else {
                item.removeAttribute('disabled');
            }

            const indicator = item.querySelector('[data-checkbox-indicator]');
            if (indicator) {
                indicator.hidden = !visible;
            }
        }
    }

    renderTable() {
        const tableEl = document.createElement('table');
        tableEl.className = 'w-full caption-bottom text-sm';
        tableEl.dataset.slot = 'tstdt_table';

        const thead = document.createElement('thead');
        thead.className = '[&_tr]:border-b';
        thead.dataset.slot = 'tstdt_table-header';

        this.table.getHeaderGroups().forEach((headerGroup) => {
            const tr = document.createElement('tr');
            tr.className = HEADER_ROW_CLASS;
            tr.dataset.slot = 'tstdt_table-row';

            if (this.bulkSelectionEnabled) {
                const selectTh = document.createElement('th');
                selectTh.dataset.slot = 'tstdt_table-head';
                selectTh.className = `${HEAD_CLASS} w-[35px] min-w-[35px] max-w-[35px] text-center align-middle`;
                selectTh.appendChild(
                    this.createSelectionCheckbox({
                        checked: this.table.getIsAllPageRowsSelected?.() ?? false,
                        indeterminate:
                            (this.table.getIsSomePageRowsSelected?.() ?? false) &&
                            !(this.table.getIsAllPageRowsSelected?.() ?? false),
                        ariaLabel: this.t('Select all'),
                        onChange: (event) => {
                            const checked = event.currentTarget.checked;
                            this.table.toggleAllPageRowsSelected?.(checked);
                            for (const pageRow of this.table.getRowModel().rows) {
                                if (checked) {
                                    this.rememberSelectedRow(pageRow);
                                } else {
                                    this.forgetSelectedRow(pageRow);
                                }
                            }
                        },
                    })
                );
                tr.appendChild(selectTh);
            }

            headerGroup.headers.forEach((header) => {
                const th = document.createElement('th');
                const meta = header.column.columnDef.meta ?? {};
                th.dataset.slot = 'tstdt_table-head';
                th.className = [
                    HEAD_CLASS,
                    this.alignClass(meta.align, { head: true }),
                    meta.headClass ?? '',
                ]
                    .filter(Boolean)
                    .join(' ');
                this.applyButtonsColumnWidth(th, meta);

                if (header.isPlaceholder) {
                    tr.appendChild(th);
                    return;
                }

                const canSort = header.column.getCanSort();
                const label = String(FlexRender({ header }) ?? '');
                const sorted = header.column.getIsSorted();

                if (canSort) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className =
                        'inline-flex items-center gap-1 hover:text-foreground focus-visible:outline-none';
                    button.innerHTML = `${this.escapeHtml(label)}${this.sortIndicator(sorted)}`;
                    button.addEventListener('click', (event) => {
                        header.column.getToggleSortingHandler()?.(event);
                        if (this.serverPagination) {
                            this.fetchRows({ resetPage: true });
                        }
                    });
                    th.appendChild(button);
                } else {
                    th.textContent = label;
                }

                tr.appendChild(th);
            });

            thead.appendChild(tr);
        });

        const tbody = document.createElement('tbody');
        tbody.className = '[&_tr:last-child]:border-0';
        tbody.dataset.slot = 'tstdt_table-body';

        const visibleColumns = this.columnVisibilityEnabled
            ? this.table.getVisibleLeafColumns()
            : this.table.getAllLeafColumns();
        const visibleColumnCount =
            Math.max(visibleColumns.length, 1) + (this.bulkSelectionEnabled ? 1 : 0);
        const rows = this.table.getRowModel().rows;
        if (rows.length === 0) {
            const tr = document.createElement('tr');
            tr.className = ROW_CLASS;
            const td = document.createElement('td');
            td.dataset.slot = 'tstdt_table-cell';
            td.className = `${CELL_CLASS} text-center text-muted-foreground`;
            td.colSpan = visibleColumnCount;
            td.textContent = this.emptyMessage;
            tr.appendChild(td);
            tbody.appendChild(tr);
        } else {
            rows.forEach((row) => {
                const tr = document.createElement('tr');
                const selected = this.bulkSelectionEnabled && (row.getIsSelected?.() ?? false);
                tr.className = ROW_CLASS;
                tr.dataset.slot = 'tstdt_table-row';
                if (selected) {
                    tr.dataset.state = 'selected';
                }

                if (this.bulkSelectionEnabled) {
                    const selectTd = document.createElement('td');
                    selectTd.dataset.slot = 'tstdt_table-cell';
                    selectTd.className = SELECT_CELL_CLASS;
                    selectTd.appendChild(
                        this.createSelectionCheckbox({
                            checked: selected,
                            ariaLabel: this.t('Select row'),
                            onChange: (event) => {
                                const checked = event.currentTarget.checked;
                                row.toggleSelected?.(checked);
                                if (checked) {
                                    this.rememberSelectedRow(row);
                                } else {
                                    this.forgetSelectedRow(row);
                                }
                            },
                        })
                    );
                    tr.appendChild(selectTd);
                }

                const cells = this.columnVisibilityEnabled ? row.getVisibleCells() : row.getAllCells();
                cells.forEach((cell) => {
                    const td = document.createElement('td');
                    const meta = cell.column.columnDef.meta ?? {};
                    td.dataset.slot = 'tstdt_table-cell';
                    td.className = [
                        CELL_CLASS,
                        this.alignClass(meta.align),
                        meta.class ?? '',
                    ]
                        .filter(Boolean)
                        .join(' ');
                    this.applyButtonsColumnWidth(td, meta);
                    td.innerHTML = String(FlexRender({ cell }) ?? '');
                    tr.appendChild(td);
                });

                tbody.appendChild(tr);
            });
        }

        tableEl.append(thead, tbody);
        this.tableTarget.replaceChildren(tableEl);
    }

    renderPagination() {
        const { pageIndex } = this.table.store.state.pagination;
        const pageCount = this.table.getPageCount();
        const total = this.serverPagination
            ? this.totalRows
            : this.table.getFilteredRowModel().rows.length;
        const currentPage = pageCount === 0 ? 0 : pageIndex + 1;

        if (this.hasPageInputTarget && document.activeElement !== this.pageInputTarget) {
            this.pageInputTarget.value = String(currentPage);
            this.pageInputTarget.max = String(Math.max(pageCount, 1));
        }

        if (this.hasPageCountTarget) {
            this.pageCountTarget.textContent = String(Math.max(pageCount, 1));
        }

        if (this.hasTotalCountTarget) {
            this.totalCountTarget.textContent = total.toLocaleString();
        }

        if (this.hasFirstTarget) {
            this.firstTarget.disabled = !this.table.getCanPreviousPage();
        }

        if (this.hasPreviousTarget) {
            this.previousTarget.disabled = !this.table.getCanPreviousPage();
        }

        if (this.hasNextTarget) {
            this.nextTarget.disabled = !this.table.getCanNextPage();
        }

        if (this.hasLastTarget) {
            this.lastTarget.disabled = !this.table.getCanNextPage();
        }
    }

    sortIndicator(sorted) {
        if (sorted === 'asc') {
            return ' <span aria-hidden="true">↑</span>';
        }
        if (sorted === 'desc') {
            return ' <span aria-hidden="true">↓</span>';
        }
        return ' <span class="text-muted-foreground/60" aria-hidden="true">↕</span>';
    }

    titleCase(value) {
        return value.replace(/(^|_|-)(\w)/g, (_, __, char) => ` ${char.toUpperCase()}`).trim();
    }

    escapeHtml(value) {
        return value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    escapeAttr(value) {
        return this.escapeHtml(String(value));
    }
}
