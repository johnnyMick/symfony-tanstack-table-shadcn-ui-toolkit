<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Layout;

/**
 * Collapsible sidebar section with child {@see NavLink}s for {@see AdminLayout}.
 */
final class NavCollapsible
{
    private ?string $icon = null;

    private ?string $tooltip = null;

    /** @var list<string> */
    private array $routes = [];

    /** @var list<array<string, mixed>> */
    private array $children = [];

    private function __construct(
        private string $label,
    ) {
    }

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function tooltip(?string $tooltip): self
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    /**
     * Route names that expand this group (usually union of child routes).
     *
     * @param list<string>|string $routes
     */
    public function routes(array|string $routes): self
    {
        $this->routes = \is_string($routes) ? [$routes] : array_values($routes);

        return $this;
    }

    /**
     * @param list<NavLink|array<string, mixed>> $children
     */
    public function children(array $children): self
    {
        $normalized = [];
        $inferredRoutes = [];
        foreach ($children as $child) {
            $item = $child instanceof NavLink ? $child->toArray() : $child;
            $normalized[] = $item;
            foreach ($item['routes'] ?? [] as $route) {
                if (\is_string($route) && '' !== $route) {
                    $inferredRoutes[] = $route;
                }
            }
        }
        $this->children = $normalized;
        if ([] === $this->routes && [] !== $inferredRoutes) {
            $this->routes = array_values(array_unique($inferredRoutes));
        }

        return $this;
    }

    /**
     * @return array{
     *     type: 'collapsible',
     *     label: string,
     *     icon: ?string,
     *     tooltip: ?string,
     *     routes: list<string>,
     *     children: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => 'collapsible',
            'label' => $this->label,
            'icon' => $this->icon,
            'tooltip' => $this->tooltip ?? $this->label,
            'routes' => $this->routes,
            'children' => $this->children,
        ];
    }
}
