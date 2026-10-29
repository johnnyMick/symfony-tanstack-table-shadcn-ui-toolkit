<?php

declare(strict_types=1);

namespace App\Controller\Concerns;

use Symfony\Component\HttpFoundation\Request;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminBrand;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminLayout;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminUser;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavCollapsible;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavGroup;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavLink;

/**
 * Shared demo admin shell (brand, nav, account) for controllers that wrap pages in AdminLayout.
 *
 * Requires {@see \Symfony\Bundle\FrameworkBundle\Controller\AbstractController}.
 */
trait ProvidesAdminLayout
{
    use TranslatesUi;

    private function adminLayout(): AdminLayout
    {
        $salesLockIcon = $this->isGranted('ROLE_ADMIN') ? 'lucide:lock-open' : 'lucide:lock';

        return AdminLayout::create()
            ->brand(
                AdminBrand::create()
                    ->title($this->t('DataTables Demo'))
                    ->subtitle($this->t('Admin console'))
                    ->href($this->generateUrl('app_demo'))
                    ->icon('lucide:table-2')
                    ->tooltip($this->t('TanStack Demo'))
            )
            ->nav([
                NavGroup::make($this->t('Overview'))->items([
                    NavLink::make($this->t('Dashboard'))
                        ->href($this->generateUrl('app_demo'))
                        ->icon('lucide:layout-dashboard')
                        ->route('app_demo'),
                ]),
                NavGroup::make($this->t('Platform'))->items([
                    NavCollapsible::make($this->t('Catalog'))
                        ->icon('lucide:package')
                        ->children([
                            NavLink::make($this->t('Products'))
                                ->href($this->generateUrl('app_catalog_products'))
                                ->routes(['app_catalog_products', 'app_catalog_products_edit']),
                            NavLink::make($this->t('Categories'))
                                ->href($this->generateUrl('app_catalog_categories'))
                                ->routes(['app_catalog_categories', 'app_catalog_categories_edit']),
                        ]),
                    NavCollapsible::make($this->t('Sales'))
                        ->icon('lucide:shopping-cart')
                        ->tooltip($this->isGranted('ROLE_ADMIN')
                            ? $this->t('Sales (unlocked)')
                            : $this->t('Sales (sign in required)'))
                        ->children([
                            NavLink::make($this->t('Orders'))
                                ->href($this->generateUrl('app_sales_orders'))
                                ->icon($salesLockIcon)
                                ->route('app_sales_orders'),
                            NavLink::make($this->t('Customers'))
                                ->href($this->generateUrl('app_sales_customers'))
                                ->icon($salesLockIcon)
                                ->route('app_sales_customers'),
                            NavLink::make($this->t('Order items'))
                                ->href($this->generateUrl('app_sales_order_items'))
                                ->icon($salesLockIcon)
                                ->route('app_sales_order_items'),
                        ]),
                    NavCollapsible::make($this->t('Settings'))
                        ->icon('lucide:settings-2')
                        ->children([
                            NavLink::make($this->t('Preferences'))
                                ->href($this->generateUrl('app_settings_preferences'))
                                ->route('app_settings_preferences'),
                        ]),
                ]),
            ])
            ->user($this->adminUser());
    }

    private function adminUser(): AdminUser
    {
        $identifier = $this->getUser()?->getUserIdentifier() ?? 'Guest';
        $initials = strtoupper(substr($identifier, 0, 2));
        $locale = $this->container->get('request_stack')->getCurrentRequest()?->getLocale() ?? 'en';
        $user = AdminUser::create()
            ->name($identifier === 'admin' ? 'Admin' : $identifier)
            ->email($identifier === 'Guest' ? null : sprintf('%s@example.test', $identifier))
            ->initials($initials !== '' ? $initials : 'AD')
            ->tooltip($identifier)
            ->locale($locale)
            ->languages([
                ['code' => 'en', 'label' => 'English', 'href' => $this->generateUrl('app_locale_switch', ['_locale' => 'en'])],
                ['code' => 'fr', 'label' => 'Français', 'href' => $this->generateUrl('app_locale_switch', ['_locale' => 'fr'])],
                ['code' => 'de', 'label' => 'Deutsch', 'href' => $this->generateUrl('app_locale_switch', ['_locale' => 'de'])],
                ['code' => 'it', 'label' => 'Italiano', 'href' => $this->generateUrl('app_locale_switch', ['_locale' => 'it'])],
            ])
            ->menuItems([
                ['label' => 'Profile', 'icon' => 'lucide:user'],
                ['label' => 'Notifications', 'icon' => 'lucide:bell'],
            ]);

        if ($this->getUser()) {
            $user->logoutHref($this->generateUrl('app_logout'));
        } else {
            $user
                ->logoutLabel('Sign in')
                ->logoutHref($this->generateUrl('app_login'));
        }

        return $user;
    }

    /** True when the workspace tabs controller is fetching panel HTML. */
    private function isWorkspaceTabRequest(Request $request): bool
    {
        return '1' === $request->headers->get('X-Workspace-Tab')
            || '1' === $request->request->get('_workspace_tab')
            || '1' === $request->query->get('_workspace_tab')
            || $request->isXmlHttpRequest();
    }
}
