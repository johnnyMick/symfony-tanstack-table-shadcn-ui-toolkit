<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

/**
 * Mutate scalar row arrays after Doctrine {@code getArrayResult()} and before JSON.
 *
 * Apps may subclass {@see TransformRowsEvent} or implement this interface for a custom
 * event dispatched via an extended {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier}.
 */
interface TransformRowsEventInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getRows(): array;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function setRows(array $rows): void;

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array;

    /**
     * Rewrite one column across all rows that contain the key.
     *
     * @param callable(mixed, array<string, mixed>): mixed $mapper
     */
    public function mapColumn(string $field, callable $mapper): void;
}
