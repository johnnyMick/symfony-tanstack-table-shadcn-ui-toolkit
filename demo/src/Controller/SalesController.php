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
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

#[Route('/sales')]
final class SalesController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/orders', name: 'app_sales_orders')]
    public function orders(): Response
    {
        $table = $this->ordersTable();

        return $this->render('sales/orders.html.twig', [
            'table' => $table->toArray(),
        ]);
    }

    #[Route('/orders/format/json', name: 'app_sales_orders_json', methods: ['GET'])]
    public function ordersJson(Request $request): JsonResponse
    {
        $table = $this->ordersTable();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        [$rows, $total] = $this->orderRowsPage($query, $table);

        return $table->json($rows, $total, $query->page, $query->pageSize);
    }

    #[Route('/customers', name: 'app_sales_customers')]
    public function customers(): Response
    {
        $table = $this->customersTable();

        return $this->render('sales/customers.html.twig', [
            'table' => $table->toArray(),
        ]);
    }

    #[Route('/customers/format/json', name: 'app_sales_customers_json', methods: ['GET'])]
    public function customersJson(Request $request): JsonResponse
    {
        $table = $this->customersTable();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        [$rows, $total] = $this->customerRowsPage($query, $table);

        return $table->json($rows, $total, $query->page, $query->pageSize);
    }

    #[Route('/order-items', name: 'app_sales_order_items')]
    public function orderItems(): Response
    {
        $table = $this->orderItemsTable();

        return $this->render('sales/order_items.html.twig', [
            'table' => $table->toArray(),
        ]);
    }

    #[Route('/order-items/format/json', name: 'app_sales_order_items_json', methods: ['GET'])]
    public function orderItemsJson(Request $request): JsonResponse
    {
        $table = $this->orderItemsTable();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        [$rows, $total] = $this->orderItemRowsPage($query, $table);

        return $table->json($rows, $total, $query->page, $query->pageSize);
    }

    private function ordersTable(): TanstackDataTable
    {
        return TanstackDataTable::create()
            ->title('Sales orders')
            ->description('Shop orders with server-side pagination via AJAX.')
            ->url($this->generateUrl('app_sales_orders_json'))
            ->pageSize(10)
            ->emptyMessage('No orders yet. Reset demo data from Settings.')
            ->columns([
                Column::make('reference')->label('Reference')->field('o.reference')->class('font-mono text-xs'),
                Column::make('customer')->label('Customer')->field("CONCAT(c.firstName, ' ', c.lastName)")->class('font-medium'),
                Column::make('email')->label('Email')->field('c.email')->class('text-muted-foreground'),
                Column::make('status')->label('Status')->field('o.status')->badge([
                    'pending' => 'secondary',
                    'paid' => 'default',
                    'shipped' => 'outline',
                    'cancelled' => 'destructive',
                    'refunded' => 'destructive',
                ]),
                Column::make('total')->label('Total')->field('o.total')->currency(),
                Column::make('orderedAt')->label('Ordered at')->field('o.orderedAt')->class('tabular-nums text-muted-foreground'),
            ]);
    }

    private function customersTable(): TanstackDataTable
    {
        return TanstackDataTable::create()
            ->title('Customers')
            ->description('Shop customers seeded for the demo.')
            ->url($this->generateUrl('app_sales_customers_json'))
            ->pageSize(10)
            ->emptyMessage('No customers yet. Reset demo data from Settings.')
            ->filterPlaceholder('Filter customers…')
            ->columns([
                Column::make('name')->label('Name')->field("CONCAT(c.firstName, ' ', c.lastName)")->class('font-medium'),
                Column::make('email')->label('Email')->field('c.email'),
                Column::make('location')->label('Location')->field("CONCAT(c.city, ', ', c.country)"),
                Column::make('createdAt')->label('Created')->field('c.createdAt')->class('tabular-nums text-muted-foreground'),
            ])
            ->withoutBulkActions();
    }

    private function orderItemsTable(): TanstackDataTable
    {
        return TanstackDataTable::create()
            ->title('Order line items')
            ->description('Products sold on each order with quantity and unit price.')
            ->url($this->generateUrl('app_sales_order_items_json'))
            ->pageSize(10)
            ->emptyMessage('No order items yet. Reset demo data from Settings.')
            ->filterPlaceholder('Filter order items…')
            ->columns([
                Column::make('reference')->label('Order')->field('o.reference')->class('font-mono text-xs'),
                Column::make('product')->label('Product')->field('p.name')->class('font-medium'),
                Column::make('sku')->label('SKU')->field('p.sku')->class('font-mono text-xs text-muted-foreground'),
                Column::make('quantity')->label('Qty')->field('i.quantity')->number(),
                Column::make('unitPrice')->label('Unit price')->field('i.unitPrice')->currency(),
                // No field(): lineTotal is computed and filtered in memory.
                Column::make('lineTotal')->label('Line total')->currency()->class('font-medium'),
            ]);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function orderRowsPage(TableQuery $query, TanstackDataTable $table): array
    {
        $fieldMap = $table->fieldMap();

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Order::class, 'o')
            ->leftJoin('o.customer', 'c');
        $query->applyDoctrineFilter($countQb, $fieldMap);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb = $this->em->createQueryBuilder()
            ->select('o', 'c')
            ->from(Order::class, 'o')
            ->leftJoin('o.customer', 'c')
            ->orderBy('o.orderedAt', 'DESC')
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->pageSize);
        $query->applyDoctrineFilter($qb, $fieldMap);

        /** @var list<Order> $orders */
        $orders = $qb->getQuery()->getResult();

        $rows = array_map(static fn (Order $order): array => [
            'id' => $order->getId(),
            'reference' => $order->getReference(),
            'customer' => $order->getCustomer()->getFirstName().' '.$order->getCustomer()->getLastName(),
            'email' => $order->getCustomer()->getEmail(),
            'status' => $order->getStatus(),
            'total' => $order->getTotal(),
            'orderedAt' => $order->getOrderedAt()->format('Y-m-d H:i'),
        ], $orders);

        return [$rows, $total];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function customerRowsPage(TableQuery $query, TanstackDataTable $table): array
    {
        $fieldMap = $table->fieldMap();

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Customer::class, 'c');
        $query->applyDoctrineFilter($countQb, $fieldMap);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->orderBy('c.lastName', 'ASC')
            ->addOrderBy('c.firstName', 'ASC')
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->pageSize);
        $query->applyDoctrineFilter($qb, $fieldMap);

        /** @var list<Customer> $customers */
        $customers = $qb->getQuery()->getResult();

        $rows = array_map(static fn (Customer $customer): array => [
            'id' => $customer->getId(),
            'name' => $customer->getFirstName().' '.$customer->getLastName(),
            'email' => $customer->getEmail(),
            'location' => $customer->getCity().', '.$customer->getCountry(),
            'createdAt' => $customer->getCreatedAt()->format('Y-m-d'),
        ], $customers);

        return [$rows, $total];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function orderItemRowsPage(TableQuery $query, TanstackDataTable $table): array
    {
        // lineTotal is computed — filter/paginate in PHP when searching that column.
        if ($query->hasFilter() && 'lineTotal' === $query->filterField) {
            /** @var list<OrderItem> $items */
            $items = $this->em->createQueryBuilder()
                ->select('i', 'o', 'p')
                ->from(OrderItem::class, 'i')
                ->leftJoin('i.order', 'o')
                ->leftJoin('i.product', 'p')
                ->orderBy('i.id', 'DESC')
                ->getQuery()
                ->getResult();

            $rows = array_map(static fn (OrderItem $item): array => [
                'id' => $item->getId(),
                'reference' => $item->getOrder()->getReference(),
                'product' => $item->getProduct()->getName(),
                'sku' => $item->getProduct()->getSku(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
                'lineTotal' => round((float) $item->getUnitPrice() * $item->getQuantity(), 2),
            ], $items);

            return $query->paginateRows($rows);
        }

        $fieldMap = $table->fieldMap();

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(OrderItem::class, 'i')
            ->leftJoin('i.order', 'o')
            ->leftJoin('i.product', 'p');
        $query->applyDoctrineFilter($countQb, $fieldMap);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb = $this->em->createQueryBuilder()
            ->select('i', 'o', 'p')
            ->from(OrderItem::class, 'i')
            ->leftJoin('i.order', 'o')
            ->leftJoin('i.product', 'p')
            ->orderBy('i.id', 'DESC')
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->pageSize);
        $query->applyDoctrineFilter($qb, $fieldMap);

        /** @var list<OrderItem> $items */
        $items = $qb->getQuery()->getResult();

        $rows = array_map(static fn (OrderItem $item): array => [
            'id' => $item->getId(),
            'reference' => $item->getOrder()->getReference(),
            'product' => $item->getProduct()->getName(),
            'sku' => $item->getProduct()->getSku(),
            'quantity' => $item->getQuantity(),
            'unitPrice' => $item->getUnitPrice(),
            'lineTotal' => round((float) $item->getUnitPrice() * $item->getQuantity(), 2),
        ], $items);

        return [$rows, $total];
    }
}
