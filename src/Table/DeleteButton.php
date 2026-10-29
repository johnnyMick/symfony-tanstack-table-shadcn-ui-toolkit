<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Special delete column/button: confirms, then POSTs to a destination {@see url()}.
 *
 * Pass the instance directly to {@see TanstackDataTable::columns()} (blank-header
 * action column), or nest it in {@see Column::make()}{@code ->buttons()}.
 *
 * The URL must be set in PHP. Placeholders {@code {id}} / {@code {field}} are filled
 * from row data when rendered. On confirm, a form POST is submitted to that URL
 * with the row {@code id} (and optional CSRF token).
 *
 * Example:
 *
 * <code>
 * DeleteButton::create()
 *     ->url(str_replace('/0/', '/{id}/', $this->generateUrl('app_catalog_products_delete', ['id' => 0])))
 *     ->confirm('Delete “{name}”?')
 *     ->csrfToken($csrfToken),
 * </code>
 */
final class DeleteButton
{
    private string $label = 'Delete';

    private ?string $icon = 'lucide:trash-2';

    private string $variant = 'destructive';

    private string $size = 'icon-sm';

    private ?string $url = null;

    private string $confirm = 'Delete this row?';

    private ?string $csrfToken = null;

    private string $csrfField = '_token';

    private string $idField = 'id';

    private string $columnKey = 'delete';

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

    /**
     * Destination URL for the delete POST. Placeholders {@code {id}}, {@code {name}}, …
     * are replaced from row data when rendered.
     */
    public function url(?string $url): self
    {
        $trimmed = null === $url ? null : trim($url);
        $this->url = '' === $trimmed ? null : $trimmed;

        return $this;
    }

    /**
     * Confirmation dialog text. Supports the same {@code {field}} placeholders as {@see url()}.
     */
    public function confirm(string $message): self
    {
        $trimmed = trim($message);
        $this->confirm = '' === $trimmed ? 'Delete this row?' : $trimmed;

        return $this;
    }

    /**
     * CSRF token value included in the POST body (field name from {@see csrfField()}).
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
     * POST body field that carries the row id (default {@code id}).
     */
    public function idField(string $idField): self
    {
        $trimmed = trim($idField);
        $this->idField = '' === $trimmed ? 'id' : $trimmed;

        return $this;
    }

    /**
     * Accessor key when this button is used as its own column via {@see toColumn()}.
     * Default {@code delete}.
     */
    public function columnKey(string $columnKey): self
    {
        $trimmed = trim($columnKey);
        $this->columnKey = '' === $trimmed ? 'delete' : $trimmed;

        return $this;
    }

    /**
     * Build a blank-header action {@see Column} containing only this button.
     */
    public function toColumn(): Column
    {
        return Column::make($this->columnKey)->alignEnd()->buttons([$this]);
    }

    /**
     * @return array{
     *     id: string,
     *     label: string,
     *     icon: ?string,
     *     variant: string,
     *     size: string,
     *     url: ?string,
     *     confirm: string,
     *     csrfToken: ?string,
     *     csrfField: string,
     *     idField: string,
     *     deleteButton: true
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => 'delete',
            'label' => $this->label,
            'icon' => $this->icon,
            'variant' => $this->variant,
            'size' => $this->size,
            'url' => $this->url,
            'confirm' => $this->confirm,
            'csrfToken' => $this->csrfToken,
            'csrfField' => $this->csrfField,
            'idField' => $this->idField,
            'deleteButton' => true,
        ];
    }
}
