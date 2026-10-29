<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once after scalar rows are fetched
 * ({@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier::transformRows()}).
 *
 * Field subscribers map raw DB values (booleans, enums, …) to client labels via
 * {@see mapColumn()}. Bundle listeners may also format {@see \DateTimeInterface} values.
 *
 * Events are not {@code final}: subclass and override
 * {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier::createTransformRowsEvent()}
 * if you need a richer payload. Keep dispatching on {@see self::NAME}.
 */
class TransformRowsEvent extends Event implements TransformRowsEventInterface
{
    /**
     * Stable event name for the listener channel.
     * Subclasses should keep dispatching on this constant, not {@code static::class}.
     */
    public const NAME = self::class;

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed>       $context
     */
    public function __construct(
        private array $rows,
        private readonly array $context = [],
    ) {
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function setRows(array $rows): void
    {
        $this->rows = array_values($rows);
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function mapColumn(string $field, callable $mapper): void
    {
        foreach ($this->rows as $index => $row) {
            if (!\array_key_exists($field, $row)) {
                continue;
            }
            $this->rows[$index][$field] = $mapper($row[$field], $row);
        }
    }
}
