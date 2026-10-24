<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Fluent row action button for a {@see Column::buttons()} column.
 *
 * Clicks run {@code tanstack-table#rowAction}, which dispatches a bubbling
 * DOM {@code CustomEvent}. The default event name is {@code tanstack-table:row-{id}};
 * override it with {@see event()}.
 *
 * Example:
 *
 * <code>
 * Column::make('actions')
 *     ->label('Actions')
 *     ->buttons([
 *         ColumnButton::edit(),
 *         ColumnButton::delete(),
 *         ColumnButton::make('view')
 *             ->label('Voir')
 *             ->icon('lucide:eye')
 *             ->variant('ghost')
 *             ->event('catalog:view'),
 *     ]),
 * </code>
 *
 * Listen in JS:
 *
 * <code>
 * document.addEventListener('tanstack-table:row-edit', (e) => {
 *     // e.detail: { action, button, row, rowId, config, table }
 * });
 * document.addEventListener('tanstack-table:row-delete', (e) => {
 *     // handle delete using e.detail.row
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

    public static function delete(): self
    {
        return self::make('delete')
            ->label('Delete')
            ->icon('lucide:trash-2')
            ->variant('destructive');
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
     * @return array{
     *     id: string,
     *     label: string,
     *     icon: ?string,
     *     variant: string,
     *     size: string,
     *     event: ?string
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
        ];
    }
}
