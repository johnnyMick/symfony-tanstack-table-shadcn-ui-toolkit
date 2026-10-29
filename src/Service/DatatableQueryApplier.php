<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Service;

use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use SymfonyTanstackTableShadcnUiToolkit\DataCollector\DatatableTrace;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySortEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySortEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\TransformRowsEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\TransformRowsEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Query\DatatableFieldJoinEnricher;
use SymfonyTanstackTableShadcnUiToolkit\Query\QueryContext;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;

/**
 * Turns table request data into Doctrine QueryBuilder changes via events.
 *
 * Typical flow:
 * 1. {@see fetchPage()} — COUNT + one page of rows
 * 2. For each field: optional {@see ApplyJoinEvent}, then select / filter / sort
 * 3. After fetch: {@see TransformRowsEvent} for JSON-friendly values
 *
 * @see \SymfonyTanstackTableShadcnUiToolkit\Query\QueryContext
 */
class DatatableQueryApplier
{
    /** @var array<string, true> join aliases already applied in the current call */
    private array $joinedAliases = [];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?DatatableTrace $trace = null,
        private readonly ?DatatableFieldJoinEnricher $joinEnricher = null,
    ) {
    }

    /**
     * Build a COUNT query and a page query from the same factory.
     *
     * @param callable(): QueryBuilder $qbFactory must return a fresh root QB each call
     *
     * @return array{0: list<array<string, mixed>>, 1: int} [rows, total]
     */
    public function fetchPage(
        callable $qbFactory,
        TableQuery $query,
        QueryContext $context,
        string $idExpression,
    ): array {
        $context = $this->prepareContext($context);

        $total = $this->countRows($qbFactory(), $query, $context, $idExpression);

        $pageQb = $qbFactory();
        $pageQb->setFirstResult($query->getOffset())->setMaxResults($query->pageSize);
        $this->applyDefaultOrRequestSort($pageQb, $query, $context, $idExpression);

        $rows = $this->fetchScalarRows($pageQb, $query, $context, $idExpression);

        return [$rows, $total];
    }

    /**
     * Select fields, apply filters, run the query, then transform rows.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchScalarRows(
        QueryBuilder $qb,
        TableQuery $query,
        QueryContext $context,
        string $idExpression,
    ): array {
        $context = $this->prepareContext($context);
        $fields = $this->resolveSelectFields($query, $context);
        $context = $context->withSelectedFields($fields);

        $qb->select($idExpression.' AS id');

        $selectFields = $this->fieldsWithoutId($fields);
        if ([] !== $selectFields) {
            $this->applySelects($qb, $selectFields, $context);
        }

        if ($query->hasFilter()) {
            $this->applyFilters($qb, $query->filters, $context);
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return $this->transformRows($rows, $context);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public function transformRows(array $rows, QueryContext $context = new QueryContext()): array
    {
        $context = $this->prepareContext($context);
        $contextArray = $context->normalize()->toArray();
        $before = array_values($rows);

        $event = $this->createTransformRowsEvent($before, $contextArray);
        $this->eventDispatcher->dispatch($event, $this->transformRowsEventName());
        $after = array_values($event->getRows());

        $this->trace?->addApply('transform', [
            'rowCount' => \count($after),
            'columns' => DatatableTrace::rowColumnKeys($after),
            'changedColumns' => DatatableTrace::changedColumns($before, $after),
            'events' => [DatatableTrace::transformEventPayload($event, $before)],
            'context' => DatatableTrace::sanitizeContext($contextArray),
            'sampleBefore' => DatatableTrace::sanitizeValue(\array_slice($before, 0, 3)),
            'sampleAfter' => DatatableTrace::sanitizeValue(\array_slice($after, 0, 3)),
        ]);

        return $after;
    }

    /**
     * @param list<string>|array<int|string, mixed> $fields
     */
    public function applySelects(QueryBuilder $qb, array $fields, QueryContext $context = new QueryContext()): void
    {
        $this->joinedAliases = [];
        $rootAlias = $this->rootAlias($qb);
        $contextArray = $this->prepareContext($context)->normalize()->toArray();
        $appliedFields = [];
        $events = [];

        foreach ($fields as $rawField) {
            $field = $this->normalizeAccessorKey($rawField);
            if (null === $field) {
                continue;
            }

            $appliedFields[] = $field;
            $events = [...$events, ...$this->dispatchJoinsForField($qb, $field, $rootAlias, $contextArray)];

            $event = $this->createSelectEvent($qb, $field, $rootAlias, $contextArray);
            $this->eventDispatcher->dispatch($event, $this->selectEventName());
            $events[] = DatatableTrace::selectEventPayload($event);
        }

        $this->trace?->addApply('selects', [
            'rootAlias' => $rootAlias,
            'fields' => $appliedFields,
            'events' => $events,
            'context' => DatatableTrace::sanitizeContext($contextArray),
            ...$this->querySnapshot($qb),
        ]);
    }

    /**
     * @param list<array<string, mixed>>|array<int|string, mixed> $filters
     */
    public function applyFilters(QueryBuilder $qb, array $filters, QueryContext $context = new QueryContext()): void
    {
        $this->joinedAliases = [];
        $rootAlias = $this->rootAlias($qb);
        $contextArray = $this->prepareContext($context)->normalize()->toArray();
        $appliedFilters = [];
        $events = [];

        foreach ($filters as $rawFilter) {
            $parsed = $this->parseFilterEntry($rawFilter);
            if (null === $parsed) {
                continue;
            }

            $appliedFilters[] = [
                'field' => $parsed['field'],
                'operator' => $parsed['operator'],
                'value' => DatatableTrace::sanitizeValue($parsed['value']),
                'valueTo' => DatatableTrace::sanitizeValue($parsed['valueTo']),
            ];

            $events = [...$events, ...$this->dispatchJoinsForField($qb, $parsed['field'], $rootAlias, $contextArray)];

            $event = $this->createFilterEvent(
                $qb,
                $parsed['field'],
                $parsed['operator'],
                $parsed['value'],
                $rootAlias,
                $contextArray,
                $parsed['valueTo'],
            );
            $this->eventDispatcher->dispatch($event, $this->filterEventName());
            $events[] = DatatableTrace::filterEventPayload($event);
        }

        $this->trace?->addApply('filters', [
            'rootAlias' => $rootAlias,
            'filters' => $appliedFilters,
            'events' => $events,
            'context' => DatatableTrace::sanitizeContext($contextArray),
            ...$this->querySnapshot($qb),
        ]);
    }

    /**
     * @param list<array{field?: string, direction?: string}>|array<int|string, mixed> $sorts
     */
    public function applySorts(QueryBuilder $qb, array $sorts, QueryContext $context = new QueryContext()): void
    {
        $this->joinedAliases = [];
        $rootAlias = $this->rootAlias($qb);
        $contextArray = $this->prepareContext($context)->normalize()->toArray();
        $appliedSorts = [];
        $events = [];

        foreach ($sorts as $rawSort) {
            $parsed = $this->parseSortEntry($rawSort);
            if (null === $parsed) {
                continue;
            }

            $appliedSorts[] = $parsed;
            $events = [...$events, ...$this->dispatchJoinsForField($qb, $parsed['field'], $rootAlias, $contextArray)];

            $event = $this->createSortEvent($qb, $parsed['field'], $parsed['direction'], $rootAlias, $contextArray);
            $this->eventDispatcher->dispatch($event, $this->sortEventName());
            $events[] = DatatableTrace::sortEventPayload($event);
        }

        $this->trace?->addApply('sorts', [
            'rootAlias' => $rootAlias,
            'sorts' => $appliedSorts,
            'events' => $events,
            'context' => DatatableTrace::sanitizeContext($contextArray),
            ...$this->querySnapshot($qb),
        ]);
    }

    // -------------------------------------------------------------------------
    // Event factories (override in a subclass for richer payloads)
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $context
     */
    protected function createSelectEvent(
        QueryBuilder $qb,
        string $field,
        string $rootAlias,
        array $context,
    ): ApplySelectEventInterface {
        return new ApplySelectEvent($qb, $field, $rootAlias, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function createFilterEvent(
        QueryBuilder $qb,
        string $field,
        string $operator,
        mixed $value,
        string $rootAlias,
        array $context,
        mixed $valueTo,
    ): ApplyFilterEventInterface {
        return new ApplyFilterEvent($qb, $field, $operator, $value, $rootAlias, $context, $valueTo);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function createJoinEvent(
        QueryBuilder $qb,
        string $field,
        string $joinAlias,
        string $joinPath,
        string $rootAlias,
        array $context,
        string $joinType = ApplyJoinEventInterface::TYPE_LEFT,
        ?string $joinCondition = null,
    ): ApplyJoinEventInterface {
        return new ApplyJoinEvent(
            $qb,
            $field,
            $joinAlias,
            $joinPath,
            $rootAlias,
            $context,
            $joinType,
            $joinCondition,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function createSortEvent(
        QueryBuilder $qb,
        string $field,
        string $direction,
        string $rootAlias,
        array $context,
    ): ApplySortEventInterface {
        return new ApplySortEvent($qb, $field, $direction, $rootAlias, $context);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed>       $context
     */
    protected function createTransformRowsEvent(array $rows, array $context): TransformRowsEventInterface
    {
        return new TransformRowsEvent($rows, $context);
    }

    protected function selectEventName(): string
    {
        return ApplySelectEvent::NAME;
    }

    protected function filterEventName(): string
    {
        return ApplyFilterEvent::NAME;
    }

    protected function joinEventName(): string
    {
        return ApplyJoinEvent::NAME;
    }

    protected function sortEventName(): string
    {
        return ApplySortEvent::NAME;
    }

    protected function transformRowsEventName(): string
    {
        return TransformRowsEvent::NAME;
    }

    protected function eventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    protected function rootAlias(QueryBuilder $qb): string
    {
        $aliases = $qb->getRootAliases();
        if ([] === $aliases) {
            throw new \LogicException('QueryBuilder has no root alias; call from()/createQueryBuilder() first.');
        }

        return $aliases[0];
    }

    // -------------------------------------------------------------------------
    // Private helpers (read top-to-bottom)
    // -------------------------------------------------------------------------

    private function countRows(
        QueryBuilder $countQb,
        TableQuery $query,
        QueryContext $context,
        string $idExpression,
    ): int {
        $countQb->select(\sprintf('COUNT(%s)', $idExpression));

        if ($query->hasFilter()) {
            $this->applyFilters($countQb, $query->filters, $context);
        }

        return (int) $countQb->getQuery()->getSingleScalarResult();
    }

    private function applyDefaultOrRequestSort(
        QueryBuilder $pageQb,
        TableQuery $query,
        QueryContext $context,
        string $idExpression,
    ): void {
        if ($query->hasSort()) {
            $this->applySorts($pageQb, $query->sorts, $context);

            return;
        }

        $pageQb->orderBy($idExpression, 'DESC');
    }

    /**
     * @param list<string> $fields
     *
     * @return list<string>
     */
    private function fieldsWithoutId(array $fields): array
    {
        return array_values(array_filter(
            $fields,
            static fn (string $field): bool => 'id' !== $field,
        ));
    }

    private function normalizeAccessorKey(mixed $field): ?string
    {
        if (!\is_string($field)) {
            return null;
        }

        $field = trim($field);

        return '' === $field ? null : $field;
    }

    /**
     * @return array{field: string, operator: string, value: mixed, valueTo: mixed}|null
     */
    private function parseFilterEntry(mixed $filter): ?array
    {
        if (!\is_array($filter)) {
            return null;
        }

        $field = trim((string) ($filter['field'] ?? ''));
        if ('' === $field) {
            return null;
        }

        return [
            'field' => $field,
            'operator' => strtolower(trim((string) ($filter['operator'] ?? 'eq'))),
            'value' => $filter['value'] ?? null,
            'valueTo' => $filter['valueTo'] ?? null,
        ];
    }

    /**
     * @return array{field: string, direction: string}|null
     */
    private function parseSortEntry(mixed $sort): ?array
    {
        if (!\is_array($sort)) {
            return null;
        }

        $field = trim((string) ($sort['field'] ?? ''));
        if ('' === $field) {
            return null;
        }

        $direction = strtolower(trim((string) ($sort['direction'] ?? $sort['dir'] ?? 'asc')));
        if (!\in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        return [
            'field' => $field,
            'direction' => $direction,
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<array<string, mixed>> profiler payloads
     */
    private function dispatchJoinsForField(
        QueryBuilder $qb,
        string $field,
        string $rootAlias,
        array $context,
    ): array {
        if (!$this->isFieldAllowed($field, $context)) {
            return [];
        }

        $payloads = [];
        foreach ($this->resolveJoinsForField($field, $rootAlias, $context) as $join) {
            $payloads[] = $this->dispatchOneJoin($qb, $field, $rootAlias, $context, $join);
        }

        return $payloads;
    }

    /**
     * @param array<string, mixed>                                         $context
     * @param array{alias: string, path: string, type: string, condition: string|null} $join
     *
     * @return array<string, mixed>
     */
    private function dispatchOneJoin(
        QueryBuilder $qb,
        string $field,
        string $rootAlias,
        array $context,
        array $join,
    ): array {
        $alias = $join['alias'];

        if ($this->aliasAlreadyJoined($qb, $alias)) {
            return $this->dedupedJoinPayload($field, $rootAlias, $context, $join);
        }

        $event = $this->createJoinEvent(
            $qb,
            $field,
            $alias,
            $join['path'],
            $rootAlias,
            $context,
            $join['type'],
            $join['condition'],
        );
        $this->eventDispatcher->dispatch($event, $this->joinEventName());

        $joinWasApplied = ApplyJoinEvent::OUTCOME_APPLIED === $event->getJoinOutcome()
            || $event->hasAlias($alias);
        if ($joinWasApplied) {
            $this->joinedAliases[$alias] = true;
        }

        return DatatableTrace::joinEventPayload($event);
    }

    private function aliasAlreadyJoined(QueryBuilder $qb, string $alias): bool
    {
        if (isset($this->joinedAliases[$alias])) {
            return true;
        }

        return \in_array($alias, $qb->getAllAliases(), true);
    }

    /**
     * @param array<string, mixed>                                         $context
     * @param array{alias: string, path: string, type: string, condition: string|null} $join
     *
     * @return array<string, mixed>
     */
    private function dedupedJoinPayload(
        string $field,
        string $rootAlias,
        array $context,
        array $join,
    ): array {
        return [
            'type' => 'join',
            'event' => ApplyJoinEvent::class,
            'field' => $field,
            'joinAlias' => $join['alias'],
            'joinPath' => $join['path'],
            'joinType' => $join['type'],
            'joinCondition' => $join['condition'],
            'rootAlias' => $rootAlias,
            'aliasPresent' => true,
            'joinOutcome' => ApplyJoinEvent::OUTCOME_SKIPPED_DEDUPE,
            'handled' => true,
            'propagationStopped' => true,
            'context' => DatatableTrace::sanitizeContext($context),
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<array{alias: string, path: string, type: string, condition: string|null}>
     */
    private function resolveJoinsForField(string $field, string $rootAlias, array $context): array
    {
        $joins = $context['joins'] ?? null;
        if (!\is_array($joins) || !\array_key_exists($field, $joins)) {
            return [];
        }

        $resolved = [];
        foreach ($this->joinConfigAsList($joins[$field]) as $item) {
            $parsed = $this->parseJoinItem($item, $field, $rootAlias, $context);
            if (null !== $parsed) {
                $resolved[] = $parsed;
            }
        }

        return $resolved;
    }

    /**
     * Normalize one field's join config into a list of hop configs.
     *
     * @return list<mixed>
     */
    private function joinConfigAsList(mixed $config): array
    {
        if (\is_string($config) && '' !== $config) {
            return [$config];
        }

        if (!\is_array($config)) {
            return [];
        }

        // Single hop: ['path' => 'p.category', 'alias' => 'c']
        $isSingleHop = isset($config['path']);
        if ($isSingleHop) {
            return [$config];
        }

        // Chain: [ ['path' => ...], ['path' => ...] ] or list of path strings
        return array_values($config);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{alias: string, path: string, type: string, condition: string|null}|null
     */
    private function parseJoinItem(mixed $item, string $field, string $rootAlias, array $context): ?array
    {
        $path = null;
        $alias = null;
        $type = ApplyJoinEventInterface::TYPE_LEFT;
        $condition = null;

        if (\is_string($item) && '' !== $item) {
            $path = $item;
        }

        if (\is_array($item)) {
            $path = \is_string($item['path'] ?? null) ? $item['path'] : null;
            $alias = \is_string($item['alias'] ?? null) ? $item['alias'] : null;
            $type = \is_string($item['type'] ?? null)
                ? strtolower($item['type'])
                : ApplyJoinEventInterface::TYPE_LEFT;
            $condition = \is_string($item['condition'] ?? null) ? $item['condition'] : null;
        }

        if (null === $path || '' === $path) {
            return null;
        }

        if (null === $alias || '' === $alias) {
            $alias = $this->inferJoinAlias($field, $rootAlias, $context);
        }

        if (null === $alias || '' === $alias || $alias === $rootAlias) {
            return null;
        }

        $validTypes = [ApplyJoinEventInterface::TYPE_LEFT, ApplyJoinEventInterface::TYPE_INNER];
        if (!\in_array($type, $validTypes, true)) {
            $type = ApplyJoinEventInterface::TYPE_LEFT;
        }

        return [
            'alias' => $alias,
            'path' => $path,
            'type' => $type,
            'condition' => $condition,
        ];
    }

    /**
     * Prefer the first non-root alias used in field_map (e.g. {@code c.name} → {@code c}).
     *
     * @param array<string, mixed> $context
     */
    private function inferJoinAlias(string $field, string $rootAlias, array $context): ?string
    {
        $map = $context['field_map'] ?? null;
        if (!\is_array($map)) {
            return null;
        }

        $expression = $map[$field] ?? null;
        if (!\is_string($expression) || '' === $expression) {
            return null;
        }

        if (false === preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\./', $expression, $matches)) {
            return null;
        }

        foreach ($matches[1] as $candidate) {
            if ($candidate !== $rootAlias) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isFieldAllowed(string $field, array $context): bool
    {
        if (!\array_key_exists('allowed_fields', $context)) {
            return true;
        }

        $allowed = $context['allowed_fields'];
        if (!\is_array($allowed)) {
            return false;
        }

        $allowedNames = array_map(static fn (mixed $f): string => (string) $f, $allowed);

        return \in_array($field, $allowedNames, true);
    }

    private function prepareContext(QueryContext $context): QueryContext
    {
        if (null === $this->joinEnricher) {
            return $context;
        }

        return $this->joinEnricher->enrich($context);
    }

    /**
     * @return list<string>
     */
    private function resolveSelectFields(TableQuery $query, QueryContext $context): array
    {
        if ($query->hasFields()) {
            return $query->fields;
        }

        $fromAllowed = $context->getAllowedFields();
        if (null !== $fromAllowed && [] !== $fromAllowed) {
            return $fromAllowed;
        }

        return array_keys($context->getFieldMap());
    }

    /**
     * @return array{dql: string, dqlFormatted: string, sql: ?string, parameters: array<string, mixed>}
     */
    private function querySnapshot(QueryBuilder $qb): array
    {
        $dql = $qb->getDQL();
        $parameters = [];
        foreach ($qb->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = DatatableTrace::sanitizeValue($parameter->getValue());
        }

        return [
            'dql' => $dql,
            'dqlFormatted' => DatatableTrace::formatDql($dql),
            'sql' => $this->tryGetSql($qb),
            'parameters' => $parameters,
        ];
    }

    private function tryGetSql(QueryBuilder $qb): ?string
    {
        try {
            $sql = $qb->getQuery()->getSQL();
            if (\is_array($sql)) {
                return implode("\n", $sql);
            }

            return $sql;
        } catch (\Throwable) {
            return null;
        }
    }
}
