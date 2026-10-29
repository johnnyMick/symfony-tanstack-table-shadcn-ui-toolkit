<?php

declare(strict_types=1);

namespace App\EventSubscriber\Query;

use App\Controller\SalesController;
use App\Entity\Order;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\AsTanStackDatatableField;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\AbstractDatatableFieldSubscriber;

/**
 * Example {@see ApplySelectEvent} handler for the orders {@code customer} column.
 *
 * Scoped to {@see SalesController::ordersJson}. Select is owned here; filters still use
 * {@code Column::field()} CONCAT via {@code field_map}.
 */
#[AsTanStackDatatableField(
    'customer',
    entity: Order::class,
    controller: SalesController::class,
    action: 'ordersJson',
)]
final class OrderCustomerSelectSubscriber extends AbstractDatatableFieldSubscriber
{
    protected static function priority(): int
    {
        return 10;
    }

    protected function select(ApplySelectEventInterface $event): void
    {
        $event->getQueryBuilder()->addSelect(
            "CONCAT(c.firstName, ' ', c.lastName) AS customer",
        );
        $event->handleAndStop();
    }
}
