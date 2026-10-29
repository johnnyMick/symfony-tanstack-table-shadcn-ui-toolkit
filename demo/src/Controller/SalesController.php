<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnBadge;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

#[Route('/sales')]
#[IsGranted('ROLE_ADMIN')]
final class SalesController extends AbstractController
{
    use Concerns\ProvidesAdminLayout;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DatatableQueryApplier $queryApplier,
    ) {
    }

    #[Route('/orders', name: 'app_sales_orders')]
    public function orders(Request $request): Response
    {
        $table = $this->ordersTable();
        if ($this->isWorkspaceTabRequest($request)) {
            return $this->render('sales/_orders.html.twig', [
                'table' => $table->toArray(),
            ]);
        }

        return $this->render('sales/orders.html.twig', [
            'layout' => $this->adminLayout()->mainTabLabel($this->t('Orders'))->toArray(),
        ]);
    }

    #[Route('/orders/format/json', name: 'app_sales_orders_json', methods: ['GET'])]
    public function ordersJson(Request $request): JsonResponse
    {
        $table = $this->ordersTable();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        $context = $table->queryContext(
            Order::class,
            datetimeFormats: ['orderedAt' => 'Y-m-d H:i'],
            controller: self::class,
            action: __FUNCTION__,
        );

        [$rows, $total] = $this->queryApplier->fetchPage(
            fn () => $this->em->createQueryBuilder()->from(Order::class, 'o'),
            $query,
            $context,
            'o.id',
        );

        return $table->json($rows, $total, query: $query);
    }

    #[Route('/customers', name: 'app_sales_customers')]
    public function customers(Request $request): Response
    {
        $table = $this->customersTable();
        if ($this->isWorkspaceTabRequest($request)) {
            return $this->render('sales/_customers.html.twig', [
                'table' => $table->toArray(),
            ]);
        }

        return $this->render('sales/customers.html.twig', [
            'layout' => $this->adminLayout()->mainTabLabel($this->t('Customers'))->toArray(),
        ]);
    }

    #[Route('/customers/format/json', name: 'app_sales_customers_json', methods: ['GET'])]
    public function customersJson(Request $request): JsonResponse
    {
        $table = $this->customersTable();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        $context = $table->queryContext(
            Customer::class,
            datetimeFormats: ['createdAt' => 'Y-m-d'],
            controller: self::class,
            action: __FUNCTION__,
        );

        [$rows, $total] = $this->queryApplier->fetchPage(
            fn () => $this->em->createQueryBuilder()->from(Customer::class, 'c'),
            $query,
            $context,
            'c.id',
        );

        return $table->json($rows, $total, query: $query);
    }

    #[Route('/order-items', name: 'app_sales_order_items')]
    public function orderItems(Request $request): Response
    {
        $table = $this->orderItemsTable();
        if ($this->isWorkspaceTabRequest($request)) {
            return $this->render('sales/_order_items.html.twig', [
                'table' => $table->toArray(),
            ]);
        }

        return $this->render('sales/order_items.html.twig', [
            'layout' => $this->adminLayout()->mainTabLabel($this->t('Order items'))->toArray(),
        ]);
    }

    #[Route('/order-items/format/json', name: 'app_sales_order_items_json', methods: ['GET'])]
    public function orderItemsJson(Request $request): JsonResponse
    {
        $table = $this->orderItemsTable();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        [$rows, $total] = $this->orderItemRowsPage($query, $table);

        return $table->json($rows, $total, query: $query);
    }

    private function ordersTable(): TanstackDataTable
    {
        return TanstackDataTable::create('sales-orders')
            ->title($this->t('Sales orders'))
            ->description($this->t('Shop orders with server-side pagination via AJAX.'))
            ->url($this->generateUrl('app_sales_orders_json'))
            ->pageSize(10)
            ->emptyMessage($this->t('No orders yet. Reset demo data from Settings.'))
            ->columns([
                Column::make('reference')->label($this->t('Reference'))->field('o.reference')->class('font-mono text-xs'),
                // field() for filters; select via OrderCustomerSelectSubscriber (ApplySelect example).
                Column::make('customer')->label($this->t('Customer'))->field("CONCAT(c.firstName, ' ', c.lastName)")->join('o.customer', 'c')->class('font-medium'),
                Column::make('email')->label($this->t('Email'))->field('c.email')->join('o.customer', 'c')->class('text-muted-foreground'),
                // field() for selects; filter handled by SalesStatusFilterSubscriber whitelist.
                Column::make('status')->label($this->t('Status'))->field('o.status')->badge([
                    ColumnBadge::make(Order::STATUS_PENDING)->variant('secondary')->label($this->t('Pending')),
                    ColumnBadge::make(Order::STATUS_PAID)->variant('default')->label($this->t('Paid')),
                    ColumnBadge::make(Order::STATUS_SHIPPED)->variant('outline')->label($this->t('Shipped')),
                    ColumnBadge::make(Order::STATUS_CANCELLED)->variant('destructive')->label($this->t('Cancelled')),
                    ColumnBadge::make(Order::STATUS_REFUNDED)->variant('destructive')->label($this->t('Refunded')),
                ])->filterBadgeChoices(multiple: true),
                Column::make('total')->label($this->t('Total'))->field('o.total')->currency(),
                Column::make('orderedAt')->label($this->t('Ordered at'))->field('o.orderedAt')->class('tabular-nums text-muted-foreground')->filterDate(),
            ]);
    }

    private function customersTable(): TanstackDataTable
    {
        return TanstackDataTable::create('sales-customers')
            ->title($this->t('Customers'))
            ->description($this->t('Shop customers seeded for the demo.'))
            ->url($this->generateUrl('app_sales_customers_json'))
            ->pageSize(10)
            ->emptyMessage($this->t('No customers yet. Reset demo data from Settings.'))
            ->filterPlaceholder($this->t('Filter customers…'))
            ->columns([
                Column::make('name')->label($this->t('Name'))->field("CONCAT(c.firstName, ' ', c.lastName)")->class('font-medium'),
                Column::make('email')->label($this->t('Email'))->field('c.email'),
                Column::make('location')->label($this->t('Location'))->field("CONCAT(c.city, ', ', c.country)"),
                Column::make('createdAt')->label($this->t('Created'))->field('c.createdAt')->class('tabular-nums text-muted-foreground'),
            ]);
    }

    private function orderItemsTable(): TanstackDataTable
    {
        return TanstackDataTable::create('sales-order-items')
            ->title($this->t('Order line items'))
            ->description($this->t('Products sold on each order with quantity and unit price.'))
            ->url($this->generateUrl('app_sales_order_items_json'))
            ->pageSize(10)
            ->emptyMessage($this->t('No order items yet. Reset demo data from Settings.'))
            ->filterPlaceholder($this->t('Filter order items…'))
            ->columns([
                Column::make('reference')->label($this->t('Order'))->field('o.reference')->join('i.order', 'o')->class('font-mono text-xs'),
                Column::make('product')->label($this->t('Product'))->field('p.name')->join('i.product', 'p')->class('font-medium'),
                Column::make('sku')->label($this->t('SKU'))->field('p.sku')->join('i.product', 'p')->class('font-mono text-xs text-muted-foreground'),
                Column::make('quantity')->label($this->t('Qty'))->field('i.quantity')->number(),
                Column::make('unitPrice')->label($this->t('Unit price'))->field('i.unitPrice')->currency(),
                Column::make('lineTotal')->label($this->t('Line total'))->field('i.quantity * i.unitPrice')->currency()->class('font-medium'),
            ]);
    }

    /**
     * lineTotal may be filtered in PHP when that column is the active filter.
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function orderItemRowsPage(TableQuery $query, TanstackDataTable $table): array
    {
        $context = $table->queryContext(
            OrderItem::class,
            controller: self::class,
            action: 'orderItemsJson',
        );

        // lineTotal comparisons are awkward in DQL across DBs — filter/paginate in memory.
        if ($query->hasFilter() && 'lineTotal' === $query->filterField) {
            $qb = $this->em->createQueryBuilder()
                ->from(OrderItem::class, 'i')
                ->orderBy('i.id', 'DESC');

            $unfiltered = new TableQuery(
                1,
                PHP_INT_MAX,
                filters: [],
                fields: $query->hasFields() ? $query->fields : ($context->getAllowedFields() ?? []),
            );
            $rows = $this->queryApplier->fetchScalarRows($qb, $unfiltered, $context, 'i.id');

            return $query->paginateRows($rows);
        }

        return $this->queryApplier->fetchPage(
            fn () => $this->em->createQueryBuilder()->from(OrderItem::class, 'i'),
            $query,
            $context,
            'i.id',
        );
    }
}
