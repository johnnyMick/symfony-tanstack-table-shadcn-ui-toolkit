<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Bulk action option for {@see TanstackDataTable} (select box + row checkboxes).
 *
 * Configure in the controller action via {@see TanstackDataTable::bulkActions()}.
 * Non-empty bulk actions enable row checkboxes. The play button dispatches a
 * bubbling DOM {@code CustomEvent} with the selected rows plus {@see url()} and
 * {@see csrfToken()}. Default event name is {@code tanstack-table:bulk-{id}}.
 *
 * Example:
 *
 * <code>
 * $table->bulkActions([
 *     BulkAction::delete()
 *         ->url($this->generateUrl('app_catalog_products_bulk_delete'))
 *         ->csrfToken($csrfTokenManager->getToken('bulk_delete')->getValue()),
 *     BulkAction::freeze()
 *         ->url($this->generateUrl('app_catalog_products_bulk_freeze'))
 *         ->csrfToken($csrfTokenManager->getToken('bulk_freeze')->getValue()),
 * ]);
 * </code>
 *
 * Listen in JS:
 *
 * <code>
 * document.addEventListener('tanstack-table:bulk-delete', (e) => {
 *     // e.detail: { action, event, tableId, url, csrfToken, csrfField, rows, rowIds, config, table }
 * });
 * </code>
 */
final class BulkAction
{
    private string $label;

    private ?string $event = null;

    private ?string $url = null;

    private ?string $csrfToken = null;

    private string $csrfField = '_token';

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
     * Destination URL for the bulk action (included in the play-button event detail).
     */
    public function url(?string $url): self
    {
        $trimmed = null === $url ? null : trim($url);
        $this->url = '' === $trimmed ? null : $trimmed;

        return $this;
    }

    /**
     * CSRF token value included in the play-button event detail
     * (field name from {@see csrfField()}).
     */
    public function csrfToken(?string $csrfToken): self
    {
        $trimmed = null === $csrfToken ? null : trim($csrfToken);
        $this->csrfToken = '' === $trimmed ? null : $trimmed;

        return $this;
    }

    public function csrfField(string $csrfField): self
    {
        $trimmed = trim($csrfField);
        $this->csrfField = '' === $trimmed ? '_token' : $trimmed;

        return $this;
    }

    /**
     * @return array{
     *     id: string,
     *     label: string,
     *     event: ?string,
     *     url: ?string,
     *     csrfToken: ?string,
     *     csrfField: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'event' => $this->event,
            'url' => $this->url,
            'csrfToken' => $this->csrfToken,
            'csrfField' => $this->csrfField,
        ];
    }
}
