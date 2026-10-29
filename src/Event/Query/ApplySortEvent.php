<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * Dispatched once per sort rule when {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier::applySorts()} runs.
 *
 * @phpstan-type Context array<string, mixed>
 */
class ApplySortEvent extends AbstractQueryEvent implements ApplySortEventInterface
{
    public const NAME = self::class;

    /**
     * @param Context $context
     */
    public function __construct(
        QueryBuilder $queryBuilder,
        string $field,
        private readonly string $direction,
        string $rootAlias,
        array $context = [],
    ) {
        parent::__construct($queryBuilder, $field, $rootAlias, $context);
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function resolveExpression(): ?string
    {
        return FieldExpression::resolve($this->getField(), $this->getRootAlias(), $this->getContext());
    }
}
