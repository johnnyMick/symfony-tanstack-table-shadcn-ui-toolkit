<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Special workspace-tab column/button: opens a tab for a destination {@see url()}.
 *
 * Pass the instance directly to {@see TanstackDataTable::columns()} (it becomes a
 * blank-header action column). For a general-purpose {@code tab} column that mixes
 * other buttons, use {@see Column::make()}{@code ->buttons()} instead.
 *
 * The resolved URL is the unique tab id. Placeholders {@code {id}} / {@code {field}}
 * are filled from row data when rendered. Right-click always offers open/close.
 *
 * Example:
 *
 * <code>
 * // Special workspace-tab column (preferred):
 * WorkspaceTabButton::create()
 *     ->url('/catalog/products/{id}/edit'),
 *
 * // Or general-purpose column with any buttons:
 * Column::make('tab')->buttons([
 *     WorkspaceTabButton::create()->url('/items/{id}'),
 *     ColumnButton::make('copy')->label('Copy'),
 * ]),
 * </code>
 */
final class WorkspaceTabButton
{
    private string $label = 'Open';

    private ?string $icon = 'lucide:panel-top';

    private string $variant = 'outline';

    private string $size = 'icon-sm';

    private string $event = 'workspace-tabs:open';

    private ?string $url = null;

    private string $columnKey = 'tab';

    /** When true, opens in the level-2 nested strip under the active workspace tab. */
    private bool $nested = false;

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
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

    public function event(string $event): self
    {
        $this->event = $event;

        return $this;
    }

    /**
     * Destination URL for the workspace tab. Placeholders {@code {id}}, {@code {name}}, …
     * are replaced from row data when rendered. The resolved URL is the unique tab id.
     */
    public function url(?string $url): self
    {
        $trimmed = null === $url ? null : trim($url);
        $this->url = '' === $trimmed ? null : $trimmed;

        return $this;
    }

    /**
     * Open in the level-2 nested tab strip (active level-1 tab becomes nested main).
     * Nested slot count is configured separately via {@see \SymfonyTanstackTableShadcnUiToolkit\Layout\AdminLayout::nestedTabCount()}.
     */
    public function nested(bool $nested = true): self
    {
        $this->nested = $nested;

        return $this;
    }

    /**
     * Accessor key when this button is used as its own column via {@see toColumn()}.
     * Default {@code tab}.
     */
    public function columnKey(string $columnKey): self
    {
        $trimmed = trim($columnKey);
        $this->columnKey = '' === $trimmed ? 'tab' : $trimmed;

        return $this;
    }

    /**
     * Build a blank-header action {@see Column} containing only this button.
     */
    public function toColumn(): Column
    {
        return Column::make($this->columnKey)->buttons([$this]);
    }

    /**
     * @return array{
     *     id: string,
     *     label: string,
     *     icon: ?string,
     *     variant: string,
     *     size: string,
     *     event: string,
     *     url: ?string,
     *     workspaceTab: true,
     *     contextMenu: true,
     *     nested: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => 'tab',
            'label' => $this->label,
            'icon' => $this->icon,
            'variant' => $this->variant,
            'size' => $this->size,
            'event' => $this->event,
            'url' => $this->url,
            'workspaceTab' => true,
            'contextMenu' => true,
            'nested' => $this->nested,
        ];
    }
}
