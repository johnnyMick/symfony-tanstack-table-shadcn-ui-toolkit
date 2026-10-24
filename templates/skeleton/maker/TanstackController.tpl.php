<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

<?php if ($with_doctrine): ?>
<?php foreach ($resources as $resource): ?>
use <?= $resource['entity_fqcn']; ?>;
<?php endforeach; ?>
use Doctrine\ORM\EntityManagerInterface;
<?php endif; ?>
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

#[Route('<?= $route_prefix; ?>')]
final class <?= $class_name; ?> extends AbstractController
{
<?php if ($with_doctrine): ?>
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

<?php endif; ?>
<?php foreach ($resources as $resource): ?>
    #[Route('/<?= $resource['route_segment']; ?>', name: '<?= $resource['route_name']; ?>')]
    public function <?= $resource['method']; ?>(): Response
    {
        $table = $this-><?= $resource['table_method']; ?>();

        return $this->render('<?= $resource['template']; ?>', [
            'table' => $table->toArray(),
        ]);
    }

    #[Route('/<?= $resource['route_segment']; ?>/format/json', name: '<?= $resource['route_name_json']; ?>', methods: ['GET'])]
    public function <?= $resource['json_method']; ?>(Request $request): JsonResponse
    {
        $table = $this-><?= $resource['table_method']; ?>();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        [$rows, $total] = $this-><?= $resource['rows_method']; ?>($query);

        return $table->json($rows, $total, $query->page, $query->pageSize);
    }

<?php endforeach; ?>
<?php foreach ($resources as $resource): ?>
    private function <?= $resource['table_method']; ?>(): TanstackDataTable
    {
        return TanstackDataTable::create()
            ->title('<?= $resource['table_title']; ?>')
            ->description('<?= $resource['table_description']; ?>')
            ->url($this->generateUrl('<?= $resource['route_name_json']; ?>'))
            ->pageSize(10)
            ->emptyMessage('<?= $resource['empty_message']; ?>')
            ->columns([
                Column::make('id')->label('ID')->field('e.id')->class('font-mono text-xs'),
                Column::make('name')->label('Name')->field('e.name')->class('font-medium'),
                Column::make('status')->label('Status')->field('e.status')->badge(
                    ['active' => 'default', 'inactive' => 'secondary'],
                    ['active' => 'Active', 'inactive' => 'Inactive'],
                ),
                Column::make('actions')
                    ->label('Actions')
                    ->alignEnd()
                    ->buttons([
                        ColumnButton::edit(),
                        ColumnButton::delete(),
                    ]),
            ]);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function <?= $resource['rows_method']; ?>(TableQuery $query): array
    {
<?php if ($with_doctrine): ?>
        $fieldMap = $this-><?= $resource['table_method']; ?>()->fieldMap();

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(<?= $resource['entity_short']; ?>::class, 'e');
        $query->applyDoctrineFilter($countQb, $fieldMap);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(<?= $resource['entity_short']; ?>::class, 'e')
            ->orderBy('e.id', 'ASC')
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->pageSize);
        $query->applyDoctrineFilter($qb, $fieldMap);

        /** @var list<<?= $resource['entity_short']; ?>> $items */
        $items = $qb->getQuery()->getResult();

        $rows = array_map(static fn (<?= $resource['entity_short']; ?> $item): array => [
            'id' => $item->getId(),
            // TODO: map entity getters to column accessorKeys
            'name' => method_exists($item, 'getName') ? $item->getName() : (string) $item->getId(),
            'status' => method_exists($item, 'isActive') ? ($item->isActive() ? 'active' : 'inactive') : 'active',
        ], $items);

        return [$rows, $total];
<?php else: ?>
        // TODO: replace with your data source (Doctrine, API, etc.)
        $allRows = [
            ['id' => 1, 'name' => 'Sample <?= $resource['singular_label']; ?>', 'status' => 'active'],
            ['id' => 2, 'name' => 'Another <?= $resource['singular_label']; ?>', 'status' => 'inactive'],
        ];

        return $query->paginateRows($allRows);
<?php endif; ?>
    }

<?php endforeach; ?>
}
