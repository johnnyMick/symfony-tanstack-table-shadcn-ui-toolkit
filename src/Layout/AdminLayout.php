<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Layout;

/**
 * Configures a {@see AdminLayout} Twig component from a controller action.
 *
 * @example
 *  $layout = AdminLayout::create()
 *      ->brand(
 *          AdminBrand::create()
 *              ->title('DataTables Demo')
 *              ->subtitle('Admin console')
 *              ->href($this->generateUrl('app_demo'))
 *              ->icon('lucide:table-2')
 *      )
 *      ->nav([
 *          NavGroup::make('Overview')->items([
 *              NavLink::make('Dashboard')
 *                  ->href($this->generateUrl('app_demo'))
 *                  ->icon('lucide:layout-dashboard')
 *                  ->route('app_demo'),
 *          ]),
 *          NavGroup::make('Platform')->items([
 *              NavCollapsible::make('Catalog')
 *                  ->icon('lucide:package')
 *                  ->children([
 *                      NavLink::make('Products')
 *                          ->href($this->generateUrl('app_catalog_products'))
 *                          ->routes(['app_catalog_products', 'app_catalog_products_edit']),
 *                  ]),
 *          ]),
 *      ])
 *      ->user(
 *          AdminUser::create()
 *              ->name('Admin')
 *              ->logoutHref($this->generateUrl('app_logout'))
 *      )
 *      ->mainTabLabel('Products')
 *      ->tabCount(5)
 *      ->nestedTabs()
 *      ->nestedTabCount(7)
 *      ->commandPalette(CommandPalette::create());
 *
 *  return $this->render('products.html.twig', ['layout' => $layout->toArray()]);
 *
 *  Twig: {@code <twig:tstdt:AdminLayout :config="layout">…</twig:tstdt:AdminLayout>}
 *
 *  Workspace tabs open from a button/event URL (the URL is the unique tab id):
 *  {@code workspace-tabs:open} with {@code { url, label? }}.
 *  Nested (level-2) tabs: {@code { url, label?, nested: true }} — active level-1
 *  tab becomes the nested main. Enable/disable with {@see nestedTabs()}; level-2
 *  slot count is independent via {@see nestedTabCount()} (default 7).
 */
final class AdminLayout
{
    /** @var array<string, mixed> */
    private array $brand = [];

    /** @var list<array<string, mixed>> */
    private array $nav = [];

    /** @var array<string, mixed> */
    private array $user = [];

    private int $tabCount = 5;

    private bool $nestedTabs = true;

    /** Level-2 content slots (independent of {@see $tabCount}). */
    private int $nestedTabCount = 7;

    private string $mainTabLabel = 'List';

    private string $workspaceLabel = 'Workspace';

    private string $sidebarLabel = 'Admin navigation';

    private string $id = 'admin-sidebar';

    /** @var array<string, mixed> */
    private array $commandPalette = [];

    private function __construct()
    {
        $this->brand = AdminBrand::create()->toArray();
        $this->user = AdminUser::create()->toArray();
        $this->commandPalette = CommandPalette::create()->toArray();
    }

    public static function create(): self
    {
        return new self();
    }

    public function brand(AdminBrand|array $brand): self
    {
        $this->brand = $brand instanceof AdminBrand ? $brand->toArray() : array_merge($this->brand, $brand);

        return $this;
    }

    /**
     * @param list<NavGroup|array<string, mixed>> $nav
     */
    public function nav(array $nav): self
    {
        $normalized = [];
        foreach ($nav as $group) {
            $normalized[] = $group instanceof NavGroup ? $group->toArray() : $group;
        }
        $this->nav = $normalized;

        return $this;
    }

    public function addNavGroup(NavGroup|array $group): self
    {
        $this->nav[] = $group instanceof NavGroup ? $group->toArray() : $group;

        return $this;
    }

    public function user(AdminUser|array $user): self
    {
        $this->user = $user instanceof AdminUser ? $user->toArray() : array_merge($this->user, $user);

        return $this;
    }

    public function tabCount(int $tabCount): self
    {
        if ($tabCount < 1) {
            throw new \InvalidArgumentException('tabCount must be >= 1.');
        }
        if ($tabCount > 20) {
            throw new \InvalidArgumentException('tabCount must be <= 20.');
        }
        $this->tabCount = $tabCount;

        return $this;
    }

    /**
     * Show the level-2 nested tab strip (active level-1 tab becomes nested main).
     * When disabled, {@code nested: true} opens fall back to level-1 slots.
     */
    public function nestedTabs(bool $enabled = true): self
    {
        $this->nestedTabs = $enabled;

        return $this;
    }

    public function withoutNestedTabs(): self
    {
        return $this->nestedTabs(false);
    }

    /**
     * Max nested (level-2) content slots. Independent of {@see tabCount()} (default 7).
     */
    public function nestedTabCount(int $nestedTabCount): self
    {
        if ($nestedTabCount < 1) {
            throw new \InvalidArgumentException('nestedTabCount must be >= 1.');
        }
        if ($nestedTabCount > 20) {
            throw new \InvalidArgumentException('nestedTabCount must be <= 20.');
        }
        $this->nestedTabCount = $nestedTabCount;

        return $this;
    }

    public function mainTabLabel(string $mainTabLabel): self
    {
        $trimmed = trim($mainTabLabel);
        $this->mainTabLabel = '' === $trimmed ? 'List' : $trimmed;

        return $this;
    }

    public function workspaceLabel(string $workspaceLabel): self
    {
        $this->workspaceLabel = $workspaceLabel;

        return $this;
    }

    public function sidebarLabel(string $sidebarLabel): self
    {
        $this->sidebarLabel = $sidebarLabel;

        return $this;
    }

    public function id(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Workspace command palette (header icon / Ctrl⌘K).
     * Pass {@see CommandPalette::create()->disabled()} to hide it.
     */
    public function commandPalette(CommandPalette|array $commandPalette): self
    {
        $this->commandPalette = $commandPalette instanceof CommandPalette
            ? $commandPalette->toArray()
            : array_replace(CommandPalette::create()->toArray(), $commandPalette);

        return $this;
    }

    public function withoutCommandPalette(): self
    {
        $this->commandPalette = CommandPalette::create()->disabled()->toArray();

        return $this;
    }

    public function getTabCount(): int
    {
        return $this->tabCount;
    }

    public function hasNestedTabs(): bool
    {
        return $this->nestedTabs;
    }

    public function getNestedTabCount(): int
    {
        return $this->nestedTabCount;
    }

    /**
     * Props for {@code <twig:tstdt:AdminLayout :config="layout" />}.
     *
     * @return array{
     *     brand: array<string, mixed>,
     *     nav: list<array<string, mixed>>,
     *     user: array<string, mixed>,
     *     tabCount: int,
     *     nestedTabs: bool,
     *     nestedTabCount: int,
     *     mainTabLabel: string,
     *     workspaceLabel: string,
     *     sidebarLabel: string,
     *     id: string,
     *     commandPalette: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'brand' => $this->brand,
            'nav' => $this->nav,
            'user' => $this->user,
            'tabCount' => $this->tabCount,
            'nestedTabs' => $this->nestedTabs,
            'nestedTabCount' => $this->getNestedTabCount(),
            'mainTabLabel' => $this->mainTabLabel,
            'workspaceLabel' => $this->workspaceLabel,
            'sidebarLabel' => $this->sidebarLabel,
            'id' => $this->id,
            'commandPalette' => $this->commandPalette,
        ];
    }
}
