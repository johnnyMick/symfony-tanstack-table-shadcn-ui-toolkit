<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Layout;

/**
 * Sidebar link (top-level or collapsible child) for {@see AdminLayout}.
 */
final class NavLink
{
    private string $href = '#';

    private ?string $icon = null;

    private ?string $tooltip = null;

    /** @var list<string> */
    private array $routes = [];

    private function __construct(
        private string $label,
    ) {
    }

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function href(string $href): self
    {
        $this->href = $href;

        return $this;
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
     * Route names that mark this link active (and open a parent collapsible).
     *
     * @param list<string>|string $routes
     */
    public function routes(array|string $routes): self
    {
        $this->routes = \is_string($routes) ? [$routes] : array_values($routes);

        return $this;
    }

    public function route(string $route): self
    {
        $this->routes = [$route];

        return $this;
    }

    /**
     * @return array{
     *     type: 'link',
     *     label: string,
     *     href: string,
     *     icon: ?string,
     *     tooltip: ?string,
     *     routes: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => 'link',
            'label' => $this->label,
            'href' => $this->href,
            'icon' => $this->icon,
            'tooltip' => $this->tooltip ?? $this->label,
            'routes' => $this->routes,
        ];
    }
}
