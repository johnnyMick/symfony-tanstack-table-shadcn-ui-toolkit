<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Special header “Add” action: opens a large dialog and loads {@see url()}.
 *
 * Pass via {@see TanstackDataTable::headerActions()} (or {@see HeaderAction::addRow()}).
 * The URL must be set in PHP (typically a create/new form route).
 *
 * Example:
 *
 * <code>
 * $table->headerActions([
 *     HeaderAction::downloadCsv(),
 *     AddButton::create()
 *         ->url($this->generateUrl('app_catalog_products_new'))
 *         ->title('Add product'),
 * ]);
 * </code>
 */
final class AddButton
{
    private string $label = 'Add';

    private ?string $icon = 'lucide:plus';

    private string $variant = 'default';

    private string $size = 'sm';

    private ?string $url = null;

    private string $title = 'Add';

    private string $side = 'right';

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
     * Create-form URL loaded into the large dialog (GET), then posted from the dialog.
     */
    public function url(?string $url): self
    {
        $trimmed = null === $url ? null : trim($url);
        $this->url = '' === $trimmed ? null : $trimmed;

        return $this;
    }

    /**
     * Drawer header title.
     */
    public function title(string $title): self
    {
        $trimmed = trim($title);
        $this->title = '' === $trimmed ? 'Add' : $trimmed;

        return $this;
    }

    /**
     * @deprecated Large dialog is always centered; side is ignored and kept for BC.
     */
    public function side(string $side): self
    {
        $allowed = ['right', 'left', 'top', 'bottom'];
        $normalized = strtolower(trim($side));
        $this->side = \in_array($normalized, $allowed, true) ? $normalized : 'right';

        return $this;
    }

    /**
     * @return array{
     *     id: string,
     *     label: string,
     *     icon: ?string,
     *     variant: string,
     *     size: string,
     *     type: string,
     *     action: string,
     *     event: string,
     *     url: ?string,
     *     title: string,
     *     side: string,
     *     addButton: true
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => 'add-row',
            'label' => $this->label,
            'icon' => $this->icon,
            'variant' => $this->variant,
            'size' => $this->size,
            'type' => 'button',
            'action' => 'click->tstdt--tanstack-table#addAction',
            'event' => 'tanstack-table:add-row',
            'url' => $this->url,
            'title' => $this->title,
            'side' => $this->side,
            'addButton' => true,
        ];
    }
}
