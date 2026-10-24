<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

#[Route('/settings')]
final class SettingsController extends AbstractController
{
    private const DEMO_TABLES = ['category', 'product', 'customer', 'shop_order', 'order_item'];

    #[Route('/demo-data', name: 'app_settings_demo_data')]
    public function demoData(): Response
    {
        $table = $this->demoDataTable();

        return $this->render('settings/demo_data.html.twig', [
            'table' => $table->toArray(),
        ]);
    }

    #[Route('/demo-data/format/json', name: 'app_settings_demo_data_json', methods: ['GET'])]
    public function demoDataJson(Request $request, Connection $connection): JsonResponse
    {
        $table = $this->demoDataTable();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        [$rows, $total] = $query->paginateRows($this->demoDataRows($connection));

        return $table->json($rows, $total, $query->page, $query->pageSize);
    }

    #[Route('/preferences', name: 'app_settings_preferences')]
    public function preferences(): Response
    {
        return $this->render('settings/preferences.html.twig');
    }

    private function demoDataTable(): TanstackDataTable
    {
        return TanstackDataTable::create()
            ->title('Database tables')
            ->description('Row counts for the seeded demo schema. Reset rebuilds everything from Faker.')
            ->url($this->generateUrl('app_settings_demo_data_json'))
            ->pageSize(10)
            ->emptyMessage('No tables found.')
            ->columns([
                Column::make('table')->label('Table')->class('font-mono text-sm'),
                Column::make('rows')->label('Rows')->number()->class('font-medium'),
            ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function demoDataRows(Connection $connection): array
    {
        $rows = [];

        foreach (self::DEMO_TABLES as $table) {
            try {
                $count = (int) $connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
            } catch (\Throwable) {
                $count = 0;
            }

            $rows[] = [
                'id' => $table,
                'table' => $table,
                'rows' => $count,
            ];
        }

        return $rows;
    }
}
