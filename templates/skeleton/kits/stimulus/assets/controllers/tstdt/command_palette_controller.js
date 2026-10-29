import { Controller } from '@hotwired/stimulus';

/**
 * Workspace command palette driven by PHP {@code CommandPalette} config
 * ({@code data-command-palette-config} JSON on the controller element).
 *
 * Built-in actions: go-main, refresh-main, close-all, open-sidebar, close-sidebar,
 * go-tab / close-tab, go-tab-slots / close-tab-slots (expand 1…N), event (custom DOM event).
 */
export default class extends Controller {
    static targets = [
        'dialog',
        'input',
        'list',
        'empty',
        'confirmDialog',
        'confirmTitle',
        'confirmDescription',
        'confirmButton',
    ];

    connect() {
        this.config = this.parseConfig();
        this._commands = [];
        this._filtered = [];
        this._activeIndex = 0;
        this._pendingConfirm = null;
        this._shortcut = String(this.config?.keyboardShortcut ?? 'k').toLowerCase();

        this._boundKeydown = this.onGlobalKeydown.bind(this);
        this._boundDialogClick = (event) => {
            if (event.target === this.dialogTarget) {
                this.close();
            }
        };
        this._boundDialogCancel = (event) => {
            event.preventDefault();
            this.close();
        };
        this._boundConfirmClick = (event) => {
            if (event.target === this.confirmDialogTarget) {
                this.cancelConfirm();
            }
        };
        this._boundConfirmCancel = (event) => {
            event.preventDefault();
            this.cancelConfirm();
        };

        document.addEventListener('keydown', this._boundKeydown);
        if (this.hasDialogTarget) {
            this.dialogTarget.addEventListener('click', this._boundDialogClick);
            this.dialogTarget.addEventListener('cancel', this._boundDialogCancel);
        }
        if (this.hasConfirmDialogTarget) {
            this.confirmDialogTarget.addEventListener('click', this._boundConfirmClick);
            this.confirmDialogTarget.addEventListener('cancel', this._boundConfirmCancel);
        }
    }

    disconnect() {
        document.removeEventListener('keydown', this._boundKeydown);
        if (this.hasDialogTarget) {
            this.dialogTarget.removeEventListener('click', this._boundDialogClick);
            this.dialogTarget.removeEventListener('cancel', this._boundDialogCancel);
        }
        if (this.hasConfirmDialogTarget) {
            this.confirmDialogTarget.removeEventListener('click', this._boundConfirmClick);
            this.confirmDialogTarget.removeEventListener('cancel', this._boundConfirmCancel);
        }
    }

    onGlobalKeydown(event) {
        if (!this._shortcut) {
            return;
        }
        if (!(event.metaKey || event.ctrlKey) || event.altKey) {
            return;
        }
        if (String(event.key || '').toLowerCase() !== this._shortcut) {
            return;
        }
        event.preventDefault();
        if (this.isOpen()) {
            this.close();
        } else {
            this.open();
        }
    }

    isOpen() {
        return Boolean(this.hasDialogTarget && this.dialogTarget.open);
    }

    open(event) {
        event?.preventDefault?.();
        if (!this.hasDialogTarget) {
            return;
        }

        this._commands = this.buildCommands();
        this._filtered = this._commands;
        this._activeIndex = 0;
        if (this.hasInputTarget) {
            this.inputTarget.value = '';
        }
        this.renderList();
        this.dialogTarget.showModal?.();
        requestAnimationFrame(() => this.inputTarget?.focus?.());
    }

    close(event) {
        event?.preventDefault?.();
        if (!this.hasDialogTarget || !this.dialogTarget.open) {
            return;
        }
        this.dialogTarget.close();
    }

    onInput() {
        const query = String(this.inputTarget?.value ?? '')
            .trim()
            .toLowerCase();
        this._filtered = query
            ? this._commands.filter((command) => command.search.includes(query))
            : this._commands;
        this._activeIndex = 0;
        this.renderList();
    }

    onInputKeydown(event) {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.moveActive(1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.moveActive(-1);
                break;
            case 'Enter':
                event.preventDefault();
                this.runActive();
                break;
            case 'Escape':
                event.preventDefault();
                this.close();
                break;
            default:
                break;
        }
    }

    selectCommand(event) {
        const index = Number(event.currentTarget?.dataset?.index);
        if (!Number.isFinite(index)) {
            return;
        }
        this._activeIndex = index;
        this.runActive();
    }

    highlightCommand(event) {
        const index = Number(event.currentTarget?.dataset?.index);
        if (!Number.isFinite(index)) {
            return;
        }
        this._activeIndex = index;
        this.syncActiveStyles();
    }

    moveActive(delta) {
        if (this._filtered.length === 0) {
            return;
        }
        const next = (this._activeIndex + delta + this._filtered.length) % this._filtered.length;
        this._activeIndex = next;
        this.syncActiveStyles();
        this._filtered[next]?.element?.scrollIntoView?.({ block: 'nearest' });
    }

    runActive() {
        const command = this._filtered[this._activeIndex];
        if (!command || command.disabled) {
            return;
        }
        this.close();
        if (command.confirm) {
            this.openConfirm(command);
            return;
        }
        command.run();
    }

    openConfirm(command) {
        if (!this.hasConfirmDialogTarget) {
            command.run();
            return;
        }
        this._pendingConfirm = command;
        const confirm = command.confirm ?? {};
        if (this.hasConfirmTitleTarget) {
            this.confirmTitleTarget.textContent = String(confirm.title ?? 'Are you sure?');
        }
        if (this.hasConfirmDescriptionTarget) {
            const description = String(confirm.description ?? '').trim();
            this.confirmDescriptionTarget.textContent = description;
            this.confirmDescriptionTarget.hidden = description === '';
        }
        if (this.hasConfirmButtonTarget) {
            this.confirmButtonTarget.textContent = String(confirm.confirmLabel ?? 'Confirm');
        }
        this.confirmDialogTarget.showModal?.();
    }

    acceptConfirm(event) {
        event?.preventDefault?.();
        const command = this._pendingConfirm;
        this._pendingConfirm = null;
        this.closeConfirmDialog();
        command?.run?.();
    }

    cancelConfirm(event) {
        event?.preventDefault?.();
        this._pendingConfirm = null;
        this.closeConfirmDialog();
    }

    closeConfirmDialog() {
        if (this.hasConfirmDialogTarget && this.confirmDialogTarget.open) {
            this.confirmDialogTarget.close();
        }
    }

    parseConfig() {
        const raw = this.element.getAttribute('data-command-palette-config') ?? '{}';
        try {
            const config = JSON.parse(raw);
            return config && typeof config === 'object' ? config : {};
        } catch (error) {
            console.error('[command-palette] Invalid data-command-palette-config JSON', error);
            return {};
        }
    }

    buildCommands() {
        const definitions = Array.isArray(this.config?.commands) ? this.config.commands : [];
        const tabs = this.workspaceTabsController();
        const slotCount = Number(tabs?.slotCountValue ?? 5) || 5;
        const mainLabel = this.mainTabLabel();
        const commands = [];

        const sidebarOpen = this.isSidebarOpen();
        const tabLabelMode = this.tabLabelMode();

        for (const definition of definitions) {
            const action = String(definition?.action ?? '').trim();
            if (definition?.expandSlots || action === 'go-tab-slots' || action === 'close-tab-slots') {
                const go = action === 'go-tab-slots' || action === 'go-tab';
                for (let index = 1; index <= slotCount; index += 1) {
                    const command = this.buildSlotCommand(definition, index, go);
                    // Skip empty placeholder slots — only filled tabs appear in the palette.
                    if (command) {
                        commands.push(command);
                    }
                }
                continue;
            }

            // Only offer the sidebar action that matches current state.
            if (action === 'open-sidebar' && sidebarOpen) {
                continue;
            }
            if (action === 'close-sidebar' && !sidebarOpen) {
                continue;
            }

            // Only offer the label-mode switch that matches the opposite of current mode.
            if (action === 'show-tab-titles' && tabLabelMode === 'title') {
                continue;
            }
            if (action === 'show-tab-urls' && tabLabelMode === 'url') {
                continue;
            }

            commands.push(this.buildStaticCommand(definition, mainLabel));
        }

        return commands;
    }

    isSidebarOpen() {
        const sidebar = this.sidebarController();
        if (!sidebar) {
            return false;
        }
        if (typeof sidebar.isOpen === 'function') {
            return Boolean(sidebar.isOpen());
        }
        return sidebar.element?.dataset?.state === 'expanded';
    }

    tabLabelMode() {
        const tabs = this.workspaceTabsController();
        const mode = String(tabs?.labelModeValue ?? 'title').trim();
        return mode === 'url' ? 'url' : 'title';
    }

    buildStaticCommand(definition, mainLabel) {
        const action = String(definition?.action ?? 'event').trim();
        const baseLabel = String(definition?.label ?? definition?.id ?? action).trim();
        const label =
            action === 'go-main' && mainLabel
                ? `${baseLabel.replace(/\s*\(.*\)\s*$/, '')} (${mainLabel})`
                : baseLabel;
        const keywords = String(definition?.keywords ?? '').trim();
        const search = `${label} ${keywords}`.toLowerCase();
        const icon = String(definition?.icon ?? '').trim() || null;

        return {
            id: String(definition?.id ?? action),
            group: String(definition?.group ?? 'Workspace'),
            label,
            icon,
            confirm: this.normalizeConfirm(definition?.confirm),
            search,
            disabled: false,
            run: () => this.runAction(definition, null),
        };
    }

    /**
     * @returns {object|null} null when the slot is empty (omitted from the palette)
     */
    buildSlotCommand(definition, index, isGo) {
        const tab = this.element.querySelector(
            `[data-slot="workspace-tab"][data-tab-id="slot-${index}"]`
        );
        const filled = tab?.dataset?.filled === 'true';
        if (!filled) {
            return null;
        }
        const title = String(tab?.dataset?.openLabel ?? '').trim();
        const url = String(tab?.dataset?.openUrl ?? tab?.dataset?.openTabId ?? '').trim();
        const display = this.tabDisplayLabel(title, url);
        const i18n = this.workspaceI18n();
        const suffix = display ? ` — ${display}` : '';
        const template = String(
            definition?.label ??
                (isGo
                    ? i18n['Go to tab {n}'] ?? 'Go to tab {n}'
                    : i18n['Close tab {n}'] ?? 'Close tab {n}')
        );
        const baseLabel = template.includes('{n}')
            ? template.replaceAll('{n}', String(index))
            : `${template} ${index}`;
        const keywords = String(definition?.keywords ?? '').trim();
        const icon = String(definition?.icon ?? '').trim() || null;

        return {
            id: `${definition?.id ?? (isGo ? 'go-tab' : 'close-tab')}-${index}`,
            group: String(definition?.group ?? 'Tabs'),
            label: `${baseLabel}${suffix}`,
            icon,
            confirm: this.normalizeConfirm(definition?.confirm),
            search: `${baseLabel} ${title} ${url} ${keywords}`.toLowerCase(),
            disabled: false,
            run: () =>
                this.runAction(
                    {
                        ...definition,
                        action: isGo ? 'go-tab' : 'close-tab',
                        slotIndex: index,
                    },
                    index
                ),
        };
    }

    normalizeConfirm(raw) {
        if (!raw || typeof raw !== 'object') {
            return null;
        }
        const title = String(raw.title ?? '').trim();
        if (!title && !String(raw.description ?? '').trim()) {
            return null;
        }
        return {
            title: title || 'Are you sure?',
            description: String(raw.description ?? '').trim(),
            confirmLabel: String(raw.confirmLabel ?? '').trim() || 'Confirm',
        };
    }

    /** Match workspace tab strip: title mode shows title, URL mode shows URL. */
    tabDisplayLabel(title, url) {
        if (this.tabLabelMode() === 'url') {
            return url || title;
        }
        return title || url;
    }

    workspaceI18n() {
        const raw = this.element.getAttribute('data-workspace-i18n') ?? '{}';
        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch {
            return {};
        }
    }

    runAction(definition, slotIndex) {
        const action = String(definition?.action ?? '').trim();
        const index = Number(slotIndex ?? definition?.slotIndex);

        switch (action) {
            case 'go-main':
                document.dispatchEvent(
                    new CustomEvent('workspace-tabs:activate', { detail: { slotId: 'main' } })
                );
                return;
            case 'refresh-main':
                this.workspaceTabsController()?.refreshTab?.();
                return;
            case 'close-all':
                document.dispatchEvent(new CustomEvent('workspace-tabs:close-all'));
                return;
            case 'open-sidebar':
                this.sidebarController()?.open?.();
                return;
            case 'close-sidebar':
                this.sidebarController()?.close?.();
                return;
            case 'show-tab-titles':
                this.workspaceTabsController()?.applyLabelMode?.('title');
                return;
            case 'show-tab-urls':
                this.workspaceTabsController()?.applyLabelMode?.('url');
                return;
            case 'go-tab':
                if (Number.isFinite(index) && index >= 1) {
                    document.dispatchEvent(
                        new CustomEvent('workspace-tabs:activate', {
                            detail: { slotIndex: Math.trunc(index) },
                        })
                    );
                }
                return;
            case 'close-tab':
                if (Number.isFinite(index) && index >= 1) {
                    document.dispatchEvent(
                        new CustomEvent('workspace-tabs:close-slot', {
                            detail: { slotIndex: Math.trunc(index) },
                        })
                    );
                }
                return;
            case 'event':
            default: {
                const eventName = String(definition?.event ?? '').trim();
                if (!eventName) {
                    return;
                }
                document.dispatchEvent(
                    new CustomEvent(eventName, {
                        bubbles: true,
                        detail: {
                            command: definition,
                            config: this.config,
                        },
                    })
                );
            }
        }
    }

    renderList() {
        if (!this.hasListTarget) {
            return;
        }

        this.listTarget.innerHTML = '';
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = this._filtered.length > 0;
        }

        let lastGroup = null;
        this._filtered.forEach((command, index) => {
            if (command.group && command.group !== lastGroup) {
                lastGroup = command.group;
                const heading = document.createElement('div');
                heading.className = 'px-2 py-1.5 text-xs font-medium text-muted-foreground';
                heading.textContent = command.group;
                this.listTarget.appendChild(heading);
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.role = 'option';
            button.dataset.index = String(index);
            button.dataset.commandId = command.id;
            button.setAttribute('aria-selected', index === this._activeIndex ? 'true' : 'false');
            button.disabled = Boolean(command.disabled);
            button.className = [
                'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm outline-none',
                'hover:bg-accent hover:text-accent-foreground',
                'aria-selected:bg-accent aria-selected:text-accent-foreground',
                'disabled:pointer-events-none disabled:opacity-40',
            ].join(' ');

            const iconWrap = document.createElement('span');
            iconWrap.className =
                'inline-flex size-4 shrink-0 items-center justify-center text-muted-foreground';
            iconWrap.setAttribute('aria-hidden', 'true');
            const iconNode = this.cloneCommandIcon(command.icon);
            if (iconNode) {
                iconWrap.appendChild(iconNode);
            }
            button.appendChild(iconWrap);

            const label = document.createElement('span');
            label.className = 'min-w-0 flex-1 truncate';
            label.textContent = command.label;
            button.appendChild(label);

            button.addEventListener('click', (event) => this.selectCommand(event));
            button.addEventListener('mousemove', (event) => this.highlightCommand(event));
            command.element = button;
            this.listTarget.appendChild(button);
        });
    }

    /** Clone a pre-rendered Lucide SVG from the Twig icon registry. */
    cloneCommandIcon(name) {
        const iconName = String(name ?? '').trim();
        if (!iconName || !this.hasDialogTarget) {
            return null;
        }
        const source = this.dialogTarget.querySelector(
            `[data-command-palette-icon="${CSS.escape(iconName)}"] svg`
        );
        if (!source) {
            return null;
        }
        const clone = source.cloneNode(true);
        clone.classList.add('size-4');
        clone.setAttribute('aria-hidden', 'true');
        return clone;
    }

    syncActiveStyles() {
        const options = this.listTarget?.querySelectorAll?.('[role="option"]') ?? [];
        options.forEach((option, index) => {
            option.setAttribute('aria-selected', index === this._activeIndex ? 'true' : 'false');
        });
    }

    workspaceTabsController() {
        return this.application.getControllerForElementAndIdentifier(
            this.element,
            'tstdt--workspace-tabs'
        );
    }

    sidebarController() {
        const host = this.element.closest('[data-controller~="tstdt--sidebar"]');
        if (!host) {
            return null;
        }
        return this.application.getControllerForElementAndIdentifier(host, 'tstdt--sidebar');
    }

    mainTabLabel() {
        const mainTab =
            this.element.querySelector('[data-slot="workspace-tab"][data-tab-id="main"]') ??
            this.element.querySelector('[data-tstdt--workspace-tabs-target="mainTab"]');
        const label = mainTab?.querySelector?.('[data-slot="workspace-tab-label"]');
        return String(label?.textContent ?? '').trim();
    }

}
