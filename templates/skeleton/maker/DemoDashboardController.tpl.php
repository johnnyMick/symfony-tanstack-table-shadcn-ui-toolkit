<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

<?php if (!empty($with_http_reset)): ?>
use <?= $seeder_fqcn; ?>;
<?php endif; ?>
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
<?php if (!empty($with_http_reset)): ?>
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
<?php endif; ?>
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
<?php if (!empty($with_admin_layout)): ?>
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminBrand;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminLayout;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminUser;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavGroup;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavLink;
<?php endif; ?>

final class <?= $class_name; ?> extends AbstractController
{
    /**
     * DB table name => dashboard card meta.
     * Adjust routes after scaffolding your resource controllers.
     *
     * @var array<string, array{label: string, route: string, icon: string}>
     */
    private const CARDS = [
<?php foreach ($cards as $table => $card): ?>
        '<?= $table; ?>' => [
            'label' => '<?= $card['label']; ?>',
            'route' => '<?= $card['route']; ?>',
            'icon' => '<?= $card['icon']; ?>',
        ],
<?php endforeach; ?>
    ];

    #[Route('<?= $route_path; ?>', name: '<?= $route_name; ?>')]
    public function index(Connection $connection): Response
    {
        return $this->render('<?= $template; ?>', [
<?php if (!empty($with_admin_layout)): ?>
            'layout' => $this->adminLayout()->mainTabLabel('Dashboard')->toArray(),
<?php endif; ?>
            'cards' => $this->buildCards($connection),
        ]);
    }
<?php if (!empty($with_http_reset)): ?>

    /**
     * Local/demo only — disabled in prod (schema wipe). Prefer CLI: app:demo:reset.
     * Synced with the bundle demo DemoController::reset.
     */
    #[Route('/demo/reset', name: 'app_demo_reset', methods: ['POST'], env: ['dev', 'test'])]
    public function reset(Request $request, <?= $seeder_short; ?> $seeder): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('demo_reset', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $counts = $seeder->reset();
        $this->addFlash('success', \sprintf(
            'Demo data reset: %s.',
            implode(', ', array_map(
                static fn (string $table, int $count): string => \sprintf('%s=%s', $table, number_format($count)),
                array_keys($counts),
                array_values($counts),
            )),
        ));

        return $this->redirectToRoute('<?= $reset_redirect_route; ?>');
    }
<?php endif; ?>

    /**
     * @return list<array{label: string, route: string, icon: string, count: int}>
     */
    private function buildCards(Connection $connection): array
    {
        $cards = [];

        foreach (self::CARDS as $table => $meta) {
            try {
                $count = (int) $connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', $table));
            } catch (\Throwable) {
                $count = 0;
            }

            $cards[] = [
                'label' => $meta['label'],
                'route' => $meta['route'],
                'icon' => $meta['icon'],
                'count' => $count,
            ];
        }

        return $cards;
    }
<?php if (!empty($with_admin_layout)): ?>

    private function adminLayout(): AdminLayout
    {
        return AdminLayout::create()
            ->brand(
                AdminBrand::create()
                    ->title('App')
                    ->subtitle('Admin console')
                    ->href($this->generateUrl('<?= $route_name; ?>'))
            )
            ->nav([
                NavGroup::make('Overview')->items([
                    NavLink::make('Dashboard')
                        ->href($this->generateUrl('<?= $route_name; ?>'))
                        ->icon('lucide:layout-dashboard')
                        ->route('<?= $route_name; ?>'),
                ]),
            ])
            ->user(AdminUser::create()->name('Admin'));
    }
<?php endif; ?>
}
