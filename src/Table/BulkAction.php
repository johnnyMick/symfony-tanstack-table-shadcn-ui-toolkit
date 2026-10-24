<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Bulk action option for {@see TanstackDataTable} (select box + row checkboxes).
 *
 * Choosing an option runs {@code tanstack-table#bulkAction}, which dispatches a
 * bubbling DOM {@code CustomEvent}. Default event name is {@code tanstack-table:bulk-{id}}.
 *
 * Example:
 *
 * <code>
 * Defaults are set on {@see TanstackDataTable::create()}. Override with:
 *
 * <code>
 * $table->bulkActions([
 *     BulkAction::delete(),
 *     BulkAction::update(),
 *     BulkAction::freeze(),
 *     BulkAction::unfreeze(),
 * ]);
 * </code>
 *
 * Listen in JS:
 *
 * <code>
 * document.addEventListener('tanstack-table:bulk-delete', (e) => {
 *     // e.detail.rows = full selected row objects
 *     // e.detail: { action, rows, rowIds, config, table }
 * });
 * </code>
 */
final class BulkAction
{
    private string $label;

    private ?string $event = null;

    private function __construct(
        private readonly string $id,
    ) {
        $this->label = $id;
        $this->event = 'tanstack-table:bulk-'.$id;
    }

    public static function make(string $id): self
    {
        return new self($id);
    }

    public static function delete(): self
    {
        return self::make('delete')->label('Delete');
    }

    public static function update(): self
    {
        return self::make('update')->label('Update');
    }

    public static function freeze(): self
    {
        return self::make('freeze')->label('Freeze');
    }

    public static function unfreeze(): self
    {
        return self::make('unfreeze')->label('Unfreeze');
    }

    public static function export(): self
    {
        return self::make('export')->label('Export');
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Custom DOM event name (default {@code tanstack-table:bulk-{id}}).
     */
    public function event(?string $event): self
    {
        $this->event = $event;

        return $this;
    }

    /**
     * @return array{id: string, label: string, event: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'event' => $this->event,
        ];
    }
}
