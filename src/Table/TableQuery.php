<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use SymfonyTanstackTableShadcnUiToolkit\DataCollector\DatatableTrace;

/**
 * Pagination + search parameters parsed from an AJAX table request.
 *
 * Query string: {@code page}, {@code pageSize}, {@code fields[]} (visible column accessorKeys),
 * and {@code filters[i][field|operator|value|valueTo|values[]]}.
 *
 * {@see $filter}, {@see $filterField}, and {@see $filterOperator} mirror the first entry of
 * {@see $filters} for single-rule convenience (quick search, in-memory shortcuts).
 */
final class TableQuery
{
    public const OPERATOR_STARTS_WITH = 'startsWith';
    public const OPERATOR_ENDS_WITH = 'endsWith';
    public const OPERATOR_EQUALS = 'equals';
    public const OPERATOR_CONTAINS = 'contains';
    public const OPERATOR_GT = 'gt';
    public const OPERATOR_GTE = 'gte';
    public const OPERATOR_LT = 'lt';
    public const OPERATOR_LTE = 'lte';
    public const OPERATOR_BETWEEN = 'between';
    public const OPERATOR_BEFORE = 'before';
    public const OPERATOR_AFTER = 'after';
    public const OPERATOR_IN = 'in';

    private const OPERATORS = [
        self::OPERATOR_STARTS_WITH,
        self::OPERATOR_ENDS_WITH,
        self::OPERATOR_EQUALS,
        self::OPERATOR_CONTAINS,
        self::OPERATOR_GT,
        self::OPERATOR_GTE,
        self::OPERATOR_LT,
        self::OPERATOR_LTE,
        self::OPERATOR_BETWEEN,
        self::OPERATOR_BEFORE,
        self::OPERATOR_AFTER,
        self::OPERATOR_IN,
    ];

    private const STRING_OPERATORS = [
        self::OPERATOR_STARTS_WITH,
        self::OPERATOR_ENDS_WITH,
        self::OPERATOR_EQUALS,
        self::OPERATOR_CONTAINS,
    ];

    /** @var list<array{field: string, operator: string, value: string, valueTo: string}> */
    public readonly array $filters;

    /**
     * Visible / requested column accessorKeys from {@code fields[]} (empty = return all keys).
     *
     * @var list<string>
     */
    public readonly array $fields;

    /**
     * Server-side sort rules from {@code sorts[i][field|direction]} (or {@code sort}/{@code sortDir}).
     *
     * @var list<array{field: string, direction: string}>
     */
    public readonly array $sorts;

    /** First filter value, or null when {@see $filters} is empty. */
    public readonly ?string $filter;

    /** First filter field, or null when {@see $filters} is empty. */
    public readonly ?string $filterField;

    /** First filter operator, or {@see self::OPERATOR_STARTS_WITH} when empty. */
    public readonly string $filterOperator;

    /**
     * @param list<array{field?: string, operator?: string, value?: string, valueTo?: string, values?: mixed}> $filters
     * @param list<string>|array<int|string, mixed>                                                             $fields
     * @param list<array{field?: string, direction?: string, dir?: string}>|array<int|string, mixed>           $sorts
     */
    public function __construct(
        public readonly int $page,
        public readonly int $pageSize,
        array $filters = [],
        array $fields = [],
        array $sorts = [],
    ) {
        if ($this->page < 1) {
            throw new \InvalidArgumentException('page must be >= 1.');
        }
        if ($this->pageSize < 1) {
            throw new \InvalidArgumentException('pageSize must be >= 1.');
        }

        $this->filters = self::normalizeFilters($filters);
        $this->fields = self::normalizeFields($fields);
        $this->sorts = self::normalizeSorts($sorts);

        $first = $this->filters[0] ?? null;
        $this->filter = $first['value'] ?? null;
        $this->filterField = $first['field'] ?? null;
        $this->filterOperator = $first['operator'] ?? self::OPERATOR_STARTS_WITH;
    }

    public static function fromRequest(
        Request $request,
        int $defaultPageSize = 25,
        int $maxPageSize = 100,
    ): self {
        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = $request->query->getInt('pageSize', $defaultPageSize);
        $pageSize = min($maxPageSize, max(1, $pageSize));

        $rawFilters = $request->query->all('filters');
        if (!\is_array($rawFilters)) {
            $rawFilters = [];
        }

        $rawFields = $request->query->all('fields');
        if (!\is_array($rawFields) || [] === $rawFields) {
            $single = $request->query->get('fields');
            $rawFields = \is_string($single) && '' !== $single ? [$single] : [];
        }

        $rawSorts = $request->query->all('sorts');
        if (!\is_array($rawSorts) || [] === $rawSorts) {
            $sortField = trim((string) $request->query->get('sort', ''));
            $sortDir = trim((string) $request->query->get('sortDir', 'asc'));
            $rawSorts = '' !== $sortField ? [['field' => $sortField, 'direction' => $sortDir]] : [];
        }

        $query = new self(
            $page,
            $pageSize,
            $rawFilters,
            $rawFields,
            $rawSorts,
        );

        DatatableTrace::current()?->addTableQuery($query, self::callerSource());

        return $query;
    }

    public function hasSort(): bool
    {
        return [] !== $this->sorts;
    }

    private static function callerSource(): ?string
    {
        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        foreach ($trace as $frame) {
            $class = $frame['class'] ?? null;
            if (null === $class || str_starts_with($class, __NAMESPACE__)) {
                continue;
            }

            return $class.'::'.($frame['function'] ?? '?');
        }

        return null;
    }

    /**
     * @param list<string>|array<int|string, mixed> $fields
     *
     * @return list<string>
     */
    private static function normalizeFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $field) {
            if (!\is_string($field) && !\is_int($field) && !\is_float($field)) {
                continue;
            }
            $name = trim((string) $field);
            if ('' === $name || \in_array($name, $normalized, true)) {
                continue;
            }
            // Only plain accessorKeys — never trust dotted / injected fragments.
            if (1 !== preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                continue;
            }
            $normalized[] = $name;
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>>|array<int|string, mixed> $sorts
     *
     * @return list<array{field: string, direction: string}>
     */
    private static function normalizeSorts(array $sorts): array
    {
        $normalized = [];
        foreach ($sorts as $sort) {
            if (!\is_array($sort)) {
                continue;
            }
            $field = trim((string) ($sort['field'] ?? ''));
            if ('' === $field || 1 !== preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                continue;
            }
            $direction = strtolower(trim((string) ($sort['direction'] ?? $sort['dir'] ?? 'asc')));
            if (!\in_array($direction, ['asc', 'desc'], true)) {
                $direction = 'asc';
            }
            $normalized[] = [
                'field' => $field,
                'direction' => $direction,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>>|array<int|string, mixed> $filters
     *
     * @return list<array{field: string, operator: string, value: string, valueTo: string}>
     */
    private static function normalizeFilters(array $filters): array
    {
        $normalized = [];
        foreach ($filters as $filter) {
            $entry = self::normalizeOneFilter($filter);
            if (null !== $entry) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    /**
     * @return array{field: string, operator: string, value: string, valueTo: string}|null
     */
    private static function normalizeOneFilter(mixed $filter): ?array
    {
        if (!\is_array($filter)) {
            return null;
        }

        $field = trim((string) ($filter['field'] ?? ''));
        if ('' === $field) {
            return null;
        }

        $value = trim((string) ($filter['value'] ?? ''));
        $valueTo = trim((string) ($filter['valueTo'] ?? ''));

        // Multi-select filters send values[] — join them into value.
        if (isset($filter['values']) && \is_array($filter['values'])) {
            $fromList = self::trimNonEmptyStrings($filter['values']);
            if ([] !== $fromList) {
                $value = implode(',', $fromList);
            }
        }

        $operator = (string) ($filter['operator'] ?? self::OPERATOR_CONTAINS);
        if (!\in_array($operator, self::OPERATORS, true)) {
            $operator = self::OPERATOR_CONTAINS;
        }

        if ('' === $value && '' === $valueTo) {
            return null;
        }

        return [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'valueTo' => $valueTo,
        ];
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<string>
     */
    private static function trimNonEmptyStrings(array $values): array
    {
        $parts = [];
        foreach ($values as $item) {
            $trimmed = trim((string) $item);
            if ('' !== $trimmed) {
                $parts[] = $trimmed;
            }
        }

        return $parts;
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
        return [] !== $this->filters;
    }

    public function hasFields(): bool
    {
        return [] !== $this->fields;
    }

    /**
     * Restrict each row to the requested {@see $fields} (plus {@code id} when present).
     * When no fields were requested, rows are returned unchanged.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public function projectRows(array $rows): array
    {
        if (!$this->hasFields()) {
            return $rows;
        }

        $keys = $this->fields;
        if (!\in_array('id', $keys, true)) {
            array_unshift($keys, 'id');
        }

        return array_values(array_map(
            static function (array $row) use ($keys): array {
                $projected = [];
                foreach ($keys as $key) {
                    if (\array_key_exists($key, $row)) {
                        $projected[$key] = $row[$key];
                    }
                }

                return $projected;
            },
            $rows,
        ));
    }

    /**
     * Apply search filters to a Doctrine query builder using a whitelist of field expressions.
     *
     * For event-driven selects/filters with custom/computed fields, prefer
     * {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier}.
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

        foreach ($this->filters as $index => $filter) {
            $expression = $fieldMap[$filter['field']] ?? null;
            if (null === $expression) {
                continue;
            }

            $this->applyDoctrineRule($qb, $expression, $filter, $parameterName.'_'.$index);
        }
    }

    /**
     * @param array{field: string, operator: string, value: string, valueTo: string} $filter
     */
    private function applyDoctrineRule(QueryBuilder $qb, string $expression, array $filter, string $paramBase): void
    {
        $operator = $filter['operator'];
        $value = $filter['value'];
        $valueTo = $filter['valueTo'];

        if (\in_array($operator, self::STRING_OPERATORS, true)) {
            $lowerExpr = \sprintf("LOWER(CONCAT(%s, ''))", $expression);
            $lowerParam = ':'.$paramBase;
            match ($operator) {
                self::OPERATOR_STARTS_WITH => $qb
                    ->andWhere($qb->expr()->like($lowerExpr, $lowerParam))
                    ->setParameter($paramBase, mb_strtolower($value).'%'),
                self::OPERATOR_ENDS_WITH => $qb
                    ->andWhere($qb->expr()->like($lowerExpr, $lowerParam))
                    ->setParameter($paramBase, '%'.mb_strtolower($value)),
                self::OPERATOR_EQUALS => $qb
                    ->andWhere($qb->expr()->eq($lowerExpr, $lowerParam))
                    ->setParameter($paramBase, mb_strtolower($value)),
                self::OPERATOR_CONTAINS => $qb
                    ->andWhere($qb->expr()->like($lowerExpr, $lowerParam))
                    ->setParameter($paramBase, '%'.mb_strtolower($value).'%'),
            };

            return;
        }

        if (self::OPERATOR_IN === $operator) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $v): bool => '' !== $v));
            if ([] === $parts) {
                return;
            }
            $qb->andWhere($qb->expr()->in($expression, ':'.$paramBase))
                ->setParameter($paramBase, $parts);

            return;
        }

        if (self::OPERATOR_BETWEEN === $operator) {
            if ('' !== $value) {
                $qb->andWhere($qb->expr()->gte($expression, ':'.$paramBase.'_from'))
                    ->setParameter($paramBase.'_from', $this->castComparable($value));
            }
            if ('' !== $valueTo) {
                $qb->andWhere($qb->expr()->lte($expression, ':'.$paramBase.'_to'))
                    ->setParameter($paramBase.'_to', $this->castComparable($valueTo));
            }

            return;
        }

        $comparable = $this->castComparable($value);
        match ($operator) {
            self::OPERATOR_GT, self::OPERATOR_AFTER => $qb
                ->andWhere($qb->expr()->gt($expression, ':'.$paramBase))
                ->setParameter($paramBase, $comparable),
            self::OPERATOR_GTE => $qb
                ->andWhere($qb->expr()->gte($expression, ':'.$paramBase))
                ->setParameter($paramBase, $comparable),
            self::OPERATOR_LT, self::OPERATOR_BEFORE => $qb
                ->andWhere($qb->expr()->lt($expression, ':'.$paramBase))
                ->setParameter($paramBase, $comparable),
            self::OPERATOR_LTE => $qb
                ->andWhere($qb->expr()->lte($expression, ':'.$paramBase))
                ->setParameter($paramBase, $comparable),
            default => null,
        };
    }

    private function castComparable(string $value): mixed
    {
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

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->rowMatchesFilters($row),
        ));
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

    /**
     * @param array<string, mixed> $row
     */
    public function rowMatchesFilters(array $row): bool
    {
        foreach ($this->filters as $filter) {
            if (!$this->matchesFilterRule($row[$filter['field']] ?? null, $filter)) {
                return false;
            }
        }

        return true;
    }

    public function matchesValue(mixed $value): bool
    {
        if (!$this->hasFilter()) {
            return true;
        }

        return $this->matchesFilterRule($value, [
            'field' => $this->filterField ?? '',
            'operator' => $this->filterOperator,
            'value' => $this->filter ?? '',
            'valueTo' => '',
        ]);
    }

    /**
     * @param array{field: string, operator: string, value: string, valueTo: string} $filter
     */
    private function matchesFilterRule(mixed $cellValue, array $filter): bool
    {
        $operator = $filter['operator'];
        $needle = $filter['value'];
        $needleTo = $filter['valueTo'];

        if (self::OPERATOR_IN === $operator) {
            $haystack = mb_strtolower((string) ($cellValue ?? ''));
            $parts = array_map('mb_strtolower', array_filter(array_map('trim', explode(',', $needle)), static fn (string $v): bool => '' !== $v));

            return \in_array($haystack, $parts, true);
        }

        if (\in_array($operator, self::STRING_OPERATORS, true)) {
            $haystack = mb_strtolower((string) ($cellValue ?? ''));
            $needle = mb_strtolower($needle);

            return match ($operator) {
                self::OPERATOR_STARTS_WITH => str_starts_with($haystack, $needle),
                self::OPERATOR_ENDS_WITH => str_ends_with($haystack, $needle),
                self::OPERATOR_EQUALS => $haystack === $needle,
                self::OPERATOR_CONTAINS => str_contains($haystack, $needle),
                default => false,
            };
        }

        $left = $this->toComparableScalar($cellValue);
        $right = $this->toComparableScalar($needle);
        $rightTo = '' !== $needleTo ? $this->toComparableScalar($needleTo) : null;

        if (null === $left) {
            return false;
        }

        return match ($operator) {
            self::OPERATOR_EQUALS => null !== $right && $left == $right,
            self::OPERATOR_GT, self::OPERATOR_AFTER => null !== $right && $left > $right,
            self::OPERATOR_GTE => null !== $right && $left >= $right,
            self::OPERATOR_LT, self::OPERATOR_BEFORE => null !== $right && $left < $right,
            self::OPERATOR_LTE => null !== $right && $left <= $right,
            self::OPERATOR_BETWEEN => (null === $right || $left >= $right) && (null === $rightTo || $left <= $rightTo),
            default => false,
        };
    }

    private function toComparableScalar(mixed $value): int|float|string|null
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value)) {
            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }

        $string = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $string)) {
            return substr($string, 0, 10);
        }

        return mb_strtolower($string);
    }
}
