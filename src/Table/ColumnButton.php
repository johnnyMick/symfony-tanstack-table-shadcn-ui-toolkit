<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Fluent row action button for a {@see Column::buttons()} column.
 *
 * Clicks run {@code tstdt--tanstack-table#rowAction}, which dispatches a bubbling
 * DOM {@code CustomEvent}. The default event name is {@code tanstack-table:row-{id}};
 * override it with {@see event()}.
 *
 * Example:
 *
 * <code>
 * // Edit at the start of the table, delete at the end (blank headers by default).
 * Column::make('edit')->buttons([ColumnButton::edit()]),
 * // … data columns …
 * DeleteButton::create()->url('/items/{id}/delete'),
 *
 * // Or a single actions column:
 * Column::make('actions')
 *     ->label('Actions')
 *     ->buttons([
 *         ColumnButton::edit(),
 *         DeleteButton::create()->url('/items/{id}/delete'),
 *         ColumnButton::make('view')
 *             ->label('Voir')
 *             ->icon('lucide:eye')
 *             ->variant('ghost')
 *             ->event('catalog:view'),
 *     ]),
 * </code>
 *
 * Listen in JS (custom buttons):
 *
 * <code>
 * document.addEventListener('tanstack-table:row-edit', (e) => {
 *     // e.detail: { action, button, row, rowId, config, table }
 * });
 * </code>
 */
final class ColumnButton
{
    private string $label;

    private ?string $icon = null;

    private string $variant = 'outline';

    private string $size = 'icon-sm';

    private ?string $event = null;

    private ?string $url = null;

    private function __construct(
        private readonly string $id,
    ) {
        $this->label = $id;
        $this->event = 'tanstack-table:row-'.$id;
    }

    public static function make(string $id): self
    {
        return new self($id);
    }

    public static function edit(): self
    {
        return self::make('edit')
            ->label('Edit')
            ->icon('lucide:pencil')
            ->variant('outline');
    }

    /**
     * Workspace tab button (opens a tab from {@see WorkspaceTabButton::url()};
     * right-click open/close is always available).
     *
     * Prefer this over {@see edit()} when using AdminLayout workspace tabs.
     */
    public static function workspaceTab(): WorkspaceTabButton
    {
        return WorkspaceTabButton::create();
    }

    /**
     * Delete button (confirm dialog, then POST to {@see DeleteButton::url()} with the row id).
     *
     * Prefer {@see DeleteButton::create()}{@code ->url(...)} so the destination is explicit.
     */
    public static function delete(): DeleteButton
    {
        return DeleteButton::create();
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function variant(string $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function size(string $size): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Custom DOM event name (default {@code tanstack-table:row-{id}}).
     */
    public function event(?string $event): self
    {
        $this->event = $event;

        return $this;
    }

    /**
     * Optional destination URL (supports {@code {id}} / row-field placeholders).
     * When set on a workspace-tab button, the resolved URL is the unique tab id.
     */
    public function url(?string $url): self
    {
        $trimmed = null === $url ? null : trim($url);
        $this->url = '' === $trimmed ? null : $trimmed;

        return $this;
    }

    /**
     * @return array{
     *     id: string,
     *     label: string,
     *     icon: ?string,
     *     variant: string,
     *     size: string,
     *     event: ?string,
     *     url: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'icon' => $this->icon,
            'variant' => $this->variant,
            'size' => $this->size,
            'event' => $this->event,
            'url' => $this->url,
        ];
    }
}
