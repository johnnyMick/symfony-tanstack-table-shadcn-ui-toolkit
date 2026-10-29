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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use SymfonyTanstackTableShadcnUiToolkit\Query\QueryContext;
use SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier;
use SymfonyTanstackTableShadcnUiToolkit\Table\AddButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\BulkAction;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnSwitch;
use SymfonyTanstackTableShadcnUiToolkit\Table\DeleteButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\HeaderAction;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;
use SymfonyTanstackTableShadcnUiToolkit\Table\WorkspaceTabButton;

#[Route('/catalog')]
final class CatalogController extends AbstractController
{
    use Concerns\ProvidesAdminLayout;

    /** Reusable edit placeholder tabs in the workspace (main list tab is separate). */
    private const WORKSPACE_TAB_COUNT = 5;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly DatatableQueryApplier $queryApplier,
    ) {
    }

    #[Route('/products', name: 'app_catalog_products')]
    public function products(Request $request): Response
    {
        $table = $this->productsTable();
        if ($this->isWorkspaceTabRequest($request)) {
            return $this->render('catalog/_products.html.twig', [
                'table' => $table->toArray(),
            ]);
        }

        $layout = $this->adminLayout()
            ->mainTabLabel($this->t('Products'))
            ->tabCount(self::WORKSPACE_TAB_COUNT);

        return $this->render('catalog/products.html.twig', [
            'layout' => $layout->toArray(),
        ]);
    }

    #[Route('/products/format/json', name: 'app_catalog_products_json', methods: ['GET'])]
    public function productsJson(Request $request): JsonResponse
    {
        $table = $this->productsTable();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        $context = $table->queryContext(
            Product::class,
            extraAllowed: ['status'],
            datetimeFormats: ['createdAt' => 'Y-m-d'],
            controller: self::class,
            action: __FUNCTION__,
        );

        [$rows, $total] = $this->queryApplier->fetchPage(
            fn () => $this->em->createQueryBuilder()->from(Product::class, 'p'),
            $query,
            $context,
            'p.id',
        );

        return $table->json($rows, $total, query: $query);
    }

    #[Route('/products/{id}/edit', name: 'app_catalog_products_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function productEdit(Request $request, Product $product): Response
    {
        $categories = $this->em->getRepository(Category::class)->findBy([], ['name' => 'ASC']);
        $categoriesTable = $this->productEditCategoriesTable($product);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('product_edit_'.$product->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException($this->t('Invalid CSRF token.'));
            }

            $categoryId = (int) $request->request->get('category');
            $category = $this->em->getRepository(Category::class)->find($categoryId);
            if (!$category instanceof Category) {
                throw $this->createNotFoundException($this->t('Category not found.'));
            }

            $product
                ->setName(trim((string) $request->request->get('name')))
                ->setSku(trim((string) $request->request->get('sku')))
                ->setCategory($category)
                ->setPrice((string) $request->request->get('price'))
                ->setStock((int) $request->request->get('stock'))
                ->setActive($request->request->has('active'));

            $this->em->flush();
            $this->addFlash('success', $this->t('Product “%name%” saved.', ['%name%' => $product->getName()]));

            if ($this->isWorkspaceTabRequest($request)) {
                return $this->render('catalog/_product_edit.html.twig', [
                    'product' => $product,
                    'categories' => $categories,
                    'categories_table' => $categoriesTable->toArray(),
                ]);
            }

            return $this->redirectToRoute('app_catalog_products_edit', ['id' => $product->getId()]);
        }

        if ($this->isWorkspaceTabRequest($request)) {
            return $this->render('catalog/_product_edit.html.twig', [
                'product' => $product,
                'categories' => $categories,
                'categories_table' => $categoriesTable->toArray(),
            ]);
        }

        $layout = $this->adminLayout()
            ->mainTabLabel($this->t('Products'))
            ->tabCount(self::WORKSPACE_TAB_COUNT);

        return $this->render('catalog/product_edit.html.twig', [
            'layout' => $layout->toArray(),
            'product' => $product,
        ]);
    }

    #[Route('/products/{id}/delete', name: 'app_catalog_products_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function productDelete(Request $request, Product $product): Response
    {
        if (!$this->isCsrfTokenValid('delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException($this->t('Invalid CSRF token.'));
        }

        $name = $product->getName();
        $this->em->remove($product);
        $this->em->flush();
        $this->addFlash('success', $this->t('Product “%name%” deleted.', ['%name%' => $name]));

        return $this->redirectToRoute('app_catalog_products');
    }

    #[Route('/products/{id}/status', name: 'app_catalog_products_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function productStatus(Request $request, Product $product): JsonResponse
    {
        if (!$this->isCsrfTokenValid('product_status', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException($this->t('Invalid CSRF token.'));
        }

        $raw = mb_strtolower(trim((string) $request->request->get('value', '')));
        if (\in_array($raw, ['1', 'true', 'yes', 'active', 'on'], true)) {
            $active = true;
        } elseif (\in_array($raw, ['0', 'false', 'no', 'inactive', 'off'], true)) {
            $active = false;
        } else {
            $active = !$product->isActive();
        }

        $product->setActive($active);
        $this->em->flush();

        return new JsonResponse([
            'ok' => true,
            'id' => $product->getId(),
            'status' => $active ? 'active' : 'inactive',
            'active' => $active,
        ]);
    }

    #[Route('/products/bulk-delete', name: 'app_catalog_products_bulk_delete', methods: ['POST'])]
    public function productsBulkDelete(Request $request): JsonResponse
    {
        return $this->bulkActionResponse($request, 'bulk_delete', 'delete');
    }

    #[Route('/products/bulk-update', name: 'app_catalog_products_bulk_update', methods: ['POST'])]
    public function productsBulkUpdate(Request $request): JsonResponse
    {
        return $this->bulkActionResponse($request, 'bulk_update', 'update');
    }

    #[Route('/products/bulk-freeze', name: 'app_catalog_products_bulk_freeze', methods: ['POST'])]
    public function productsBulkFreeze(Request $request): JsonResponse
    {
        return $this->bulkActionResponse($request, 'bulk_freeze', 'freeze');
    }

    #[Route('/products/bulk-unfreeze', name: 'app_catalog_products_bulk_unfreeze', methods: ['POST'])]
    public function productsBulkUnfreeze(Request $request): JsonResponse
    {
        return $this->bulkActionResponse($request, 'bulk_unfreeze', 'unfreeze');
    }

    #[Route('/products/new', name: 'app_catalog_products_new', methods: ['GET', 'POST'])]
    public function productNew(Request $request): Response
    {
        $categories = $this->em->getRepository(Category::class)->findBy([], ['name' => 'ASC']);
        $values = [
            'name' => '',
            'sku' => '',
            'category' => null,
            'price' => '0',
            'stock' => '0',
            'active' => true,
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('product_new', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException($this->t('Invalid CSRF token.'));
            }

            $values = [
                'name' => trim((string) $request->request->get('name')),
                'sku' => trim((string) $request->request->get('sku')),
                'category' => (int) $request->request->get('category') ?: null,
                'price' => (string) $request->request->get('price'),
                'stock' => (string) $request->request->get('stock'),
                'active' => $request->request->has('active'),
            ];

            $category = $values['category']
                ? $this->em->getRepository(Category::class)->find($values['category'])
                : null;
            if (!$category instanceof Category) {
                $this->addFlash('error', $this->t('Choose a category.'));

                return $this->render('catalog/_product_new.html.twig', [
                    'categories' => $categories,
                    'values' => $values,
                ]);
            }

            $product = (new Product())
                ->setName($values['name'])
                ->setSku($values['sku'])
                ->setCategory($category)
                ->setPrice($values['price'])
                ->setStock((int) $values['stock'])
                ->setActive($values['active'])
                ->setCreatedAt(new \DateTimeImmutable());

            $this->em->persist($product);
            $this->em->flush();
            $this->addFlash('success', $this->t('Product “%name%” created.', ['%name%' => $product->getName()]));

            return $this->redirectToRoute('app_catalog_products');
        }

        return $this->render('catalog/_product_new.html.twig', [
            'categories' => $categories,
            'values' => $values,
        ]);
    }

    #[Route('/categories', name: 'app_catalog_categories')]
    public function categories(Request $request): Response
    {
        $table = $this->categoriesTable();
        if ($this->isWorkspaceTabRequest($request)) {
            return $this->render('catalog/_categories.html.twig', [
                'table' => $table->toArray(),
            ]);
        }

        $layout = $this->adminLayout()
            ->mainTabLabel($this->t('Categories'))
            ->tabCount(self::WORKSPACE_TAB_COUNT);

        return $this->render('catalog/categories.html.twig', [
            'layout' => $layout->toArray(),
        ]);
    }

    #[Route('/categories/format/json', name: 'app_catalog_categories_json', methods: ['GET'])]
    public function categoriesJson(Request $request): JsonResponse
    {
        $table = $this->categoriesTable();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        [$rows, $total] = $this->categoryRowsPage($query, $table);

        return $table->json($rows, $total, query: $query);
    }

    #[Route('/categories/{id}/edit', name: 'app_catalog_categories_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function categoryEdit(Request $request, Category $category): Response
    {
        $productsTable = $this->categoryEditProductsTable($category);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('category_edit_'.$category->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException($this->t('Invalid CSRF token.'));
            }

            $category
                ->setName(trim((string) $request->request->get('name')))
                ->setSlug(trim((string) $request->request->get('slug')))
                ->setDescription(trim((string) $request->request->get('description')) ?: null);

            $this->em->flush();
            $this->addFlash('success', $this->t('Category “%name%” saved.', ['%name%' => $category->getName()]));

            if ($this->isWorkspaceTabRequest($request)) {
                return $this->render('catalog/_category_edit.html.twig', [
                    'category' => $category,
                    'products_table' => $productsTable->toArray(),
                ]);
            }

            return $this->redirectToRoute('app_catalog_categories_edit', ['id' => $category->getId()]);
        }

        if ($this->isWorkspaceTabRequest($request)) {
            return $this->render('catalog/_category_edit.html.twig', [
                'category' => $category,
                'products_table' => $productsTable->toArray(),
            ]);
        }

        $layout = $this->adminLayout()
            ->mainTabLabel($this->t('Categories'))
            ->tabCount(self::WORKSPACE_TAB_COUNT);

        return $this->render('catalog/category_edit.html.twig', [
            'layout' => $layout->toArray(),
            'category' => $category,
        ]);
    }

    #[Route('/categories/{id}/products/format/json', name: 'app_catalog_category_products_json', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function categoryProductsJson(Request $request, Category $category): JsonResponse
    {
        $table = $this->categoryEditProductsTable($category);
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        $context = $table->queryContext(
            Product::class,
            extraAllowed: ['status'],
            datetimeFormats: ['createdAt' => 'Y-m-d'],
            controller: self::class,
            action: __FUNCTION__,
        );

        $categoryId = (int) $category->getId();
        [$rows, $total] = $this->queryApplier->fetchPage(
            fn (): QueryBuilder => $this->em->createQueryBuilder()
                ->from(Product::class, 'p')
                ->andWhere('IDENTITY(p.category) = :categoryId')
                ->setParameter('categoryId', $categoryId),
            $query,
            $context,
            'p.id',
        );

        return $table->json($rows, $total, query: $query);
    }

    #[Route('/categories/bulk-delete', name: 'app_catalog_categories_bulk_delete', methods: ['POST'])]
    public function categoriesBulkDelete(Request $request): JsonResponse
    {
        return $this->bulkActionResponse($request, 'bulk_delete', 'delete');
    }

    #[Route('/categories/{id}/delete', name: 'app_catalog_categories_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function categoryDelete(Request $request, Category $category): Response
    {
        if (!$this->isCsrfTokenValid('delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException($this->t('Invalid CSRF token.'));
        }

        $name = $category->getName();
        $this->em->remove($category);
        $this->em->flush();
        $this->addFlash('success', $this->t('Category “%name%” deleted.', ['%name%' => $name]));

        return $this->redirectToRoute('app_catalog_categories');
    }

    #[Route('/categories/new', name: 'app_catalog_categories_new', methods: ['GET', 'POST'])]
    public function categoryNew(Request $request): Response
    {
        $values = [
            'name' => '',
            'slug' => '',
            'description' => '',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('category_new', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException($this->t('Invalid CSRF token.'));
            }

            $values = [
                'name' => trim((string) $request->request->get('name')),
                'slug' => trim((string) $request->request->get('slug')),
                'description' => trim((string) $request->request->get('description')),
            ];

            $category = (new Category())
                ->setName($values['name'])
                ->setSlug($values['slug'])
                ->setDescription('' !== $values['description'] ? $values['description'] : null);

            $this->em->persist($category);
            $this->em->flush();
            $this->addFlash('success', $this->t('Category “%name%” created.', ['%name%' => $category->getName()]));

            return $this->redirectToRoute('app_catalog_categories');
        }

        return $this->render('catalog/_category_new.html.twig', [
            'values' => $values,
        ]);
    }

    /**
     * Demo stub for bulk actions: validates CSRF and echoes selected ids.
     * The Stimulus play button dispatches a DOM event with url / csrf / rowIds;
     * wire a JS listener to POST here.
     *
     * @return JsonResponse
     */
    private function bulkActionResponse(Request $request, string $csrfId, string $action): JsonResponse
    {
        if (!$this->isCsrfTokenValid($csrfId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException($this->t('Invalid CSRF token.'));
        }

        $ids = array_values(array_filter(
            array_map('intval', (array) $request->request->all('ids')),
            static fn (int $id): bool => $id > 0,
        ));

        return new JsonResponse([
            'ok' => true,
            'action' => $action,
            'ids' => $ids,
        ]);
    }

    private function productsTable(): TanstackDataTable
    {
        return TanstackDataTable::create('catalog-products')
            ->title($this->t('Catalog products'))
            ->description($this->t('TanStack Table with shadcn styling — server-side pagination via AJAX.'))
            ->url($this->generateUrl('app_catalog_products_json'))
            ->pageSize(10)
            ->emptyMessage($this->t('No products yet. Reset demo data from Settings.'))
            ->tabTitle('{name} · {category}')
            ->opensTab()
            ->headerActions([
                HeaderAction::downloadCsv()->label($this->t('Download CSV')),
                AddButton::create()
                    ->label($this->t('Add'))
                    ->url($this->generateUrl('app_catalog_products_new'))
                    ->title($this->t('Add product')),
            ])
            ->bulkActions([
                BulkAction::delete()
                    ->label($this->t('Delete'))
                    ->url($this->generateUrl('app_catalog_products_bulk_delete'))
                    ->csrfToken($this->csrfTokenManager->getToken('bulk_delete')->getValue()),
                BulkAction::update()
                    ->label($this->t('Update'))
                    ->url($this->generateUrl('app_catalog_products_bulk_update'))
                    ->csrfToken($this->csrfTokenManager->getToken('bulk_update')->getValue()),
                BulkAction::freeze()
                    ->label($this->t('Freeze'))
                    ->url($this->generateUrl('app_catalog_products_bulk_freeze'))
                    ->csrfToken($this->csrfTokenManager->getToken('bulk_freeze')->getValue()),
                BulkAction::unfreeze()
                    ->label($this->t('Unfreeze'))
                    ->url($this->generateUrl('app_catalog_products_bulk_unfreeze'))
                    ->csrfToken($this->csrfTokenManager->getToken('bulk_unfreeze')->getValue()),
            ])
            ->columns([
                WorkspaceTabButton::create()
                    ->label($this->t('Open'))
                    ->url(str_replace('/0/', '/{id}/', $this->generateUrl('app_catalog_products_edit', ['id' => 0]))),
                Column::make('sku')->label($this->t('SKU'))->field('p.sku')->class('font-mono text-xs')->filterString(),
                Column::make('name')->label($this->t('Name'))->field('p.name')->class('font-medium')->filterString(),
                // Join hop on ProductCategoryJoinSubscriber attribute (no Column::join()).
                Column::make('category')->label($this->t('Category'))->field('c.name')->filterString(),
                Column::make('price')->label($this->t('Price'))->field('p.price')->currency()->filterNumber(),
                Column::make('stock')->label($this->t('Stock'))->field('p.stock')->number()->filterNumber(),
                // No field(): status select/filter/transform via ProductStatusFilterSubscriber.
                ColumnSwitch::make('status')
                    ->label($this->t('Status'))
                    ->alignCenter()
                    ->url(str_replace('/0/', '/{id}/', $this->generateUrl('app_catalog_products_status', ['id' => 0])))
                    ->csrfToken($this->csrfTokenManager->getToken('product_status')->getValue())
                    ->values('active', 'inactive', $this->t('Active'), $this->t('Inactive')),
                Column::make('createdAt')->label($this->t('Created'))->field('p.createdAt')->filterDateRange()->defaultHidden(),
                DeleteButton::create()
                    ->label($this->t('Delete'))
                    ->url(str_replace('/0/', '/{id}/', $this->generateUrl('app_catalog_products_delete', ['id' => 0])))
                    ->confirm($this->t('Delete product “{name}”?'))
                    ->csrfToken($this->csrfTokenManager->getToken('delete')->getValue()),
            ]);
    }

    private function categoriesTable(): TanstackDataTable
    {
        return TanstackDataTable::create('catalog-categories')
            ->title($this->t('Catalog categories'))
            ->description($this->t('Product groups used by the demo catalog.'))
            ->url($this->generateUrl('app_catalog_categories_json'))
            ->pageSize(10)
            ->emptyMessage($this->t('No categories yet. Reset demo data from Settings.'))
            ->tabTitle('{name}')
            ->opensTab()
            ->headerActions([
                HeaderAction::downloadCsv()->label($this->t('Download CSV')),
                AddButton::create()
                    ->label($this->t('Add'))
                    ->url($this->generateUrl('app_catalog_categories_new'))
                    ->title($this->t('Add category')),
            ])
            ->bulkActions([
                BulkAction::delete()
                    ->label($this->t('Delete'))
                    ->url($this->generateUrl('app_catalog_categories_bulk_delete'))
                    ->csrfToken($this->csrfTokenManager->getToken('bulk_delete')->getValue()),
            ])
            ->columns([
                WorkspaceTabButton::create()
                    ->label($this->t('Open'))
                    ->url(str_replace('/0/', '/{id}/', $this->generateUrl('app_catalog_categories_edit', ['id' => 0]))),
                Column::make('name')->label($this->t('Name'))->field('c.name')->class('font-medium'),
                Column::make('slug')->label($this->t('Slug'))->field('c.slug')->class('font-mono text-xs'),
                Column::make('description')->label($this->t('Description'))->field('c.description')->class('max-w-md truncate text-muted-foreground')->defaultHidden(),
                // No field(): productCount is aggregated and filtered in memory.
                Column::make('productCount')->label($this->t('Products'))->number(),
                DeleteButton::create()
                    ->label($this->t('Delete'))
                    ->url(str_replace('/0/', '/{id}/', $this->generateUrl('app_catalog_categories_delete', ['id' => 0])))
                    ->confirm($this->t('Delete category “{name}”?'))
                    ->csrfToken($this->csrfTokenManager->getToken('delete')->getValue()),
            ]);
    }

    /** Categories list embedded on the product edit page (opens in level-2 nested tabs). */
    private function productEditCategoriesTable(Product $product): TanstackDataTable
    {
        $productId = (int) $product->getId();

        return TanstackDataTable::create('product-'.$productId.'-categories')
            ->title($this->t('Categories'))
            ->description($this->t('Open a category in a nested tab under this product.'))
            ->url($this->generateUrl('app_catalog_categories_json'))
            ->pageSize(10)
            ->emptyMessage($this->t('No categories yet. Reset demo data from Settings.'))
            ->tabTitle('{name}')
            ->opensTab()
            ->columns([
                WorkspaceTabButton::create()
                    ->label($this->t('Open'))
                    ->url(str_replace('/0/', '/{id}/', $this->generateUrl('app_catalog_categories_edit', ['id' => 0])))
                    ->nested(),
                Column::make('name')->label($this->t('Name'))->field('c.name')->class('font-medium'),
                Column::make('slug')->label($this->t('Slug'))->field('c.slug')->class('font-mono text-xs'),
                Column::make('productCount')->label($this->t('Products'))->number(),
            ]);
    }

    /** Products in this category, embedded on the category edit page (opens in level-2 nested tabs). */
    private function categoryEditProductsTable(Category $category): TanstackDataTable
    {
        $categoryId = (int) $category->getId();

        return TanstackDataTable::create('category-'.$categoryId.'-products')
            ->title($this->t('Products'))
            ->description($this->t('Open a product in a nested tab under this category.'))
            ->url($this->generateUrl('app_catalog_category_products_json', ['id' => $categoryId]))
            ->pageSize(10)
            ->emptyMessage($this->t('No products in this category yet.'))
            ->tabTitle('{name}')
            ->opensTab()
            ->columns([
                WorkspaceTabButton::create()
                    ->label($this->t('Open'))
                    ->url(str_replace('/0/', '/{id}/', $this->generateUrl('app_catalog_products_edit', ['id' => 0])))
                    ->nested(),
                Column::make('sku')->label($this->t('SKU'))->field('p.sku')->class('font-mono text-xs')->filterString(),
                Column::make('name')->label($this->t('Name'))->field('p.name')->class('font-medium')->filterString(),
                Column::make('price')->label($this->t('Price'))->field('p.price')->currency()->filterNumber(),
                Column::make('stock')->label($this->t('Stock'))->field('p.stock')->number()->filterNumber(),
                ColumnSwitch::make('status')
                    ->label($this->t('Status'))
                    ->alignCenter()
                    ->url(str_replace('/0/', '/{id}/', $this->generateUrl('app_catalog_products_status', ['id' => 0])))
                    ->csrfToken($this->csrfTokenManager->getToken('product_status')->getValue())
                    ->values('active', 'inactive', $this->t('Active'), $this->t('Inactive')),
            ]);
    }

    /**
     * productCount is aggregated — custom select + optional in-memory filter.
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function categoryRowsPage(TableQuery $query, TanstackDataTable $table): array
    {
        $context = $table->queryContext(
            Category::class,
            extraAllowed: ['productCount'],
            controller: self::class,
        );

        if ($query->hasFilter() && 'productCount' === $query->filterField) {
            $qb = $this->em->createQueryBuilder()
                ->from(Category::class, 'c')
                ->leftJoin('c.products', 'p')
                ->groupBy('c.id')
                ->orderBy('c.name', 'ASC');

            $fields = $query->hasFields() ? $query->fields : ($context->getAllowedFields() ?? []);
            $rows = $this->fetchCategoryScalarRows($qb, $fields, $context, withFilters: false);

            return $query->paginateRows($rows);
        }

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Category::class, 'c');
        if ($query->hasFilter()) {
            $this->queryApplier->applyFilters($countQb, $query->filters, $context);
        }
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb = $this->em->createQueryBuilder()
            ->from(Category::class, 'c')
            ->leftJoin('c.products', 'p')
            ->groupBy('c.id')
            ->orderBy('c.name', 'ASC')
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->pageSize);

        $fields = $query->hasFields() ? $query->fields : ($context->getAllowedFields() ?? []);
        $rows = $this->fetchCategoryScalarRows($qb, $fields, $context, withFilters: $query->hasFilter(), filters: $query->filters);

        return [$rows, $total];
    }

    /**
     * @param list<string>                            $fields
     * @param list<array<string, mixed>>|array<mixed> $filters
     *
     * @return list<array<string, mixed>>
     */
    private function fetchCategoryScalarRows(
        QueryBuilder $qb,
        array $fields,
        QueryContext $context,
        bool $withFilters,
        array $filters = [],
    ): array {
        $qb->select('c.id AS id');
        if (\in_array('productCount', $fields, true)) {
            $qb->addSelect('COUNT(p.id) AS productCount');
        }

        $selectFields = array_values(array_filter(
            $fields,
            static fn (string $field): bool => !\in_array($field, ['id', 'productCount'], true),
        ));
        if ([] !== $selectFields) {
            $this->queryApplier->applySelects($qb, $selectFields, $context);
        }
        if ($withFilters && [] !== $filters) {
            $this->queryApplier->applyFilters($qb, $filters, $context);
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $qb->getQuery()->getArrayResult();
        $rows = array_map(static function (array $row): array {
            if (isset($row['productCount'])) {
                $row['productCount'] = (int) $row['productCount'];
            }
            if (\array_key_exists('description', $row) && null === $row['description']) {
                $row['description'] = '';
            }

            return $row;
        }, $rows);

        return $this->queryApplier->transformRows($rows, $context);
    }
}
