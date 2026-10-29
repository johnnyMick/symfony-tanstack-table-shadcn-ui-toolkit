<?php

declare(strict_types=1);

namespace App\EventSubscriber\Query;

use App\Controller\CatalogController;
use App\Entity\Product;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\AsTanStackDatatableField;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\AbstractDatatableFieldSubscriber;

/**
 * Example {@see ApplyJoinEvent} handler for the products {@code category} column.
 *
 * Join hop on the attribute ({@code join} / {@code joinAlias}); scoped to
 * {@see CatalogController::productsJson}. Applies INNER JOIN + {@see handleAndStop()}.
 */
#[AsTanStackDatatableField(
    'category',
    entity: Product::class,
    controller: CatalogController::class,
    action: 'productsJson',
    join: 'p.category',
    joinAlias: 'c',
)]
final class ProductCategoryJoinSubscriber extends AbstractDatatableFieldSubscriber
{
    protected static function priority(): int
    {
        return 10;
    }

    protected function join(ApplyJoinEventInterface $event): void
    {
        $event->getQueryBuilder()->innerJoin(
            $event->getJoinPath(),
            $event->getJoinAlias(),
        );
        $event->setJoinOutcome(ApplyJoinEvent::OUTCOME_APPLIED);
        $event->handleAndStop();
    }
}
