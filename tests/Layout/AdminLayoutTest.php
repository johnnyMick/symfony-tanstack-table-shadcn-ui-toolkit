<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Layout;

use PHPUnit\Framework\TestCase;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminBrand;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminLayout;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminUser;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavCollapsible;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavGroup;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavLink;

final class AdminLayoutTest extends TestCase
{
    public function testToArrayIncludesDefaults(): void
    {
        $config = AdminLayout::create()->toArray();

        self::assertSame('App', $config['brand']['title']);
        self::assertSame('Admin console', $config['brand']['subtitle']);
        self::assertSame([], $config['nav']);
        self::assertSame('Admin', $config['user']['name']);
        self::assertSame('en', $config['user']['locale']);
        self::assertCount(4, $config['user']['languages']);
        self::assertSame('fr', $config['user']['languages'][1]['code']);
        self::assertSame('Français', $config['user']['languages'][1]['label']);
        self::assertArrayNotHasKey('tabUrlPattern', $config);
        self::assertSame(5, $config['tabCount']);
        self::assertTrue($config['nestedTabs']);
        self::assertSame(7, $config['nestedTabCount']);
        self::assertSame('List', $config['mainTabLabel']);
        self::assertSame('Workspace', $config['workspaceLabel']);
        self::assertSame('admin-sidebar', $config['id']);
        self::assertArrayHasKey('commandPalette', $config);
        self::assertTrue($config['commandPalette']['enabled']);
        self::assertNotEmpty($config['commandPalette']['commands']);
    }

    public function testFluentConfigurationSerializes(): void
    {
        $layout = AdminLayout::create()
            ->brand(
                AdminBrand::create()
                    ->title('Demo')
                    ->subtitle('Console')
                    ->href('/demo')
                    ->icon('lucide:table-2')
            )
            ->nav([
                NavGroup::make('Overview')->items([
                    NavLink::make('Dashboard')
                        ->href('/demo')
                        ->icon('lucide:layout-dashboard')
                        ->route('app_demo'),
                ]),
                NavGroup::make('Platform')->items([
                    NavCollapsible::make('Catalog')
                        ->icon('lucide:package')
                        ->children([
                            NavLink::make('Products')
                                ->href('/catalog/products')
                                ->routes(['app_catalog_products', 'app_catalog_products_edit']),
                        ]),
                ]),
            ])
            ->user(
                AdminUser::create()
                    ->name('Ada')
                    ->email('ada@example.test')
                    ->initials('AL')
                    ->logoutHref('/logout')
                    ->withoutMenuItems()
            )
            ->mainTabLabel('Products')
            ->tabCount(3)
            ->workspaceLabel('Desk')
            ->id('demo-shell');

        $config = $layout->toArray();

        self::assertSame('Demo', $config['brand']['title']);
        self::assertSame('/demo', $config['brand']['href']);
        self::assertCount(2, $config['nav']);
        self::assertSame('Overview', $config['nav'][0]['label']);
        self::assertSame('link', $config['nav'][0]['items'][0]['type']);
        self::assertSame(['app_demo'], $config['nav'][0]['items'][0]['routes']);
        self::assertSame('collapsible', $config['nav'][1]['items'][0]['type']);
        self::assertSame(
            ['app_catalog_products', 'app_catalog_products_edit'],
            $config['nav'][1]['items'][0]['routes']
        );
        self::assertSame('Products', $config['nav'][1]['items'][0]['children'][0]['label']);
        self::assertSame('Ada', $config['user']['name']);
        self::assertSame('/logout', $config['user']['logoutHref']);
        self::assertSame([], $config['user']['menuItems']);
        self::assertSame('Products', $config['mainTabLabel']);
        self::assertSame(3, $config['tabCount']);
        self::assertTrue($config['nestedTabs']);
        self::assertSame(7, $config['nestedTabCount']);
        self::assertSame('Desk', $config['workspaceLabel']);
        self::assertSame('demo-shell', $config['id']);
    }

    public function testCollapsibleInfersRoutesFromChildren(): void
    {
        $item = NavCollapsible::make('Sales')
            ->children([
                NavLink::make('Orders')->route('app_sales_orders'),
                NavLink::make('Customers')->route('app_sales_customers'),
            ])
            ->toArray();

        self::assertSame(['app_sales_orders', 'app_sales_customers'], $item['routes']);
    }

    public function testTabCountRejectsOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AdminLayout::create()->tabCount(0);
    }

    public function testTabCountRejectsAboveMax(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AdminLayout::create()->tabCount(21);
    }

    public function testNestedTabsApi(): void
    {
        $layout = AdminLayout::create()->tabCount(4)->nestedTabCount(2);
        self::assertTrue($layout->hasNestedTabs());
        self::assertSame(2, $layout->getNestedTabCount());
        self::assertSame(2, $layout->toArray()['nestedTabCount']);
        self::assertTrue($layout->toArray()['nestedTabs']);

        // Level-2 count stays independent when only level-1 tabCount changes.
        $layout->tabCount(6);
        self::assertSame(2, $layout->getNestedTabCount());

        $disabled = $layout->withoutNestedTabs()->toArray();
        self::assertFalse($layout->hasNestedTabs());
        self::assertFalse($disabled['nestedTabs']);
        self::assertSame(2, $disabled['nestedTabCount']);
    }

    public function testNestedTabCountRejectsOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AdminLayout::create()->nestedTabCount(0);
    }

    public function testNestedTabCountRejectsAboveMax(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AdminLayout::create()->nestedTabCount(21);
    }
}
