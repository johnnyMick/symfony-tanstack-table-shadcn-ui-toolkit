<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * Dispatched once per required join hop for a field, before that field's
 * {@see ApplySelectEvent} / {@see ApplyFilterEvent} / {@see ApplySortEvent}.
 *
 * Not final — subclass and override
 * {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier::createJoinEvent()}
 * if you need a richer payload. Keep dispatching on {@see self::NAME}.
 *
 * Default handling: {@see \SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\DefaultJoinSubscriber}
 * via {@see \SymfonyTanstackTableShadcnUiToolkit\Query\LeftJoinApplicator}.
 *
 * @phpstan-type Context array<string, mixed>
 */
class ApplyJoinEvent extends AbstractQueryEvent implements ApplyJoinEventInterface
{
    public const NAME = self::class;

    public const OUTCOME_APPLIED = 'applied';
    public const OUTCOME_SKIPPED_ALIAS_PRESENT = 'skipped_alias_present';
    public const OUTCOME_SKIPPED_UNSAFE = 'skipped_unsafe';
    public const OUTCOME_SKIPPED_DEDUPE = 'skipped_dedupe';

    private ?string $joinOutcome = null;

    /**
     * @param Context $context
     */
    public function __construct(
        QueryBuilder $queryBuilder,
        string $field,
        private readonly string $joinAlias,
        private readonly string $joinPath,
        string $rootAlias,
        array $context = [],
        private readonly string $joinType = ApplyJoinEventInterface::TYPE_LEFT,
        private readonly ?string $joinCondition = null,
    ) {
        parent::__construct($queryBuilder, $field, $rootAlias, $context);
    }

    public function getJoinAlias(): string
    {
        return $this->joinAlias;
    }

    public function getJoinPath(): string
    {
        return $this->joinPath;
    }

    public function getJoinType(): string
    {
        return $this->joinType;
    }

    public function getJoinCondition(): ?string
    {
        return $this->joinCondition;
    }

    public function getJoinOutcome(): ?string
    {
        return $this->joinOutcome;
    }

    public function setJoinOutcome(string $outcome): void
    {
        $this->joinOutcome = $outcome;
    }
}
