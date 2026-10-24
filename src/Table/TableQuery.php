<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pagination + search parameters parsed from an AJAX table request.
 *
 * Query string: {@code page}, {@code pageSize}, {@code filter}, {@code filterField}, {@code filterOperator}.
 */
final class TableQuery
{
    public const OPERATOR_STARTS_WITH = 'startsWith';
    public const OPERATOR_ENDS_WITH = 'endsWith';
    public const OPERATOR_EQUALS = 'equals';
    public const OPERATOR_CONTAINS = 'contains';

    private const OPERATORS = [
        self::OPERATOR_STARTS_WITH,
        self::OPERATOR_ENDS_WITH,
        self::OPERATOR_EQUALS,
        self::OPERATOR_CONTAINS,
    ];

    public function __construct(
        public readonly int $page,
        public readonly int $pageSize,
        public readonly ?string $filter = null,
        public readonly ?string $filterField = null,
        public readonly string $filterOperator = self::OPERATOR_STARTS_WITH,
    ) {
        if ($this->page < 1) {
            throw new \InvalidArgumentException('page must be >= 1.');
        }
        if ($this->pageSize < 1) {
            throw new \InvalidArgumentException('pageSize must be >= 1.');
        }
        if (!\in_array($this->filterOperator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported filterOperator "%s".', $this->filterOperator));
        }
    }

    public static function fromRequest(
        Request $request,
        int $defaultPageSize = 25,
        int $maxPageSize = 100,
    ): self {
        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = $request->query->getInt('pageSize', $defaultPageSize);
        $pageSize = min($maxPageSize, max(1, $pageSize));

        $filter = trim((string) $request->query->get('filter', ''));
        $filterField = trim((string) $request->query->get('filterField', ''));
        $operator = (string) $request->query->get('filterOperator', self::OPERATOR_STARTS_WITH);
        if (!\in_array($operator, self::OPERATORS, true)) {
            $operator = self::OPERATOR_STARTS_WITH;
        }

        return new self(
            $page,
            $pageSize,
            '' !== $filter ? $filter : null,
            '' !== $filterField ? $filterField : null,
            $operator,
        );
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->pageSize;
    }

    public function pageCount(int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) ceil($total / $this->pageSize);
    }

    public function hasFilter(): bool
    {
        return null !== $this->filter && '' !== $this->filter && null !== $this->filterField && '' !== $this->filterField;
    }

    /**
     * Apply the search to a Doctrine query builder using a whitelist of field expressions.
     *
     * @param QueryBuilder          $qb            QueryBuilder
     * @param array<string, string> $fieldMap      accessorKey => DQL expression (from {@see TanstackDataTable::fieldMap()})
     * @param string                $parameterName
     */
    public function applyDoctrineFilter(QueryBuilder $qb, array $fieldMap, string $parameterName = 'table_filter'): void
    {
        if (!$this->hasFilter()) {
            return;
        }

        if (!method_exists($qb, 'expr') || !method_exists($qb, 'andWhere') || !method_exists($qb, 'setParameter')) {
            throw new \InvalidArgumentException('applyDoctrineFilter expects a Doctrine QueryBuilder.');
        }

        $expression = $fieldMap[$this->filterField] ?? null;
        if (null === $expression) {
            return;
        }

        $value = $this->filter;
        // CONCAT forces a string cast across common Doctrine platforms.
        $lowerExpr = \sprintf("LOWER(CONCAT(%s, ''))", $expression);
        $lowerParam = ':'.$parameterName;

        match ($this->filterOperator) {
            self::OPERATOR_STARTS_WITH => $qb
                ->andWhere($qb->expr()->like($lowerExpr, $lowerParam))
                ->setParameter($parameterName, mb_strtolower($value).'%'),
            self::OPERATOR_ENDS_WITH => $qb
                ->andWhere($qb->expr()->like($lowerExpr, $lowerParam))
                ->setParameter($parameterName, '%'.mb_strtolower($value)),
            self::OPERATOR_EQUALS => $qb
                ->andWhere($qb->expr()->eq($lowerExpr, $lowerParam))
                ->setParameter($parameterName, mb_strtolower($value)),
            self::OPERATOR_CONTAINS => $qb
                ->andWhere($qb->expr()->like($lowerExpr, $lowerParam))
                ->setParameter($parameterName, '%'.mb_strtolower($value).'%'),
        };
    }

    /**
     * Filter an in-memory list of row arrays (after mapping to accessorKeys).
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public function filterRows(array $rows): array
    {
        if (!$this->hasFilter()) {
            return $rows;
        }

        $field = $this->filterField;
        $filtered = array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->matchesValue($row[$field] ?? null),
        ));

        return $filtered;
    }

    /**
     * Paginate an in-memory list (optionally filtered first).
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    public function paginateRows(array $rows): array
    {
        $filtered = $this->filterRows($rows);
        $total = \count($filtered);
        $pageRows = array_values(\array_slice($filtered, $this->getOffset(), $this->pageSize));

        return [$pageRows, $total];
    }

    public function matchesValue(mixed $value): bool
    {
        if (!$this->hasFilter()) {
            return true;
        }

        $haystack = mb_strtolower((string) ($value ?? ''));
        $needle = mb_strtolower($this->filter ?? '');

        return match ($this->filterOperator) {
            self::OPERATOR_STARTS_WITH => str_starts_with($haystack, $needle),
            self::OPERATOR_ENDS_WITH => str_ends_with($haystack, $needle),
            self::OPERATOR_EQUALS => $haystack === $needle,
            self::OPERATOR_CONTAINS => str_contains($haystack, $needle),
        };
    }
}
