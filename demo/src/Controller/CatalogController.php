<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

#[Route('/catalog')]
final class CatalogController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/products', name: 'app_catalog_products')]
    public function products(): Response
    {
        $table = $this->productsTable();

        return $this->render('catalog/products.html.twig', [
            'table' => $table->toArray(),
        ]);
    }

    #[Route('/products/format/json', name: 'app_catalog_products_json', methods: ['GET'])]
    public function productsJson(Request $request): JsonResponse
    {
        $table = $this->productsTable();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        [$rows, $total] = $this->productRowsPage($query, $table);

        return $table->json($rows, $total, $query->page, $query->pageSize);
    }

    #[Route('/categories', name: 'app_catalog_categories')]
    public function categories(): Response
    {
        $table = $this->categoriesTable();

        return $this->render('catalog/categories.html.twig', [
            'table' => $table->toArray(),
        ]);
    }

    #[Route('/categories/format/json', name: 'app_catalog_categories_json', methods: ['GET'])]
    public function categoriesJson(Request $request): JsonResponse
    {
        $table = $this->categoriesTable();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        [$rows, $total] = $this->categoryRowsPage($query, $table);

        return $table->json($rows, $total, $query->page, $query->pageSize);
    }

    private function productsTable(): TanstackDataTable
    {
        return TanstackDataTable::create()
            ->title('Catalog products')
            ->description('TanStack Table with shadcn styling — server-side pagination via AJAX.')
            ->url($this->generateUrl('app_catalog_products_json'))
            ->pageSize(10)
            ->emptyMessage('No products yet. Reset demo data from Settings.')
            ->columns([
                Column::make('sku')->label('SKU')->field('p.sku')->class('font-mono text-xs'),
                Column::make('name')->label('Name')->field('p.name')->class('font-medium'),
                Column::make('category')->label('Category')->field('c.name'),
                Column::make('price')->label('Price')->field('p.price')->currency(),
                Column::make('stock')->label('Stock')->field('p.stock')->number(),
                // No field(): status is mapped from p.active with custom filter logic.
                Column::make('status')->label('Status')->badge(
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

    private function categoriesTable(): TanstackDataTable
    {
        return TanstackDataTable::create()
            ->title('Catalog categories')
            ->description('Product groups used by the demo catalog.')
            ->url($this->generateUrl('app_catalog_categories_json'))
            ->pageSize(10)
            ->emptyMessage('No categories yet. Reset demo data from Settings.')
            ->columns([
                Column::make('name')->label('Name')->field('c.name')->class('font-medium'),
                Column::make('slug')->label('Slug')->field('c.slug')->class('font-mono text-xs'),
                Column::make('description')->label('Description')->field('c.description')->class('max-w-md truncate text-muted-foreground'),
                // No field(): productCount is aggregated and filtered in memory.
                Column::make('productCount')->label('Products')->number(),
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
    private function productRowsPage(TableQuery $query, TanstackDataTable $table): array
    {
        $fieldMap = $table->fieldMap();

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Product::class, 'p')
            ->leftJoin('p.category', 'c');
        $this->applyProductFilter($countQb, $query, $fieldMap);

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb = $this->em->createQueryBuilder()
            ->select('p', 'c')
            ->from(Product::class, 'p')
            ->leftJoin('p.category', 'c')
            ->orderBy('p.name', 'ASC')
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->pageSize);
        $this->applyProductFilter($qb, $query, $fieldMap);

        /** @var list<Product> $products */
        $products = $qb->getQuery()->getResult();

        $rows = array_map(static fn (Product $product): array => [
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'category' => $product->getCategory()->getName(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'status' => $product->isActive() ? 'active' : 'inactive',
        ], $products);

        return [$rows, $total];
    }

    /**
     * @param array<string, string> $fieldMap
     */
    private function applyProductFilter(QueryBuilder $qb, TableQuery $query, array $fieldMap): void
    {
        if (!$query->hasFilter()) {
            return;
        }

        if ('status' === $query->filterField) {
            $needle = mb_strtolower($query->filter ?? '');
            $active = str_starts_with('active', $needle) || '1' === $needle || 'true' === $needle;
            $inactive = str_starts_with('inactive', $needle) || '0' === $needle || 'false' === $needle;

            if ($active && !$inactive) {
                $qb->andWhere('p.active = true');
            } elseif ($inactive && !$active) {
                $qb->andWhere('p.active = false');
            } elseif (!$active && !$inactive) {
                $qb->andWhere('1 = 0');
            }

            return;
        }

        $query->applyDoctrineFilter($qb, $fieldMap);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function categoryRowsPage(TableQuery $query, TanstackDataTable $table): array
    {
        $fieldMap = $table->fieldMap();

        // productCount is aggregated — filter in memory after fetch when that field is selected.
        if ($query->hasFilter() && 'productCount' === $query->filterField) {
            $categories = $this->em->createQueryBuilder()
                ->select('c', 'COUNT(p.id) AS productCount')
                ->from(Category::class, 'c')
                ->leftJoin('c.products', 'p')
                ->groupBy('c.id')
                ->orderBy('c.name', 'ASC')
                ->getQuery()
                ->getResult();

            $rows = array_map(static function (array $row): array {
                /** @var Category $category */
                $category = $row[0];

                return [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'slug' => $category->getSlug(),
                    'description' => $category->getDescription() ?? '',
                    'productCount' => (int) $row['productCount'],
                ];
            }, $categories);

            return $query->paginateRows($rows);
        }

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Category::class, 'c');
        $query->applyDoctrineFilter($countQb, $fieldMap);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb = $this->em->createQueryBuilder()
            ->select('c', 'COUNT(p.id) AS productCount')
            ->from(Category::class, 'c')
            ->leftJoin('c.products', 'p')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->pageSize);
        $query->applyDoctrineFilter($qb, $fieldMap);

        $categories = $qb->getQuery()->getResult();

        $rows = array_map(static function (array $row): array {
            /** @var Category $category */
            $category = $row[0];

            return [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
                'description' => $category->getDescription() ?? '',
                'productCount' => (int) $row['productCount'],
            ];
        }, $categories);

        return [$rows, $total];
    }
}
