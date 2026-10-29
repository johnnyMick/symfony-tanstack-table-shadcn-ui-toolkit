<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Layout;

/**
 * Labeled sidebar group for {@see AdminLayout}.
 */
final class NavGroup
{
    /** @var list<array<string, mixed>> */
    private array $items = [];

    private function __construct(
        private string $label,
    ) {
    }

    public static function make(string $label): self
    {
        return new self($label);
    }

    /**
     * @param list<NavLink|NavCollapsible|array<string, mixed>> $items
     */
    public function items(array $items): self
    {
        $normalized = [];
        foreach ($items as $item) {
            if ($item instanceof NavLink || $item instanceof NavCollapsible) {
                $normalized[] = $item->toArray();
            } else {
                $normalized[] = $item;
            }
        }
        $this->items = $normalized;

        return $this;
    }

    public function add(NavLink|NavCollapsible|array $item): self
    {
        if ($item instanceof NavLink || $item instanceof NavCollapsible) {
            $this->items[] = $item->toArray();
        } else {
            $this->items[] = $item;
        }

        return $this;
    }

    /**
     * @return array{
     *     label: string,
     *     items: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'items' => $this->items,
        ];
    }
}
