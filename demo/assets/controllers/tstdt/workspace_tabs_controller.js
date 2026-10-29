import { Controller } from '@hotwired/stimulus';

/**
 * Fixed workspace tabs: 1 pinned main slot + N placeholder content slots (level 1),
 * plus a second-level strip whose main tab mirrors the active level-1 tab.
 *
 * Nested (level-2) slot count is independent of level 1. Open nested tabs with:
 *
 *   document.dispatchEvent(new CustomEvent('workspace-tabs:open', {
 *     detail: { url: '/items/42/related', label: 'Related', nested: true },
 *   }));
 *
 * Or mark a button/link in tab content:
 *   <button type="button" data-workspace-nested-tab data-url="..." data-label="...">
 *
 * Tabs are identified by URL. Level-1 open:
 *
 *   document.dispatchEvent(new CustomEvent('workspace-tabs:open', {
 *     detail: { url: '/items/42/edit', label: 'Item 42' },
 *   }));
 *
 * Focus / close by the same URL (either level):
 *   workspace-tabs:focus  { url }
 *   workspace-tabs:close  { url }
 *
 * Slot helpers (used by the command palette):
 *   workspace-tabs:activate   { slotId: 'main'|'slot-N' } or { slotIndex: N }
 *   workspace-tabs:close-slot { slotId } or { slotIndex: N }
 *   workspace-tabs:close-all
 *
 * - Filling uses the first empty slot; when full, a replace dialog appears.
 * - Ctrl/Cmd+click (or context-menu open) reserves a slot; content loads on first focus if empty.
 * - Switching tabs only shows/hides panels — open tab HTML is kept (no reload).
 */
export default class extends Controller {
    static targets = [
        'tablist',
        'panels',
        'mainTab',
        'mainPanel',
        'slotTab',
        'slotPanel',
        'nestedTablistWrap',
        'nestedTablist',
        'nestedMainTab',
        'nestedSlotTab',
        'headerTabStatus',
        'headerLoadedBadge',
        'headerRefresh',
        'replaceDialog',
        'replaceOptions',
    ];

    static values = {
        mainTabId: { type: String, default: 'main' },
        slotCount: { type: Number, default: 5 },
        /** When false, nested opens fall back to level-1 slots. */
        nestedTabs: { type: Boolean, default: true },
        /** Level-2 slot count (independent of {@code slotCount}; default 7). */
        nestedSlotCount: { type: Number, default: 7 },
        /** {@code title} = configured tabTitle; {@code url} = tab destination URL. */
        labelMode: { type: String, default: 'title' },
    };

    connect() {
        this.activeId = this.mainTabIdValue;
        this._pendingOpen = null;
        this._loading = new Set();
        /** @type {Map<string, AbortController>} */
        this._abortControllers = new Map();
        this._i18n = this.parseI18n();

        this._boundOpen = this.openFromEvent.bind(this);
        this._boundFocus = this.focusFromEvent.bind(this);
        this._boundClose = this.closeFromEvent.bind(this);
        this._boundActivate = this.activateFromEvent.bind(this);
        this._boundCloseAll = this.closeAllFromEvent.bind(this);
        this._boundCloseSlot = this.closeSlotFromEvent.bind(this);
        this._boundSubmit = this._onPanelSubmit.bind(this);
        this._boundNestedClick = this._onNestedTabTriggerClick.bind(this);
        this._boundDialogCancel = () => {
            this._pendingOpen = null;
        };
        this._boundDialogClick = (event) => {
            if (
                this.hasReplaceDialogTarget &&
                event.target === this.replaceDialogTarget
            ) {
                this.cancelReplace();
            }
        };

        document.addEventListener('workspace-tabs:open', this._boundOpen);
        document.addEventListener('workspace-tabs:focus', this._boundFocus);
        document.addEventListener('workspace-tabs:close', this._boundClose);
        document.addEventListener('workspace-tabs:activate', this._boundActivate);
        document.addEventListener('workspace-tabs:close-all', this._boundCloseAll);
        document.addEventListener('workspace-tabs:close-slot', this._boundCloseSlot);
        this.element.addEventListener('submit', this._boundSubmit);
        this.element.addEventListener('click', this._boundNestedClick);
        if (this.hasReplaceDialogTarget) {
            this.replaceDialogTarget.addEventListener('cancel', this._boundDialogCancel);
            this.replaceDialogTarget.addEventListener('click', this._boundDialogClick);
        }

        const stored = this._readStoredLabelMode();
        if (stored && stored !== this.labelModeValue) {
            this.labelModeValue = stored;
        }
        this._syncLabelModeControls();
        this._syncPlaceholders();
        // Main nested-main loads via AJAX when data-open-url is set (same path as content tabs).
        this.activate(this.mainTabIdValue);
        this._startLoadedAgoTicker();
    }

    disconnect() {
        this._stopLoadedAgoTicker();
        document.removeEventListener('workspace-tabs:open', this._boundOpen);
        document.removeEventListener('workspace-tabs:focus', this._boundFocus);
        document.removeEventListener('workspace-tabs:close', this._boundClose);
        document.removeEventListener('workspace-tabs:activate', this._boundActivate);
        document.removeEventListener('workspace-tabs:close-all', this._boundCloseAll);
        document.removeEventListener('workspace-tabs:close-slot', this._boundCloseSlot);
        this.element.removeEventListener('submit', this._boundSubmit);
        this.element.removeEventListener('click', this._boundNestedClick);
        if (this.hasReplaceDialogTarget) {
            this.replaceDialogTarget.removeEventListener('cancel', this._boundDialogCancel);
            this.replaceDialogTarget.removeEventListener('click', this._boundDialogClick);
        }
        for (const key of [...this._abortControllers.keys()]) {
            this._cancelLoad(key);
        }
    }

    /** Open: detail requires {@code url} (used as the unique tab id). */
    openFromEvent(event) {
        this._openFromDetail(event.detail ?? {});
    }

    /** Focus an already-open tab by {@code url} (level 1 or nested). */
    focusFromEvent(event) {
        const url = this._resolveUrl(event.detail ?? {});
        if (!url) {
            return;
        }
        const nested = this._findNestedSlotByUrl(url);
        if (nested) {
            if (this.activeId !== nested.parentId) {
                this.activate(nested.parentId);
            }
            this.activateNested(nested.nestedId, nested.parentId);
            return;
        }
        const slot = this._findSlotByUrl(url);
        if (slot) {
            this.activate(slot.dataset.tabId);
        }
    }

    /** Close the slot holding {@code url} (level 1 or nested). */
    closeFromEvent(event) {
        const url = this._resolveUrl(event.detail ?? {});
        if (!url) {
            return;
        }
        const nested = this._findNestedSlotByUrl(url);
        if (nested) {
            this.resetNestedSlot(nested.nestedId, nested.parentId);
            return;
        }
        const slot = this._findSlotByUrl(url);
        if (slot) {
            this.resetSlot(slot.dataset.tabId);
        }
    }

    /**
     * Activate a tab by slot id: {@code main} or {@code slot-N}.
     * Detail: {@code { slotId }} or {@code { slotIndex }} (1-based for content slots).
     */
    activateFromEvent(event) {
        const detail = event.detail ?? {};
        let slotId = String(detail.slotId ?? '').trim();
        if (!slotId && detail.slotIndex != null) {
            const index = Number(detail.slotIndex);
            if (Number.isFinite(index) && index >= 1) {
                slotId = `slot-${Math.trunc(index)}`;
            }
        }
        if (!slotId) {
            return;
        }
        this.activate(slotId);
    }

    /** Close every filled content slot (main stays); clears nested under them. */
    closeAllFromEvent() {
        this.closeAllSlots();
    }

    /** Close a content slot by {@code slotId} or 1-based {@code slotIndex}. */
    closeSlotFromEvent(event) {
        const detail = event.detail ?? {};
        let slotId = String(detail.slotId ?? '').trim();
        if (!slotId && detail.slotIndex != null) {
            const index = Number(detail.slotIndex);
            if (Number.isFinite(index) && index >= 1) {
                slotId = `slot-${Math.trunc(index)}`;
            }
        }
        if (!slotId || slotId === this.mainTabIdValue) {
            return;
        }
        this.resetSlot(slotId);
    }

    closeAllSlots() {
        for (const tab of this.slotTabTargets) {
            if (tab.dataset.filled === 'true') {
                this.resetSlot(tab.dataset.tabId);
            }
        }
        this._clearNestedSlots(this.mainTabIdValue);
        this._syncNestedStrip();
    }

    selectTab(event) {
        const tab = event.currentTarget;
        const slotId = tab?.dataset?.tabId;
        if (!slotId || tab.dataset.filled === 'false') {
            return;
        }
        // Allow selecting/copying the label without activating the tab.
        if (this._selectionWithinTabLabel(tab)) {
            return;
        }
        this.activate(slotId);
    }

    selectNestedTab(event) {
        const tab = event.currentTarget;
        const nestedId = tab?.dataset?.nestedTabId;
        if (!nestedId || tab.dataset.filled === 'false') {
            return;
        }
        if (this._selectionWithinNestedTabLabel(tab)) {
            return;
        }
        this.activateNested(nestedId, this.activeId);
    }

    _selectionWithinTabLabel(tab) {
        const selection = window.getSelection?.();
        if (!selection || selection.isCollapsed || selection.rangeCount === 0) {
            return false;
        }
        const label = tab?.querySelector?.('[data-slot="workspace-tab-label"]');
        if (!label) {
            return false;
        }
        const range = selection.getRangeAt(0);
        return label.contains(range.commonAncestorContainer);
    }

    _selectionWithinNestedTabLabel(tab) {
        const selection = window.getSelection?.();
        if (!selection || selection.isCollapsed || selection.rangeCount === 0) {
            return false;
        }
        const label = tab?.querySelector?.('[data-slot="workspace-nested-tab-label"]');
        if (!label) {
            return false;
        }
        const range = selection.getRangeAt(0);
        return label.contains(range.commonAncestorContainer);
    }

    closeTab(event) {
        event.preventDefault();
        event.stopPropagation();
        const slotId = event.currentTarget?.dataset?.tabId;
        if (!slotId || slotId === this.mainTabIdValue) {
            return;
        }
        this.resetSlot(slotId);
    }

    closeNestedTab(event) {
        event.preventDefault();
        event.stopPropagation();
        const nestedId = event.currentTarget?.dataset?.nestedTabId;
        if (!nestedId || nestedId === 'nested-main') {
            return;
        }
        this.resetNestedSlot(nestedId, this.activeId);
    }

    chooseReplaceSlot(event) {
        const slotId = event.currentTarget?.dataset?.tabId;
        const nestedId = event.currentTarget?.dataset?.nestedTabId;
        if (!this._pendingOpen) {
            return;
        }
        const pending = this._pendingOpen;
        this._pendingOpen = null;
        this._closeReplaceDialog();

        if (pending.nested && nestedId) {
            this.fillNestedSlot(nestedId, pending.parentId, pending);
            return;
        }
        if (slotId) {
            this.fillSlot(slotId, pending);
        }
    }

    cancelReplace() {
        this._pendingOpen = null;
        this._closeReplaceDialog();
    }

    /** Switch tab labels between configured title and URL ({@code data-label-mode}). */
    setLabelMode(event) {
        const mode = String(event.currentTarget?.dataset?.labelMode ?? '').trim();
        if (mode !== 'title' && mode !== 'url') {
            return;
        }
        this._setLabelMode(mode);
    }

    /** Flip between title and URL label modes. */
    toggleLabelMode() {
        this._setLabelMode(this.labelModeValue === 'url' ? 'title' : 'url');
    }

    /** Programmatic label mode: {@code 'title'} or {@code 'url'}. */
    applyLabelMode(mode) {
        this._setLabelMode(mode);
    }

    labelModeValueChanged() {
        const mode = this.labelModeValue === 'url' ? 'url' : 'title';
        if (this.labelModeValue !== mode) {
            this.labelModeValue = mode;
            return;
        }
        this._syncLabelModeControls();
        this._syncFilledLabels();
        this._syncNestedStrip();
    }

    _setLabelMode(mode) {
        const next = mode === 'url' ? 'url' : 'title';
        this._writeStoredLabelMode(next);
        this.labelModeValue = next;
    }

    /**
     * @param {object} detail
     * @param {string} [detail.url]
     * @param {string} [detail.label]
     * @param {boolean} [detail.nested]
     * @param {boolean} [detail.forceActivate]
     * @param {boolean} [detail.ctrlKey]
     * @param {boolean} [detail.metaKey]
     * @param {boolean} [detail.fromContextMenu]
     * @param {HTMLElement} [detail.button]
     */
    _openFromDetail(detail) {
        const url = this._resolveUrl(detail);
        if (!url) {
            return;
        }

        const tabId = url;
        const forceActivate = Boolean(detail.forceActivate) && !detail.fromContextMenu;
        const background =
            Boolean(detail.ctrlKey || detail.metaKey || detail.fromContextMenu) && !forceActivate;
        const label = detail.label ?? url;
        // Nested strip is only shown on filled level-1 content tabs (not main).
        const nested =
            this.nestedTabsValue &&
            this.activeId !== this.mainTabIdValue &&
            this._wantsNested(detail);

        if (nested) {
            this._openNestedFromDetail({
                tabId,
                label: String(label),
                url,
                activate: !background,
            });
            return;
        }

        const existingNested = this._findNestedSlotByUrl(url);
        if (existingNested) {
            if (!background) {
                if (this.activeId !== existingNested.parentId) {
                    this.activate(existingNested.parentId);
                }
                this.activateNested(existingNested.nestedId, existingNested.parentId);
            }
            this._emitTabOpened(tabId, existingNested.parentId, label, url, {
                nested: true,
                nestedId: existingNested.nestedId,
            });
            return;
        }

        const existing = this._findSlotByUrl(url);
        if (existing) {
            if (!background) {
                this.activate(existing.dataset.tabId);
            }
            this._emitTabOpened(tabId, existing.dataset.tabId, label, url);
            return;
        }

        const empty = this._findEmptySlot();
        if (empty) {
            this.fillSlot(empty.dataset.tabId, {
                tabId,
                label: String(label),
                url,
                activate: !background,
            });
            return;
        }

        this._pendingOpen = {
            tabId,
            label: String(label),
            url,
            activate: !background,
            nested: false,
        };
        this._openReplaceDialog();
    }

    _openNestedFromDetail({ tabId, label, url, activate = true }) {
        const parentId = this.activeId;
        const parentPanel = this._findPanel(parentId);
        if (!parentPanel || (parentId !== this.mainTabIdValue && parentPanel.dataset.filled !== 'true')) {
            return;
        }

        const existing = this._findNestedSlotByUrl(url, parentId);
        if (existing) {
            if (activate) {
                this.activateNested(existing.nestedId, parentId);
            }
            this._emitTabOpened(tabId, parentId, label, url, {
                nested: true,
                nestedId: existing.nestedId,
            });
            return;
        }

        const empty = this._findEmptyNestedSlot(parentId);
        if (empty) {
            this.fillNestedSlot(empty.dataset.nestedTabId, parentId, {
                tabId,
                label,
                url,
                activate,
            });
            return;
        }

        this._pendingOpen = {
            tabId,
            label,
            url,
            activate,
            nested: true,
            parentId,
        };
        this._openReplaceDialog({ nested: true, parentId });
    }

    _wantsNested(detail) {
        if (detail.nested === true || detail.level === 2) {
            return true;
        }
        const button = detail.button;
        if (!button) {
            return false;
        }
        if (button.dataset?.workspaceNested === 'true' || button.hasAttribute?.('data-workspace-nested-tab')) {
            return true;
        }
        return Boolean(button.closest?.('[data-workspace-nested-tab]'));
    }

    _onNestedTabTriggerClick(event) {
        const trigger = event.target?.closest?.('[data-workspace-nested-tab]');
        if (!trigger || !this.element.contains(trigger)) {
            return;
        }
        if (trigger.closest('[data-slot="workspace-tab"], [data-slot="workspace-nested-tab"]')) {
            return;
        }

        const url =
            String(trigger.dataset?.url ?? trigger.getAttribute?.('href') ?? '').trim() || null;
        if (!url || url.startsWith('#')) {
            return;
        }

        event.preventDefault();
        const label =
            String(trigger.dataset?.label ?? trigger.dataset?.tabLabel ?? '').trim() ||
            String(trigger.textContent ?? '').trim() ||
            url;

        this._openFromDetail({
            url,
            label,
            nested: true,
            button: trigger,
            ctrlKey: Boolean(event.ctrlKey),
            metaKey: Boolean(event.metaKey),
            forceActivate: false,
            fromContextMenu: false,
        });
    }

    _resolveUrl(detail) {
        const fromDetail = detail.url ?? null;
        if (fromDetail) {
            return String(fromDetail).trim() || null;
        }
        const fromButton = detail.button?.dataset?.url ?? detail.button?.getAttribute?.('data-url');
        if (fromButton) {
            return String(fromButton).trim() || null;
        }
        return null;
    }

    fillSlot(slotId, { tabId, label, url, activate = true }) {
        const tab = this._findTab(slotId);
        const panel = this._findPanel(slotId);
        const content = this._findNestedPanel(slotId, 'nested-main');
        if (!tab || !panel || !content) {
            return;
        }

        const previousUrl = tab.dataset.openTabId;
        if (previousUrl && previousUrl !== String(tabId)) {
            this._emitTabClosed(previousUrl, slotId);
        }

        // Replacing a level-1 tab clears its nested children.
        this._clearNestedSlots(slotId);
        this._unloadSlotContent(slotId);

        tab.dataset.filled = 'true';
        tab.dataset.openTabId = String(tabId);
        tab.dataset.openUrl = String(url);
        tab.dataset.openLabel = String(label);
        this._applyTabDisplay(tab);
        this._setCloseVisible(tab, true);

        panel.dataset.filled = 'true';
        panel.dataset.openTabId = String(tabId);
        panel.dataset.openUrl = String(url);
        panel.dataset.nestedActive = 'nested-main';

        content.dataset.filled = 'true';
        content.dataset.state = 'active';
        content.hidden = false;
        this._clearNestedContent(content);
        delete content.dataset.loadedAt;

        this._emitTabOpened(tabId, slotId, label, url);

        if (activate) {
            this.activate(slotId, { reload: true });
        } else if (this.activeId !== slotId) {
            tab.dataset.state = 'inactive';
            tab.setAttribute('aria-selected', 'false');
            tab.tabIndex = -1;
            panel.hidden = true;
            panel.dataset.state = 'inactive';
        }
    }

    fillNestedSlot(nestedId, parentId, { tabId, label, url, activate = true }) {
        const panel = this._findNestedPanel(parentId, nestedId);
        if (!panel || nestedId === 'nested-main') {
            return;
        }

        const previousUrl = panel.dataset.openTabId;
        if (previousUrl && previousUrl !== String(tabId)) {
            this._emitTabClosed(previousUrl, parentId, { nested: true, nestedId });
        }

        this._cancelLoad(this._loadKey(parentId, nestedId));
        panel.dataset.filled = 'true';
        panel.dataset.openTabId = String(tabId);
        panel.dataset.openUrl = String(url);
        panel.dataset.openLabel = String(label);
        this._clearNestedContent(panel);
        delete panel.dataset.loadedAt;

        this._emitTabOpened(tabId, parentId, label, url, { nested: true, nestedId });

        if (activate) {
            this.activateNested(nestedId, parentId, { reload: true });
        } else {
            panel.hidden = true;
            panel.dataset.state = 'inactive';
            if (parentId === this.activeId) {
                this._syncNestedStrip();
            }
        }
    }

    resetSlot(slotId) {
        const tab = this._findTab(slotId);
        const panel = this._findPanel(slotId);
        const content = this._findNestedPanel(slotId, 'nested-main');
        if (!tab || !panel) {
            return;
        }

        const wasActive = this.activeId === slotId;
        const index = tab.dataset.slotIndex || slotId.replace('slot-', '');
        const openTabId = tab.dataset.openTabId;

        this._clearNestedSlots(slotId);

        tab.dataset.filled = 'false';
        delete tab.dataset.openTabId;
        delete tab.dataset.openUrl;
        delete tab.dataset.openLabel;
        tab.title = '';
        tab.dataset.state = 'inactive';
        tab.setAttribute('aria-selected', 'false');
        tab.tabIndex = -1;
        this._setTabLabel(tab, this.tabPlaceholderLabel(index));
        this._setCloseVisible(tab, false);

        panel.dataset.filled = 'false';
        delete panel.dataset.openTabId;
        delete panel.dataset.openUrl;
        panel.dataset.state = 'inactive';
        panel.dataset.nestedActive = 'nested-main';
        panel.hidden = true;

        if (content) {
            content.dataset.filled = 'false';
            content.dataset.state = 'active';
            content.hidden = false;
            this._clearNestedContent(content);
            delete content.dataset.openTabId;
            delete content.dataset.openUrl;
            delete content.dataset.openLabel;
            delete content.dataset.loadedAt;
        }

        if (openTabId) {
            this._emitTabClosed(openTabId, slotId);
        }

        if (wasActive) {
            this.activate(this.mainTabIdValue);
        }
    }

    resetNestedSlot(nestedId, parentId) {
        if (!nestedId || nestedId === 'nested-main') {
            return;
        }
        const panel = this._findNestedPanel(parentId, nestedId);
        if (!panel) {
            return;
        }

        const parentPanel = this._findPanel(parentId);
        const wasActive = parentPanel?.dataset?.nestedActive === nestedId;
        const openTabId = panel.dataset.openTabId;
        const index = panel.dataset.slotIndex || nestedId.replace('nested-slot-', '');

        panel.dataset.filled = 'false';
        delete panel.dataset.openTabId;
        delete panel.dataset.openUrl;
        delete panel.dataset.openLabel;
        panel.dataset.state = 'inactive';
        panel.hidden = true;
        this._clearNestedContent(panel);
        delete panel.dataset.loadedAt;

        if (openTabId) {
            this._emitTabClosed(openTabId, parentId, { nested: true, nestedId });
        }

        if (wasActive && parentPanel) {
            parentPanel.dataset.nestedActive = 'nested-main';
            this.activateNested('nested-main', parentId);
        } else if (parentId === this.activeId) {
            this._syncNestedStrip();
        }

        // Keep placeholder label in sync when strip is showing this parent.
        if (parentId === this.activeId) {
            const stripTab = this._findNestedStripTab(nestedId);
            if (stripTab) {
                this._setNestedTabLabel(
                    stripTab,
                    this.nestedTabPlaceholderLabel(this._slotIndexForTabId(parentId), index)
                );
                this._setNestedCloseVisible(stripTab, false);
                stripTab.dataset.filled = 'false';
            }
        }
    }

    _clearNestedSlots(parentId) {
        const parentPanel = this._findPanel(parentId);
        if (!parentPanel) {
            return;
        }
        parentPanel
            .querySelectorAll('[data-slot="workspace-nested-panel"][data-nested-tab-id^="nested-slot-"]')
            .forEach((panel) => {
                const nestedId = panel.dataset.nestedTabId;
                const openTabId = panel.dataset.openTabId;
                if (openTabId) {
                    this._emitTabClosed(openTabId, parentId, { nested: true, nestedId });
                }
                panel.dataset.filled = 'false';
                delete panel.dataset.openTabId;
                delete panel.dataset.openUrl;
                delete panel.dataset.openLabel;
                panel.dataset.state = 'inactive';
                panel.hidden = true;
                this._clearNestedContent(panel);
                delete panel.dataset.loadedAt;
            });
        parentPanel.dataset.nestedActive = 'nested-main';
        const mainNested = this._findNestedPanel(parentId, 'nested-main');
        if (mainNested) {
            mainNested.hidden = false;
            mainNested.dataset.state = 'active';
        }
    }

    _emitTabOpened(tabId, slotId, label, url, extra = {}) {
        const nested = Boolean(extra.nested);
        this.element.dispatchEvent(
            new CustomEvent('workspace-tabs:opened', {
                bubbles: true,
                detail: {
                    tabId: String(tabId),
                    url: String(url),
                    slotId,
                    label,
                    slotIndex: this._slotIndexForEmit(slotId, extra),
                    tabLevel: nested ? 2 : 1,
                    nested,
                    nestedId: extra.nestedId ?? null,
                },
            })
        );
    }

    _emitTabClosed(tabId, slotId, extra = {}) {
        const nested = Boolean(extra.nested);
        this.element.dispatchEvent(
            new CustomEvent('workspace-tabs:closed', {
                bubbles: true,
                detail: {
                    tabId: String(tabId),
                    url: String(tabId),
                    slotId,
                    slotIndex: this._slotIndexForEmit(slotId, extra),
                    tabLevel: nested ? 2 : 1,
                    nested,
                    nestedId: extra.nestedId ?? null,
                },
            })
        );
    }

    /**
     * Badge index for datatable open-tab icons.
     * - Level-1 content tabs: 1-based level-1 slot index
     * - Level-2 nested slots: 1-based nested position
     * - Level-2 main (nested-main / parent content): 0
     */
    _slotIndexForEmit(slotId, extra = {}) {
        if (extra.nested && extra.nestedId) {
            if (extra.nestedId === 'nested-main') {
                return 0;
            }
            const nestedPanel = this._findNestedPanel(slotId, extra.nestedId);
            const fromPanel = Number(nestedPanel?.dataset?.slotIndex);
            if (Number.isFinite(fromPanel) && fromPanel > 0) {
                return Math.trunc(fromPanel);
            }
            const fromId = Number(String(extra.nestedId).replace(/^nested-slot-/, ''));
            if (Number.isFinite(fromId) && fromId > 0) {
                return Math.trunc(fromId);
            }
        }
        const tab = this._findTab(slotId);
        const slotIndex = Number(tab?.dataset?.slotIndex ?? String(slotId).replace(/^slot-/, ''));
        return Number.isFinite(slotIndex) && slotIndex > 0 ? Math.trunc(slotIndex) : null;
    }

    activate(slotId, { reload = false } = {}) {
        const tab = this._findTab(slotId);
        const panel = this._findPanel(slotId);
        if (!tab || !panel) {
            return;
        }
        if (slotId !== this.mainTabIdValue && tab.dataset.filled === 'false') {
            return;
        }

        this.activeId = slotId;

        this.element.querySelectorAll('[data-slot="workspace-tab"]').forEach((el) => {
            const active = el.dataset.tabId === slotId;
            el.setAttribute('aria-selected', active ? 'true' : 'false');
            el.dataset.state = active ? 'active' : 'inactive';
            el.tabIndex = active ? 0 : -1;
        });

        this.element.querySelectorAll('[data-slot="workspace-panel"]').forEach((el) => {
            const active = el.dataset.tabId === slotId;
            el.hidden = !active;
            el.dataset.state = active ? 'active' : 'inactive';
        });

        const nestedActive =
            slotId === this.mainTabIdValue
                ? 'nested-main'
                : panel.dataset.nestedActive || 'nested-main';
        this.activateNested(nestedActive, slotId, { reload });
    }

    activateNested(nestedId, parentId = this.activeId, { reload = false } = {}) {
        const parentPanel = this._findPanel(parentId);
        if (!parentPanel) {
            return;
        }
        if (nestedId !== 'nested-main') {
            const nestedPanel = this._findNestedPanel(parentId, nestedId);
            if (!nestedPanel || nestedPanel.dataset.filled !== 'true') {
                return;
            }
        }

        if (reload) {
            this._unloadNestedPanelHtml(parentId, nestedId);
        }

        parentPanel.dataset.nestedActive = nestedId;

        parentPanel.querySelectorAll('[data-slot="workspace-nested-panel"]').forEach((el) => {
            const active = el.dataset.nestedTabId === nestedId;
            el.hidden = !active;
            el.dataset.state = active ? 'active' : 'inactive';
        });

        if (parentId === this.activeId) {
            this._syncNestedStrip();
            this._syncWorkspaceStatus();
        }

        const panel = this._findNestedPanel(parentId, nestedId);
        const needsLoad =
            reload ||
            (panel?.dataset?.filled === 'true' &&
                Boolean(this._urlForNestedPanel(parentId, nestedId)) &&
                this._nestedContentIsEmpty(panel));

        if (needsLoad) {
            this._reloadNestedPanel(parentId, nestedId);
        }
    }

    /** Reload the currently active nested panel (header refresh control). */
    refreshTab(event) {
        event?.preventDefault?.();
        event?.stopPropagation?.();
        const parentId = this.activeId;
        const parentPanel = this._findPanel(parentId);
        const nestedId = parentPanel?.dataset?.nestedActive || 'nested-main';
        const panel = this._findNestedPanel(parentId, nestedId);
        if (!parentId || !nestedId || !panel) {
            return;
        }
        if (panel.dataset.filled !== 'true' || !this._urlForNestedPanel(parentId, nestedId)) {
            return;
        }
        this.activateNested(nestedId, parentId, { reload: true });
    }

    _loadKey(parentId, nestedId) {
        return `${parentId}:${nestedId}`;
    }

    _cancelLoad(loadKey) {
        const controller = this._abortControllers.get(loadKey);
        if (controller) {
            controller.abort();
            this._abortControllers.delete(loadKey);
        }
        this._loading.delete(loadKey);
    }

    /** Drop HTML for a level-1 content slot (keeps tab metadata). Main list is left intact. */
    _unloadSlotContent(slotId) {
        if (!slotId || slotId === this.mainTabIdValue) {
            return;
        }
        const parentPanel = this._findPanel(slotId);
        if (!parentPanel) {
            return;
        }
        parentPanel.querySelectorAll('[data-slot="workspace-nested-panel"]').forEach((panel) => {
            const nestedId = panel.dataset.nestedTabId;
            if (nestedId) {
                this._cancelLoad(this._loadKey(slotId, nestedId));
            }
            this._clearNestedContent(panel);
        });
    }

    _unloadNestedPanelHtml(parentId, nestedId) {
        if (!parentId || !nestedId) {
            return;
        }
        this._cancelLoad(this._loadKey(parentId, nestedId));
        const panel = this._findNestedPanel(parentId, nestedId);
        if (panel) {
            this._clearNestedContent(panel);
        }
    }

    /**
     * Content body inside a nested panel.
     * @param {HTMLElement|null|undefined} panel
     * @returns {HTMLElement|null}
     */
    _nestedContentEl(panel) {
        if (!(panel instanceof HTMLElement)) {
            return null;
        }
        return (
            panel.querySelector(':scope > [data-slot="workspace-tab-content"]') ?? panel
        );
    }

    /**
     * @param {HTMLElement|null|undefined} panel
     * @returns {boolean}
     */
    _nestedContentIsEmpty(panel) {
        return !String(this._nestedContentEl(panel)?.innerHTML ?? '').trim();
    }

    /**
     * @param {HTMLElement|null|undefined} panel
     * @param {string} html
     */
    _setNestedContentHtml(panel, html) {
        const el = this._nestedContentEl(panel);
        if (el) {
            el.innerHTML = html;
        }
    }

    /** @param {HTMLElement|null|undefined} panel */
    _clearNestedContent(panel) {
        this._setNestedContentHtml(panel, '');
    }

    _urlForNestedPanel(parentId, nestedId) {
        if (nestedId === 'nested-main') {
            const tab = this._findTab(parentId);
            return String(tab?.dataset?.openUrl ?? tab?.dataset?.openTabId ?? '').trim() || null;
        }
        const panel = this._findNestedPanel(parentId, nestedId);
        return String(panel?.dataset?.openUrl ?? panel?.dataset?.openTabId ?? '').trim() || null;
    }

    async _reloadNestedPanel(parentId, nestedId) {
        const panel = this._findNestedPanel(parentId, nestedId);
        const url = this._urlForNestedPanel(parentId, nestedId);
        if (!panel || !url) {
            return;
        }

        const loadKey = this._loadKey(parentId, nestedId);
        this._cancelLoad(loadKey);

        const controller = new AbortController();
        this._abortControllers.set(loadKey, controller);
        this._loading.add(loadKey);
        this._setNestedContentHtml(panel, this._loadingMarkup());

        try {
            const response = await fetch(url, {
                signal: controller.signal,
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Workspace-Tab': '1',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(
                    this.t('Failed to load tab (%status%)', { '%status%': response.status })
                );
            }
            const html = await response.text();
            if (controller.signal.aborted) {
                return;
            }
            this._setNestedContentHtml(panel, html);
            this._finalizePanelContent(panel);
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
            this._setNestedContentHtml(
                panel,
                this._errorMarkup(error?.message ?? this.t('Failed to load'))
            );
            this._clearPanelStatus(panel);
        } finally {
            if (this._abortControllers.get(loadKey) === controller) {
                this._abortControllers.delete(loadKey);
                this._loading.delete(loadKey);
            }
        }
    }

    _syncNestedStrip() {
        if (!this.nestedTabsValue) {
            this._setNestedStripVisible(false);
            return;
        }

        const onMain = this.activeId === this.mainTabIdValue;
        this._setNestedStripVisible(!onMain);

        if (onMain || !this.hasNestedMainTabTarget) {
            return;
        }

        const parentId = this.activeId;
        const parentTab = this._findTab(parentId);
        const parentPanel = this._findPanel(parentId);
        const nestedActive = parentPanel?.dataset?.nestedActive || 'nested-main';

        // L2 main is icon-only; keep accessible name from the active L1 slot number.
        const parentIndex = this._slotIndexForTabId(parentId, parentTab);
        const mainLabel = this.tabPlaceholderLabel(parentIndex);

        this.nestedMainTabTarget.setAttribute('aria-label', mainLabel);
        this.nestedMainTabTarget.title = mainLabel;
        this.nestedMainTabTarget.dataset.filled = 'true';

        this.nestedSlotTabTargets.forEach((stripTab) => {
            const nestedId = stripTab.dataset.nestedTabId;
            const panel = this._findNestedPanel(parentId, nestedId);
            const index = stripTab.dataset.slotIndex || nestedId?.replace('nested-slot-', '');
            if (!panel || panel.dataset.filled !== 'true') {
                stripTab.dataset.filled = 'false';
                delete stripTab.dataset.openTabId;
                delete stripTab.dataset.openUrl;
                delete stripTab.dataset.openLabel;
                stripTab.title = '';
                this._setNestedTabLabel(
                    stripTab,
                    this.nestedTabPlaceholderLabel(parentIndex, index)
                );
                this._setNestedCloseVisible(stripTab, false);
            } else {
                stripTab.dataset.filled = 'true';
                stripTab.dataset.openTabId = panel.dataset.openTabId ?? '';
                stripTab.dataset.openUrl = panel.dataset.openUrl ?? '';
                stripTab.dataset.openLabel = panel.dataset.openLabel ?? '';
                this._applyNestedTabDisplay(stripTab);
                this._setNestedCloseVisible(stripTab, true);
            }
        });

        this.element.querySelectorAll('[data-slot="workspace-nested-tab"]').forEach((el) => {
            const active = el.dataset.nestedTabId === nestedActive;
            el.setAttribute('aria-selected', active ? 'true' : 'false');
            el.dataset.state = active ? 'active' : 'inactive';
            el.tabIndex = active ? 0 : -1;
        });
    }

    _setNestedStripVisible(visible) {
        if (!this.hasNestedTablistWrapTarget) {
            return;
        }
        this.nestedTablistWrapTarget.hidden = !visible;
        this.nestedTablistWrapTarget.setAttribute('aria-hidden', visible ? 'false' : 'true');
    }

    _mainTabLabelText() {
        const label = this.mainTabTarget?.querySelector?.('[data-slot="workspace-tab-label"]');
        return String(label?.textContent ?? '').trim() || this.t('List');
    }

    _findTab(slotId) {
        return this.element.querySelector(
            `[data-slot="workspace-tab"][data-tab-id="${CSS.escape(slotId)}"]`
        );
    }

    _findPanel(slotId) {
        return this.element.querySelector(
            `[data-slot="workspace-panel"][data-tab-id="${CSS.escape(slotId)}"]`
        );
    }

    _findNestedPanel(parentId, nestedId) {
        const parent = this._findPanel(parentId);
        if (!parent) {
            return null;
        }
        return parent.querySelector(
            `[data-slot="workspace-nested-panel"][data-nested-tab-id="${CSS.escape(nestedId)}"]`
        );
    }

    _findNestedStripTab(nestedId) {
        return this.element.querySelector(
            `[data-slot="workspace-nested-tab"][data-nested-tab-id="${CSS.escape(nestedId)}"]`
        );
    }

    _findEmptySlot() {
        return this.slotTabTargets.find((tab) => tab.dataset.filled !== 'true') ?? null;
    }

    _findEmptyNestedSlot(parentId) {
        const parent = this._findPanel(parentId);
        if (!parent) {
            return null;
        }
        return (
            Array.from(
                parent.querySelectorAll(
                    '[data-slot="workspace-nested-panel"][data-nested-tab-id^="nested-slot-"]'
                )
            ).find((panel) => panel.dataset.filled !== 'true') ?? null
        );
    }

    _findSlotByUrl(url) {
        const key = String(url);
        return (
            this.slotTabTargets.find(
                (tab) =>
                    tab.dataset.filled === 'true' &&
                    (tab.dataset.openTabId === key || tab.dataset.openUrl === key)
            ) ?? null
        );
    }

    /**
     * @returns {{ parentId: string, nestedId: string, panel: HTMLElement }|null}
     */
    _findNestedSlotByUrl(url, parentId = null) {
        const key = String(url);
        const parents = parentId
            ? [this._findPanel(parentId)].filter(Boolean)
            : Array.from(this.element.querySelectorAll('[data-slot="workspace-panel"]'));

        for (const parent of parents) {
            const panels = parent.querySelectorAll(
                '[data-slot="workspace-nested-panel"][data-nested-tab-id^="nested-slot-"]'
            );
            for (const panel of panels) {
                if (
                    panel.dataset.filled === 'true' &&
                    (panel.dataset.openTabId === key || panel.dataset.openUrl === key)
                ) {
                    return {
                        parentId: parent.dataset.tabId,
                        nestedId: panel.dataset.nestedTabId,
                        panel,
                    };
                }
            }
        }
        return null;
    }

    _setTabLabel(tab, label) {
        const labelEl = tab.querySelector('[data-slot="workspace-tab-label"]');
        if (labelEl) {
            labelEl.textContent = label;
        }
    }

    _setNestedTabLabel(tab, label) {
        const labelEl = tab.querySelector('[data-slot="workspace-nested-tab-label"]');
        if (labelEl) {
            labelEl.textContent = label;
        }
    }

    _setCloseVisible(tab, visible) {
        const close = tab.querySelector('[data-slot="workspace-tab-close"]');
        if (close) {
            close.hidden = !visible;
        }
    }

    _setNestedCloseVisible(tab, visible) {
        const close = tab.querySelector('[data-slot="workspace-nested-tab-close"]');
        if (close) {
            close.hidden = !visible;
        }
    }

    _displayLabelForTab(tab) {
        if (!tab) {
            return '';
        }
        const title = String(tab.dataset.openLabel ?? '').trim();
        const url = String(tab.dataset.openUrl ?? tab.dataset.openTabId ?? '').trim();
        if (this.labelModeValue === 'url') {
            return url || title;
        }
        return title || url;
    }

    _applyTabDisplay(tab) {
        if (tab.dataset.filled !== 'true' || tab.dataset.tabId === this.mainTabIdValue) {
            return;
        }
        const display = this._displayLabelForTab(tab);
        const title = String(tab.dataset.openLabel ?? '').trim();
        const url = String(tab.dataset.openUrl ?? '').trim();
        this._setTabLabel(tab, display);
        tab.title = title && url && title !== url ? `${title}\n${url}` : display;
    }

    _applyNestedTabDisplay(tab) {
        if (tab.dataset.filled !== 'true' || tab.dataset.nestedTabId === 'nested-main') {
            return;
        }
        const display = this._displayLabelForTab(tab);
        const title = String(tab.dataset.openLabel ?? '').trim();
        const url = String(tab.dataset.openUrl ?? '').trim();
        this._setNestedTabLabel(tab, display);
        tab.title = title && url && title !== url ? `${title}\n${url}` : display;
    }

    _syncFilledLabels() {
        this.slotTabTargets.forEach((tab) => this._applyTabDisplay(tab));
    }

    _syncLabelModeControls() {
        const mode = this.labelModeValue === 'url' ? 'url' : 'title';
        this.element.querySelectorAll('[data-slot="workspace-tab-label-mode"] [data-label-mode]').forEach((button) => {
            const active = button.dataset.labelMode === mode;
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            button.dataset.state = active ? 'on' : 'off';
        });
    }

    _readStoredLabelMode() {
        try {
            const stored = window.localStorage?.getItem('tstdt.workspaceTabLabelMode');
            return stored === 'title' || stored === 'url' ? stored : null;
        } catch {
            return null;
        }
    }

    _writeStoredLabelMode(mode) {
        try {
            window.localStorage?.setItem('tstdt.workspaceTabLabelMode', mode);
        } catch {
            // Ignore quota / private-mode failures.
        }
    }

    _syncPlaceholders() {
        this.slotTabTargets.forEach((tab) => {
            if (tab.dataset.filled !== 'true') {
                this._setTabLabel(tab, this.tabPlaceholderLabel(tab.dataset.slotIndex || ''));
                this._setCloseVisible(tab, false);
            }
        });
        const parentIndex = this._slotIndexForTabId(this.activeId);
        this.nestedSlotTabTargets.forEach((tab) => {
            if (tab.dataset.filled !== 'true') {
                this._setNestedTabLabel(
                    tab,
                    this.nestedTabPlaceholderLabel(parentIndex, tab.dataset.slotIndex || '')
                );
                this._setNestedCloseVisible(tab, false);
            }
        });
    }

    parseI18n() {
        const raw = this.element.getAttribute('data-workspace-i18n') ?? '{}';
        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch {
            return {};
        }
    }

    t(key, replacements = {}) {
        let message = String(this._i18n?.[key] ?? key);
        for (const [token, value] of Object.entries(replacements ?? {})) {
            message = message.replaceAll(String(token), String(value));
        }
        return message;
    }

    tabPlaceholderLabel(index) {
        return this.t('Tab %number%', { '%number%': index });
    }

    /**
     * Level-2 empty-slot label: "Tab {L1}.{L2}" (e.g. Tab 1.3 under level-1 tab 1).
     * @param {string|number} parentIndex
     * @param {string|number} nestedIndex
     */
    nestedTabPlaceholderLabel(parentIndex, nestedIndex) {
        const parent = String(parentIndex ?? '').trim();
        const nested = String(nestedIndex ?? '').trim();
        if (!parent || !nested) {
            return this.tabPlaceholderLabel(nested || parent);
        }
        return this.tabPlaceholderLabel(`${parent}.${nested}`);
    }

    /**
     * @param {string|null|undefined} tabId
     * @param {HTMLElement|null|undefined} [tab]
     * @returns {string}
     */
    _slotIndexForTabId(tabId, tab = null) {
        const el = tab ?? this._findTab(tabId);
        return String(
            el?.dataset?.slotIndex || String(tabId ?? '').replace(/^slot-/, '') || ''
        ).trim();
    }

    _openReplaceDialog({ nested = false, parentId = null } = {}) {
        if (!this.hasReplaceDialogTarget || !this.hasReplaceOptionsTarget) {
            return;
        }

        const replaceLabel = this.t('Replace');
        this.replaceOptionsTarget.innerHTML = '';

        if (nested) {
            const parent = this._findPanel(parentId || this.activeId);
            parent
                ?.querySelectorAll(
                    '[data-slot="workspace-nested-panel"][data-nested-tab-id^="nested-slot-"][data-filled="true"]'
                )
                .forEach((panel) => {
                    const label =
                        this._displayLabelForTab(panel) ||
                        panel.dataset.openLabel ||
                        panel.dataset.nestedTabId;
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.dataset.nestedTabId = panel.dataset.nestedTabId;
                    button.dataset.action = 'click->tstdt--workspace-tabs#chooseReplaceSlot';
                    button.className =
                        'flex w-full items-center justify-between gap-3 rounded-lg border border-border bg-background px-3 py-2 text-left text-sm hover:bg-muted';
                    button.innerHTML = `<span class="min-w-0 truncate font-medium">${this._escape(label)}</span><span class="shrink-0 text-xs text-muted-foreground">${this._escape(replaceLabel)}</span>`;
                    this.replaceOptionsTarget.appendChild(button);
                });
        } else {
            this.slotTabTargets.forEach((tab) => {
                if (tab.dataset.filled !== 'true') {
                    return;
                }
                const label = this._displayLabelForTab(tab) || tab.dataset.tabId;
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.tabId = tab.dataset.tabId;
                button.dataset.action = 'click->tstdt--workspace-tabs#chooseReplaceSlot';
                button.className =
                    'flex w-full items-center justify-between gap-3 rounded-lg border border-border bg-background px-3 py-2 text-left text-sm hover:bg-muted';
                button.innerHTML = `<span class="min-w-0 truncate font-medium">${this._escape(label)}</span><span class="shrink-0 text-xs text-muted-foreground">${this._escape(replaceLabel)}</span>`;
                this.replaceOptionsTarget.appendChild(button);
            });
        }

        this.replaceDialogTarget.showModal?.();
    }

    _closeReplaceDialog() {
        if (this.hasReplaceDialogTarget && this.replaceDialogTarget.open) {
            this.replaceDialogTarget.close();
        }
    }

    _loadingMarkup() {
        const loading = this._escape(this.t('Loading…'));
        return `<div class="flex items-center gap-2 text-sm text-muted-foreground" role="status">${loading}</div>`;
    }

    _errorMarkup(message) {
        return `<div class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert">${this._escape(message)}</div>`;
    }

    _escape(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    }

    async _onPanelSubmit(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const nestedPanel = form.closest('[data-slot="workspace-nested-panel"]');
        const panel = form.closest('[data-slot="workspace-panel"]');
        if (!panel || panel.dataset.filled !== 'true') {
            return;
        }

        // AJAX-submit forms inside any filled nested panel that has a workspace URL
        // (includes level-1 main when loaded via data-open-url).
        if (!(nestedPanel instanceof HTMLElement) || nestedPanel.dataset.filled !== 'true') {
            return;
        }
        const parentId = panel.dataset.tabId;
        const nestedId = nestedPanel.dataset.nestedTabId;
        if (!parentId || !nestedId || !this._urlForNestedPanel(parentId, nestedId)) {
            return;
        }

        event.preventDefault();

        const targetPanel = nestedPanel;
        const content = this._nestedContentEl(targetPanel) || targetPanel;
        const action = form.getAttribute('action') || window.location.href;
        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        const body = new FormData(form);
        content.innerHTML = this._loadingMarkup();

        try {
            const response = await fetch(action, {
                method,
                body,
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Workspace-Tab': '1',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(this.t('Save failed (%status%)', { '%status%': response.status }));
            }
            content.innerHTML = await response.text();
            this._finalizePanelContent(targetPanel);
        } catch (error) {
            content.innerHTML = this._errorMarkup(error?.message ?? this.t('Save failed'));
            this._clearPanelStatus(targetPanel);
        }
    }

    /**
     * After HTML load/save: stamp {@code data-loaded-at} on the visible nested panel
     * and refresh the active tab-button badge.
     */
    _finalizePanelContent(panel) {
        if (!(panel instanceof HTMLElement)) {
            return;
        }
        panel.dataset.loadedAt = new Date().toISOString();
        this._syncWorkspaceStatus();
    }

    _clearPanelStatus(panel) {
        if (!(panel instanceof HTMLElement)) {
            return;
        }
        delete panel.dataset.loadedAt;
        this._syncWorkspaceStatus();
    }

    /** Hide every tab loaded-ago age-dot. */
    _clearAllTabLoadedBadges() {
        this.element.querySelectorAll('[data-slot="workspace-tab-loaded"]').forEach((loadedEl) => {
            loadedEl.hidden = true;
            delete loadedEl.dataset.age;
            loadedEl.removeAttribute('aria-label');
        });
    }

    /**
     * Apply (or clear) the loaded-ago age-dot from a panel's {@code data-loaded-at}.
     * @param {HTMLElement|null|undefined} tab
     * @param {HTMLElement|null|undefined} panel
     */
    _applyLoadedBadge(tab, panel) {
        const loadedEl = tab?.querySelector?.('[data-slot="workspace-tab-loaded"]');
        if (!loadedEl || !(tab instanceof HTMLElement)) {
            return;
        }
        const loadedAt =
            panel?.dataset?.filled === 'true' ? panel.dataset.loadedAt : null;
        const info = this._ageFromLoadedAt(loadedAt);
        if (!info) {
            loadedEl.hidden = true;
            delete loadedEl.dataset.age;
            loadedEl.removeAttribute('aria-label');
            return;
        }
        loadedEl.dataset.age = info.age;
        loadedEl.setAttribute('aria-label', info.label);
        loadedEl.hidden = false;
    }

    /**
     * @param {string|null|undefined} loadedAt
     * @returns {{ age: string, label: string }|null}
     */
    _ageFromLoadedAt(loadedAt) {
        if (!loadedAt) {
            return null;
        }
        const ageMs = Date.now() - Date.parse(loadedAt);
        if (!Number.isFinite(ageMs)) {
            return null;
        }
        const ageMinutes = ageMs / 60000;
        let age = 'fresh';
        if (ageMinutes >= 30) {
            age = 'danger';
        } else if (ageMinutes >= 10) {
            age = 'warn';
        } else if (ageMinutes >= 5) {
            age = 'recent';
        }
        return { age, label: this._formatLoadedAgo(loadedAt) };
    }

    /** Active nested panel under the focused level-1 tab. */
    _activeNestedPanel() {
        const parentId = this.activeId;
        const parentPanel = this._findPanel(parentId);
        if (!parentPanel) {
            return null;
        }
        const nestedId = parentPanel.dataset.nestedActive || 'nested-main';
        return this._findNestedPanel(parentId, nestedId);
    }

    /** Header loaded-ago badge + refresh for the active nested panel. */
    _syncHeaderTabStatus() {
        const panel = this._activeNestedPanel();
        const parentId = this.activeId;
        const nestedId = panel?.dataset?.nestedTabId || 'nested-main';
        const loadedAt =
            panel?.dataset?.filled === 'true' ? panel.dataset.loadedAt : null;
        const info = this._ageFromLoadedAt(loadedAt);
        const canRefresh =
            Boolean(panel) &&
            panel.dataset.filled === 'true' &&
            Boolean(this._urlForNestedPanel(parentId, nestedId));

        if (this.hasHeaderLoadedBadgeTarget) {
            const badge = this.headerLoadedBadgeTarget;
            if (!info) {
                badge.hidden = true;
                delete badge.dataset.age;
                badge.textContent = '';
            } else {
                badge.dataset.age = info.age;
                badge.textContent = this.t('Loaded %ago%', { '%ago%': info.label });
                badge.hidden = false;
            }
        }

        if (this.hasHeaderRefreshTarget) {
            this.headerRefreshTarget.hidden = !canRefresh;
        }

        if (this.hasHeaderTabStatusTarget) {
            this.headerTabStatusTarget.hidden = !(info || canRefresh);
        }
    }

    /**
     * Show loaded-ago on every filled tab button (L1 nested-main + L2 slots under active L1).
     * Values refresh via the 1-minute ticker.
     */
    _syncWorkspaceStatus() {
        this._clearAllTabLoadedBadges();

        // Level-1: each tab button mirrors its nested-main panel load time.
        this.element.querySelectorAll('[data-slot="workspace-tab"]').forEach((tab) => {
            const tabId = tab.dataset.tabId;
            if (!tabId || tab.dataset.filled !== 'true') {
                return;
            }
            this._applyLoadedBadge(tab, this._findNestedPanel(tabId, 'nested-main'));
        });

        // Level-2 strip: badges for nested panels under the active level-1 content tab.
        if (
            this.nestedTabsValue &&
            this.activeId &&
            this.activeId !== this.mainTabIdValue
        ) {
            const parentId = this.activeId;
            this.element.querySelectorAll('[data-slot="workspace-nested-tab"]').forEach((tab) => {
                const nestedId = tab.dataset.nestedTabId;
                if (!nestedId || nestedId === 'nested-main') {
                    return;
                }
                this._applyLoadedBadge(tab, this._findNestedPanel(parentId, nestedId));
            });
        }

        this._syncHeaderTabStatus();
    }

    _startLoadedAgoTicker() {
        this._stopLoadedAgoTicker();
        this._loadedAgoTimer = window.setInterval(() => {
            this._syncWorkspaceStatus();
        }, 60_000);
    }

    _stopLoadedAgoTicker() {
        if (this._loadedAgoTimer != null) {
            window.clearInterval(this._loadedAgoTimer);
            this._loadedAgoTimer = null;
        }
    }

    _formatLoadedAgo(iso) {
        const then = Date.parse(iso);
        if (!Number.isFinite(then)) {
            return this.t('just now');
        }
        const seconds = Math.max(0, Math.floor((Date.now() - then) / 1000));
        if (seconds < 45) {
            return this.t('just now');
        }
        if (seconds < 90) {
            return this.t('1 min ago');
        }
        if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            return this.t('%count% min ago', {
                '%count%': minutes,
            });
        }
        if (seconds < 5400) {
            return this.t('1h ago');
        }
        const hours = Math.floor(seconds / 3600);
        return this.t('%count%h ago', {
            '%count%': hours,
        });
    }
}
