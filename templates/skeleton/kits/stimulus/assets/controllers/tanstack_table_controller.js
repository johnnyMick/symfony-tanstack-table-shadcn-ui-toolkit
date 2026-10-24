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

function operatorFilter(row, columnId, filterValue) {
    const raw = filterValue && typeof filterValue === 'object' ? filterValue : { query: filterValue };
    const query = String(raw.query ?? '').trim().toLowerCase();
    if (!query) {
        return true;
    }

    const value = String(row.getValue(columnId) ?? '').toLowerCase();
    switch (raw.operator ?? DEFAULT_FILTER_OPERATOR) {
        case 'startsWith':
            return value.startsWith(query);
        case 'endsWith':
            return value.endsWith(query);
        case 'equals':
            return value === query;
        case 'contains':
        default:
            return value.includes(query);
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
        loading: { type: Boolean, default: false },
    };

    connect() {
        this.config = this.parseConfig();
        this.applyColors();
        this.rows = Array.isArray(this.config.data) ? this.config.data : [];
        this.columnDefs = this.buildColumns(this.config.columns ?? []);
        this.filterQuery = '';
        this.filterColumn = this.defaultFilterColumnId();
        this.filterOperator = DEFAULT_FILTER_OPERATOR;
        this.selectedBulkActionId = '';
        this.selectedRowsById = new Map();
        this.abortController = null;
        this.totalRows = this.rows.length;
        this.serverPagination = this.hasAjaxUrl;

        const features = {
            coreReactivityFeature: storeReactivityBindings(),
            rowSortingFeature,
            sortedRowModel: createSortedRowModel(),
            sortFns,
            globalFilteringFeature,
            columnFilteringFeature,
            filteredRowModel: createFilteredRowModel(),
            filterFns: {
                ...filterFns,
                operatorFilter,
            },
            rowPaginationFeature,
            // Client-side slicing only when not loading pages via AJAX.
            ...(this.serverPagination ? {} : { paginatedRowModel: createPaginatedRowModel() }),
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
            rowCount: this.totalRows,
            enableRowSelection: this.bulkSelectionEnabled,
            initialState: {
                pagination: {
                    pageIndex: 0,
                    pageSize: this.pageSize,
                },
                columnVisibility: {},
                rowSelection: {},
            },
            getRowId: (row, index) => String(row.id ?? index),
        });

        this.subscription = this.table.store.subscribe(() => this.render());
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

    get pageSize() {
        const value = Number(this.config?.pageSize);
        return Number.isFinite(value) && value > 0 ? value : 25;
    }

    get emptyMessage() {
        return this.config?.emptyMessage || 'No rows found.';
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
                open ? 'Hide search' : 'Show search'
            );
            toggle.title = open ? 'Hide search' : 'Search';
            toggle.dataset.state = state;
        }

        if (open && this.hasFilterTarget) {
            requestAnimationFrame(() => this.filterTarget.focus());
        }
    }

    applyFilter(event) {
        event?.preventDefault?.();

        if (this.hasFilterTarget) {
            this.filterQuery = this.filterTarget.value ?? '';
        }

        if (!this.table) {
            return;
        }

        if (this.serverPagination) {
            // Server applies filter — keep client filters clear so we don't double-filter the page.
            this.table.setGlobalFilter('');
            this.table.setColumnFilters([]);
            this.fetchRows({ resetPage: true });
            return;
        }

        const query = this.filterQuery.trim();
        const columnId = this.filterColumn || this.defaultFilterColumnId();

        this.table.setGlobalFilter('');
        this.table.setColumnFilters(
            query && columnId
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

    changeFilterField(event) {
        this.filterColumn = event.detail?.value || this.defaultFilterColumnId();
    }

    changeFilterOperator(event) {
        this.filterOperator = event.detail?.value || DEFAULT_FILTER_OPERATOR;
    }

    defaultFilterColumnId() {
        const columns = this.config?.columns ?? this.columnDefs ?? [];
        for (const column of columns) {
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

        if (!visible && this.filterColumn === columnId) {
            const next = this.table
                .getVisibleLeafColumns()
                .map((col) => col.id)
                .find((id) => id && id !== columnId);
            this.filterColumn = next ?? this.defaultFilterColumnId();
            this.applyFilter();
        }
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

        const filter = (this.filterQuery ?? '').trim();
        const filterField = this.filterColumn || this.defaultFilterColumnId();
        if (filter && filterField) {
            url.searchParams.set('filter', filter);
            url.searchParams.set('filterField', filterField);
            url.searchParams.set(
                'filterOperator',
                this.filterOperator || DEFAULT_FILTER_OPERATOR
            );
        }

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
            this.searchSubmitTarget.disabled = loading;
            const idle = this.searchSubmitTarget.querySelector('[data-search-submit-idle]');
            const spinning = this.searchSubmitTarget.querySelector('[data-search-submit-loading]');
            if (idle) {
                idle.hidden = loading;
            }
            if (spinning) {
                spinning.hidden = !loading;
            }
        }
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

    /** @deprecated Prefer headerAction + data-header-action="add-row". Kept for BC. */
    addRow(event) {
        event?.preventDefault?.();
        const button = event?.currentTarget;
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
        const eventName = action?.event || `tanstack-table:bulk-${actionId}`;
        const rowIds = rows.map((row) => String(row?.id ?? ''));

        const detail = {
            action: actionId,
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
                    selectedCount === 1 ? '1 selected' : `${selectedCount} selected`;
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
                header: definition.header ?? definition.accessorKey ?? definition.id,
                enableSorting: definition.enableSorting !== false,
                // Hideable by default whenever column visibility is enabled.
                enableHiding:
                    this.columnVisibilityEnabled &&
                    definition.enableHiding !== false &&
                    meta.hideable !== false,
                filterFn: 'operatorFilter',
                meta,
                cell: (info) => this.formatCell(info.getValue(), meta, info.row),
            };

            if (definition.accessorKey) {
                column.accessorKey = definition.accessorKey;
            } else {
                column.accessorFn = (row) => row[definition.id];
            }

            return column;
        });
    }

    formatCell(value, meta, row = null) {
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

    formatButtonsCell(buttons, row) {
        if (!Array.isArray(buttons) || buttons.length === 0) {
            return '';
        }

        const rowId = this.escapeAttr(String(row?.id ?? row?.original?.id ?? ''));
        const parts = buttons.map((button) => {
            const id = String(button?.id ?? '');
            const label = String(button?.label ?? id);
            const variant = button?.variant ?? 'outline';
            const size = button?.size ?? 'icon-sm';
            const eventName = button?.event || (id ? `tanstack-table:row-${id}` : 'tanstack-table:row-action');
            const icon = button?.icon ? this.rowActionIcon(button.icon) : '';
            const showLabel = !String(size).startsWith('icon');
            const content = icon
                ? `${icon}${showLabel ? `<span>${this.escapeHtml(label)}</span>` : ''}`
                : this.escapeHtml(label);

            return `<button
                type="button"
                data-slot="button"
                data-variant="${this.escapeAttr(variant)}"
                data-size="${this.escapeAttr(size)}"
                data-row-action="${this.escapeAttr(id)}"
                data-row-action-event="${this.escapeAttr(eventName)}"
                data-row-id="${rowId}"
                data-action="click->tanstack-table#rowAction"
                class="${this.buttonClass(variant, size)}"
                aria-label="${this.escapeAttr(label)}"
                title="${this.escapeAttr(label)}"
            >${content}</button>`;
        });

        return `<div class="inline-flex items-center justify-end gap-1">${parts.join('')}</div>`;
    }

    /**
     * Row action click handler. Dispatches a customizable DOM event
     * (see data-row-action-event) with the full row object.
     */
    rowAction(event) {
        event?.preventDefault?.();
        event?.stopPropagation?.();
        const button = event?.currentTarget;
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
        };
        const body = paths[name];
        if (!body) {
            return '';
        }

        return `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true">${body}</svg>`;
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
            "group/button inline-flex shrink-0 items-center justify-center rounded-lg border border-transparent bg-clip-padding text-sm font-medium whitespace-nowrap transition-all outline-none select-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 active:not-aria-[haspopup]:translate-y-px disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4";
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
                        ariaLabel: 'Select all',
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
                    meta.align === 'end' ? 'text-end' : '',
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
                            ariaLabel: 'Select row',
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
                        meta.align === 'end' ? 'text-end tabular-nums' : '',
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
