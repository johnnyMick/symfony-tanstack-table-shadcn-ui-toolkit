<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * Shared contract for datatable QueryBuilder events.
 *
 * Type-hint this (or {@see ApplyJoinEventInterface} / {@see ApplySelectEventInterface} /
 * {@see ApplyFilterEventInterface}) in app subscribers so custom event subclasses remain compatible.
 *
 * @phpstan-type Context array<string, mixed>
 */
interface QueryEventInterface
{
    public function getQueryBuilder(): QueryBuilder;

    public function getField(): string;

    public function getRootAlias(): string;

    /**
     * @return Context
     */
    public function getContext(): array;

    /**
     * Whether the QueryBuilder already has a join (or from) alias.
     */
    public function hasAlias(string $alias): bool;

    /**
     * Mark this field as fully handled so fallback subscribers (defaults) skip work.
     * Prefer this over relying solely on {@see stopPropagation()} when you still want
     * later non-default listeners to observe the event.
     */
    public function markHandled(): void;

    public function isHandled(): bool;

    public function isPropagationStopped(): bool;

    public function stopPropagation(): void;
}
