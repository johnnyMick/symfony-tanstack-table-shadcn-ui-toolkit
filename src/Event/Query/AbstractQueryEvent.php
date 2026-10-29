<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for datatable QueryBuilder events.
 *
 * Extend this (or {@see ApplyJoinEvent} / {@see ApplySelectEvent} / {@see ApplyFilterEvent}) in your app
 * when you need extra context beyond the default payload. Keep dispatching on the bundle event names
 * ({@see ApplyJoinEvent::NAME} / {@see ApplySelectEvent::NAME} / {@see ApplyFilterEvent::NAME})
 * so bundle subscribers still run.
 *
 * @phpstan-type Context array<string, mixed>
 */
abstract class AbstractQueryEvent extends Event implements QueryEventInterface
{
    private bool $handled = false;

    /**
     * @param Context $context
     */
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly string $field,
        private readonly string $rootAlias,
        private readonly array $context = [],
    ) {
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getRootAlias(): string
    {
        return $this->rootAlias;
    }

    /**
     * @return Context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function hasAlias(string $alias): bool
    {
        return \in_array($alias, $this->queryBuilder->getAllAliases(), true);
    }

    public function markHandled(): void
    {
        $this->handled = true;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    /**
     * Mark handled and stop further listeners (typical custom-field completion).
     */
    public function handleAndStop(): void
    {
        $this->markHandled();
        $this->stopPropagation();
    }
}
