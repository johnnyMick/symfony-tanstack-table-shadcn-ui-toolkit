<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Query;

use Doctrine\ORM\QueryBuilder;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEventInterface;

/**
 * Applies one relation join from an {@see ApplyJoinEventInterface} onto the QueryBuilder.
 *
 * Supports {@code left} (default) and {@code inner}, plus an optional WITH condition.
 */
final class LeftJoinApplicator
{
    /** Association path: {@code alias.assoc} or nested {@code alias.a.b}. */
    private const SAFE_PATH = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)+$/';

    /** Join alias: letters, digits, underscore. */
    private const SAFE_ALIAS = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    public function apply(ApplyJoinEventInterface $event): void
    {
        if ($event->isPropagationStopped() || $event->isHandled()) {
            return;
        }

        $alias = $event->getJoinAlias();
        $path = $event->getJoinPath();

        if (!$this->isSafeAlias($alias) || !$this->isSafePath($path)) {
            $event->setJoinOutcome(ApplyJoinEvent::OUTCOME_SKIPPED_UNSAFE);
            $event->markHandled();

            return;
        }

        if ($event->hasAlias($alias)) {
            $event->setJoinOutcome(ApplyJoinEvent::OUTCOME_SKIPPED_ALIAS_PRESENT);
            $event->handleAndStop();

            return;
        }

        $this->addJoin(
            $event->getQueryBuilder(),
            $path,
            $alias,
            strtolower($event->getJoinType()),
            $event->getJoinCondition(),
        );

        $event->setJoinOutcome(ApplyJoinEvent::OUTCOME_APPLIED);
        $event->handleAndStop();
    }

    private function isSafeAlias(string $alias): bool
    {
        return 1 === preg_match(self::SAFE_ALIAS, $alias);
    }

    private function isSafePath(string $path): bool
    {
        return 1 === preg_match(self::SAFE_PATH, $path);
    }

    private function addJoin(
        QueryBuilder $qb,
        string $path,
        string $alias,
        string $type,
        ?string $condition,
    ): void {
        $hasCondition = null !== $condition && '' !== $condition;
        $useInner = ApplyJoinEventInterface::TYPE_INNER === $type;

        if ($useInner && $hasCondition) {
            $qb->innerJoin($path, $alias, 'WITH', $condition);

            return;
        }

        if ($useInner) {
            $qb->innerJoin($path, $alias);

            return;
        }

        if ($hasCondition) {
            $qb->leftJoin($path, $alias, 'WITH', $condition);

            return;
        }

        $qb->leftJoin($path, $alias);
    }
}
