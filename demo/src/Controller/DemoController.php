<?php

declare(strict_types=1);

namespace App\Controller;

use App\Datatable\DemoSeeder;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DemoController extends AbstractController
{
    use Concerns\ProvidesAdminLayout;

    #[Route('/', name: 'app_demo')]
    public function index(Request $request, Connection $connection): Response
    {
        $counts = $this->tableCounts($connection);
        if ($this->isWorkspaceTabRequest($request)) {
            return $this->render('demo/_dashboard.html.twig', [
                'counts' => $counts,
            ]);
        }

        return $this->render('demo/index.html.twig', [
            'layout' => $this->adminLayout()->mainTabLabel($this->t('Dashboard'))->toArray(),
        ]);
    }

    /**
     * Local/demo only — disabled in prod (schema wipe). Prefer CLI: app:demo:reset.
     */
    #[Route('/demo/reset', name: 'app_demo_reset', methods: ['POST'], env: ['dev', 'test'])]
    public function reset(Request $request, DemoSeeder $seeder): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('demo_reset', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException($this->t('Invalid CSRF token.'));
        }

        $counts = $seeder->reset();
        $this->addFlash('success', $this->t('Demo data reset: %summary%.', [
            '%summary%' => implode(', ', array_map(
                static fn (string $table, int $count): string => sprintf('%s=%s', $table, number_format($count)),
                array_keys($counts),
                array_values($counts),
            )),
        ]));

        return $this->redirectToRoute('app_settings_demo_data');
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(Connection $connection): array
    {
        $tables = ['category', 'product', 'customer', 'shop_order', 'order_item'];
        $counts = [];

        foreach ($tables as $table) {
            try {
                $counts[$table] = (int) $connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
            } catch (\Throwable) {
                $counts[$table] = 0;
            }
        }

        return $counts;
    }
}
