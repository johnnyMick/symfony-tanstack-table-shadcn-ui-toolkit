<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEventInterface;

/**
 * Fallback filter handler (low priority): applies common operators with {@code andWhere}
 * + unique named parameters when no higher-priority subscriber handled the filter.
 *
 * Supported operators (applier + TableQuery aliases):
 * eq/equals, neq, like/contains, startswith, endswith, gt/after, lt/before, gte, lte,
 * between, in, is_null, is_not_null.
 *
 * Unknown operators are ignored (fail closed).
 *
 * When {@code context['field_map']} is set, the DQL expression comes from the map
 * (accessorKey => expression). Otherwise {@code rootAlias.field} is used for safe names.
 *
 * WHITELIST: Prefer {@code context['allowed_fields']} via {@see FieldWhitelistSubscriber}.
 */
final class DefaultFilterSubscriber implements EventSubscriberInterface
{
    private int $parameterSequence = 0;

    public static function getSubscribedEvents(): array
    {
        return [
            ApplyFilterEvent::NAME => ['onApplyFilter', -100],
        ];
    }

    private const KNOWN_OPERATORS = [
        'eq', 'equals', 'neq', 'gt', 'after', 'lt', 'before', 'gte', 'lte',
        'like', 'contains', 'startswith', 'endswith', 'between', 'in',
        'is_null', 'is_not_null',
    ];

    public function onApplyFilter(ApplyFilterEventInterface $event): void
    {
        if ($event->isPropagationStopped() || $event->isHandled()) {
            return;
        }

        $expression = $event->resolveExpression();
        if (null === $expression) {
            return;
        }

        $operator = $event->getOperator();
        if (!\in_array($operator, self::KNOWN_OPERATORS, true)) {
            return;
        }

        $this->applyOperator(
            $event->getQueryBuilder(),
            $expression,
            $operator,
            $event->getValue(),
            $event->getValueTo(),
        );
        $event->markHandled();
    }

    private function applyOperator(
        QueryBuilder $qb,
        string $expression,
        string $operator,
        mixed $value,
        mixed $valueTo,
    ): void {
        match ($operator) {
            'eq', 'equals' => $this->equalsIgnoreCase($qb, $expression, $value),
            'neq' => $this->compare($qb, $expression, 'neq', $value),
            'gt', 'after' => $this->compare($qb, $expression, 'gt', $this->castComparable($value)),
            'lt', 'before' => $this->compare($qb, $expression, 'lt', $this->castComparable($value)),
            'gte' => $this->compare($qb, $expression, 'gte', $this->castComparable($value)),
            'lte' => $this->compare($qb, $expression, 'lte', $this->castComparable($value)),
            'like', 'contains' => $this->like($qb, $expression, $value, 'contains'),
            'startswith' => $this->like($qb, $expression, $value, 'startswith'),
            'endswith' => $this->like($qb, $expression, $value, 'endswith'),
            'between' => $this->between($qb, $expression, $value, $valueTo),
            'in' => $this->in($qb, $expression, $value),
            'is_null' => $qb->andWhere($qb->expr()->isNull($expression)),
            'is_not_null' => $qb->andWhere($qb->expr()->isNotNull($expression)),
            default => null,
        };
    }

    /** Case-insensitive equality (matches TableQuery string operators). */
    private function equalsIgnoreCase(QueryBuilder $qb, string $expression, mixed $value): void
    {
        $param = $this->nextParameterName();
        $qb->andWhere($qb->expr()->eq(
            \sprintf("LOWER(CONCAT(%s, ''))", $expression),
            ':'.$param,
        ))->setParameter($param, mb_strtolower((string) $value));
    }

    private function compare(QueryBuilder $qb, string $expression, string $op, mixed $value): void
    {
        $param = $this->nextParameterName();
        $expr = $qb->expr();

        $where = match ($op) {
            'eq' => $expr->eq($expression, ':'.$param),
            'neq' => $expr->neq($expression, ':'.$param),
            'gt' => $expr->gt($expression, ':'.$param),
            'lt' => $expr->lt($expression, ':'.$param),
            'gte' => $expr->gte($expression, ':'.$param),
            'lte' => $expr->lte($expression, ':'.$param),
            default => null,
        };

        if (null === $where) {
            return;
        }

        $qb->andWhere($where)->setParameter($param, $value);
    }

    /**
     * @param 'contains'|'startswith'|'endswith' $mode
     */
    private function like(QueryBuilder $qb, string $expression, mixed $value, string $mode): void
    {
        $param = $this->nextParameterName();
        $needle = mb_strtolower((string) $value);
        $pattern = match ($mode) {
            'startswith' => $needle.'%',
            'endswith' => '%'.$needle,
            default => '%'.$needle.'%',
        };

        $qb->andWhere($qb->expr()->like(
            \sprintf("LOWER(CONCAT(%s, ''))", $expression),
            ':'.$param,
        ))->setParameter($param, $pattern);
    }

    private function between(QueryBuilder $qb, string $expression, mixed $value, mixed $valueTo): void
    {
        $from = \is_string($value) ? trim($value) : $value;
        $to = \is_string($valueTo) ? trim((string) $valueTo) : $valueTo;

        if (null !== $from && '' !== $from) {
            $param = $this->nextParameterName();
            $qb->andWhere($qb->expr()->gte($expression, ':'.$param))
                ->setParameter($param, $this->castComparable($from));
        }
        if (null !== $to && '' !== $to) {
            $param = $this->nextParameterName();
            $qb->andWhere($qb->expr()->lte($expression, ':'.$param))
                ->setParameter($param, $this->castComparable($to));
        }
    }

    private function in(QueryBuilder $qb, string $expression, mixed $value): void
    {
        $values = $this->normalizeInValues($value);
        if ([] === $values) {
            return;
        }

        $param = $this->nextParameterName();
        $qb->andWhere($qb->expr()->in($expression, ':'.$param))
            ->setParameter($param, $values);
    }

    /**
     * @return list<mixed>
     */
    private function normalizeInValues(mixed $value): array
    {
        if (\is_array($value)) {
            return array_values($value);
        }

        if (\is_string($value)) {
            $parts = array_map('trim', explode(',', $value));

            return array_values(array_filter($parts, static fn (string $v): bool => '' !== $v));
        }

        if (null === $value || '' === $value) {
            return [];
        }

        return [$value];
    }

    private function castComparable(mixed $value): mixed
    {
        if (!\is_string($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return $value;
            }
        }

        return $value;
    }

    private function nextParameterName(): string
    {
        ++$this->parameterSequence;

        return 'dtf_'.$this->parameterSequence.'_'.bin2hex(random_bytes(4));
    }
}
