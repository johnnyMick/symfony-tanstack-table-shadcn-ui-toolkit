<?php

declare(strict_types=1);

namespace App\EventSubscriber\Query;

use App\Controller\CatalogController;
use App\Entity\Product;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\AsTanStackDatatableField;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\TransformRowsEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\AbstractDatatableFieldSubscriber;

/**
 * Maps the products datatable {@code status} column onto boolean {@code Product::$active}
 * for selects and filters. Selects return the raw boolean; {@see transform()} maps labels
 * for JSON via {@see TransformRowsEvent}.
 *
 * Scoped to product JSON endpoints so other Product tables do not pick it up.
 */
#[AsTanStackDatatableField(
    'status',
    entity: Product::class,
    controller: CatalogController::class,
    action: 'productsJson',
)]
#[AsTanStackDatatableField(
    'status',
    entity: Product::class,
    controller: CatalogController::class,
    action: 'categoryProductsJson',
)]
final class ProductStatusFilterSubscriber extends AbstractDatatableFieldSubscriber
{
    protected static function priority(): int
    {
        return 10;
    }

    /**
     * Map a raw DB {@code active} value to the client badge / filter label.
     */
    public static function toClientStatus(mixed $active): string
    {
        return filter_var($active, FILTER_VALIDATE_BOOLEAN) ? 'active' : 'inactive';
    }

    protected function select(ApplySelectEventInterface $event): void
    {
        $alias = $event->getRootAlias();
        $event->getQueryBuilder()->addSelect(\sprintf('%s.active AS status', $alias));
        $event->handleAndStop();
    }

    protected function filter(ApplyFilterEventInterface $event): void
    {
        $qb = $event->getQueryBuilder();
        $parts = $this->normalizeStatusValues($event->getValue());

        $wantActive = \in_array('active', $parts, true);
        $wantInactive = \in_array('inactive', $parts, true);

        if ($wantActive && !$wantInactive) {
            $qb->andWhere($event->getRootAlias().'.active = true');
        } elseif ($wantInactive && !$wantActive) {
            $qb->andWhere($event->getRootAlias().'.active = false');
        } elseif (!$wantActive && !$wantInactive) {
            $qb->andWhere('1 = 0');
        }

        $event->handleAndStop();
    }

    protected function transform(TransformRowsEventInterface $event, string $field): void
    {
        $event->mapColumn($field, self::toClientStatus(...));
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
