<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query;

use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\AsTanStackDatatableField;

/**
 * Example custom subscriber for computed / virtual fields that are not plain entity columns.
 *
 * Prefer copying this pattern into your app (entity-specific) rather than relying on it globally.
 *
 * Demonstrates:
 * - {@see AsTanStackDatatableField} + {@see AbstractDatatableFieldSubscriber}
 * - Handling {@code fullName} via DQL CONCAT
 * - {@see handleAndStop()} so {@see DefaultSelectSubscriber} does not also run
 * - Prefer {@see Column::join()} / {@code context['joins']} over ad-hoc leftJoin here
 */
#[AsTanStackDatatableField('fullName')]
final class ComputedFieldSubscriber extends AbstractDatatableFieldSubscriber
{
    protected function select(ApplySelectEventInterface $event): void
    {
        $alias = $event->getRootAlias();
        $qb = $event->getQueryBuilder();
        $qb->addSelect(\sprintf(
            "CONCAT(%s.firstName, ' ', %s.lastName) AS fullName",
            $alias,
            $alias,
        ));

        $event->handleAndStop();
    }

    protected function filter(ApplyFilterEventInterface $event): void
    {
        $alias = $event->getRootAlias();
        $qb = $event->getQueryBuilder();
        $expression = \sprintf("CONCAT(%s.firstName, ' ', %s.lastName)", $alias, $alias);

        $operator = $event->getOperator();
        $value = $event->getValue();
        $param = 'dtf_fullName_'.bin2hex(random_bytes(4));

        match ($operator) {
            'eq' => $qb->andWhere($qb->expr()->eq($expression, ':'.$param))
                ->setParameter($param, $value),
            'neq' => $qb->andWhere($qb->expr()->neq($expression, ':'.$param))
                ->setParameter($param, $value),
            'like', 'contains' => $qb->andWhere($qb->expr()->like(
                \sprintf("LOWER(CONCAT(%s, ''))", $expression),
                ':'.$param,
            ))->setParameter($param, '%'.mb_strtolower((string) $value).'%'),
            default => null,
        };

        $event->handleAndStop();
    }
}
