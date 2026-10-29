<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Layout;

/**
 * Sidebar brand block for {@see AdminLayout}.
 */
final class AdminBrand
{
    private string $title = 'App';

    private ?string $subtitle = 'Admin console';

    private string $href = '#';

    private string $icon = 'lucide:table-2';

    private ?string $tooltip = null;

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function subtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function href(string $href): self
    {
        $this->href = $href;

        return $this;
    }

    public function icon(string $icon): self
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
     * @return array{
     *     title: string,
     *     subtitle: ?string,
     *     href: string,
     *     icon: string,
     *     tooltip: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'href' => $this->href,
            'icon' => $this->icon,
            'tooltip' => $this->tooltip ?? $this->title,
        ];
    }
}
