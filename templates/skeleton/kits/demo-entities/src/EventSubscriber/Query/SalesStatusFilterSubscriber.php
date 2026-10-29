<?php

declare(strict_types=1);

namespace App\EventSubscriber\Query;

use App\Controller\SalesController;
use App\Entity\Order;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\AsTanStackDatatableField;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\AbstractDatatableFieldSubscriber;

/**
 * Applies the sales orders datatable {@code status} filter onto {@see Order::$status}.
 *
 * Whitelists values against {@see Order::STATUSES}. Scoped to {@see SalesController::ordersJson}.
 */
#[AsTanStackDatatableField(
    'status',
    entity: Order::class,
    controller: SalesController::class,
    action: 'ordersJson',
)]
final class SalesStatusFilterSubscriber extends AbstractDatatableFieldSubscriber
{
    protected static function priority(): int
    {
        return 10;
    }

    protected function filter(ApplyFilterEventInterface $event): void
    {
        $qb = $event->getQueryBuilder();
        $parts = $this->normalizeStatusValues($event->getValue());
        $allowed = array_values(array_intersect($parts, Order::STATUSES));

        if ([] === $allowed) {
            $qb->andWhere('1 = 0');
            $event->handleAndStop();

            return;
        }

        // All known statuses selected → no restriction needed.
        if (\count($allowed) === \count(Order::STATUSES)) {
            $event->handleAndStop();

            return;
        }

        $param = 'dtf_order_status_'.bin2hex(random_bytes(4));
        $expression = $event->getRootAlias().'.status';

        if (1 === \count($allowed)) {
            $qb->andWhere($qb->expr()->eq($expression, ':'.$param))
                ->setParameter($param, $allowed[0]);
        } else {
            $qb->andWhere($qb->expr()->in($expression, ':'.$param))
                ->setParameter($param, $allowed);
        }

        $event->handleAndStop();
    }

    /**
     * @return list<string>
     */
    private function normalizeStatusValues(mixed $value): array
    {
        if (\is_array($value)) {
            $raw = $value;
        } else {
            $raw = explode(',', (string) $value);
        }

        return array_values(array_filter(array_map(
            static fn (mixed $v): string => mb_strtolower(trim((string) $v)),
            $raw,
        ), static fn (string $v): bool => '' !== $v));
    }
}
