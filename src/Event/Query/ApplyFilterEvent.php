<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

/**
 * Dispatched once per filter when {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier::applyFilters()} runs.
 *
 * Not final — subclass to attach app-specific payload, then override
 * {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier::createFilterEvent()}.
 * Always dispatch on {@see NAME} so bundle + app subscribers on this channel keep working.
 *
 * Expected filter shape (from the frontend / datatable request):
 * {@code ['field' => 'status', 'operator' => 'eq', 'value' => 'published', 'valueTo' => '']}
 *
 * Context keys commonly used by subscribers:
 * - {@code allowed_fields}: list of permitted accessor keys (whitelist)
 * - {@code field_map}: accessorKey => DQL expression (from {@see \SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable::fieldMap()})
 * - {@code entity}: entity FQCN for entity-specific subscribers
 *
 * @phpstan-type Context array<string, mixed>
 */
class ApplyFilterEvent extends AbstractQueryEvent implements ApplyFilterEventInterface
{
    /**
     * Bundle-scoped event name (stable channel FQCN).
     * Subclasses should keep dispatching on this constant, not {@code static::class}.
     */
    public const NAME = self::class;

    /**
     * @param Context $context Extra data for subscribers (e.g. allowed_fields, entity FQCN)
     */
    public function __construct(
        \Doctrine\ORM\QueryBuilder $queryBuilder,
        string $field,
        private readonly string $operator,
        private readonly mixed $value,
        string $rootAlias,
        array $context = [],
        private readonly mixed $valueTo = null,
    ) {
        parent::__construct($queryBuilder, $field, $rootAlias, $context);
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getValueTo(): mixed
    {
        return $this->valueTo;
    }

    public function resolveExpression(): ?string
    {
        return FieldExpression::resolve($this->getField(), $this->getRootAlias(), $this->getContext());
    }
}
