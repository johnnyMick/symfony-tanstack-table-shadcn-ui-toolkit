<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\DataCollector;

use Symfony\Contracts\Service\ResetInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySortEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\TransformRowsEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;

/**
 * Request-scoped recorder for datatable debug data (consumed by {@see DatatableDataCollector}).
 *
 * Registered with {@code kernel.reset} so each request starts clean. When the profiler is
 * disabled this service is still cheap; {@see current()} returns null until constructed.
 */
final class DatatableTrace implements ResetInterface
{
    private static ?self $current = null;

    /** @var list<array<string, mixed>> */
    private array $tableQueries = [];

    /** @var list<array<string, mixed>> */
    private array $applies = [];

    public function __construct()
    {
        self::$current = $this;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public function reset(): void
    {
        $this->tableQueries = [];
        $this->applies = [];
        self::$current = $this;
    }

    public function addTableQuery(TableQuery $query, ?string $source = null): void
    {
        $this->tableQueries[] = [
            'page' => $query->page,
            'pageSize' => $query->pageSize,
            'offset' => $query->getOffset(),
            'fields' => $query->fields,
            'filters' => $query->filters,
            'sorts' => $query->sorts,
            'hasFilter' => $query->hasFilter(),
            'hasFields' => $query->hasFields(),
            'hasSort' => $query->hasSort(),
            'source' => $source,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function addApply(string $type, array $payload): void
    {
        if (isset($payload['dql']) && \is_string($payload['dql']) && !isset($payload['dqlFormatted'])) {
            $payload['dqlFormatted'] = self::formatDql($payload['dql']);
        }

        if (!isset($payload['events']) || !\is_array($payload['events'])) {
            $payload['events'] = [];
        }

        $this->applies[] = [
            'type' => $type,
            ...$payload,
        ];
    }

    /**
     * Pretty-print DQL for the profiler panel (keyword line breaks).
     */
    public static function formatDql(string $dql): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($dql)) ?? trim($dql);
        $formatted = preg_replace(
            '/\s+(SELECT|FROM|WHERE|AND|OR|LEFT JOIN|INNER JOIN|JOIN|GROUP BY|ORDER BY|HAVING|SET|DISTINCT)\b/i',
            "\n$1",
            ' '.$normalized,
        ) ?? $normalized;

        return trim($formatted);
    }

    /**
     * @return array<string, mixed>
     */
    public static function selectEventPayload(ApplySelectEventInterface $event): array
    {
        return [
            'type' => 'select',
            'event' => $event::class,
            'field' => $event->getField(),
            'expression' => $event->resolveExpression(),
            'rootAlias' => $event->getRootAlias(),
            'context' => self::sanitizeContext($event->getContext()),
            'handled' => $event->isHandled(),
            'propagationStopped' => $event->isPropagationStopped(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filterEventPayload(ApplyFilterEventInterface $event): array
    {
        return [
            'type' => 'filter',
            'event' => $event::class,
            'field' => $event->getField(),
            'operator' => $event->getOperator(),
            'value' => self::sanitizeValue($event->getValue()),
            'valueTo' => self::sanitizeValue($event->getValueTo()),
            'expression' => $event->resolveExpression(),
            'rootAlias' => $event->getRootAlias(),
            'context' => self::sanitizeContext($event->getContext()),
            'handled' => $event->isHandled(),
            'propagationStopped' => $event->isPropagationStopped(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function joinEventPayload(ApplyJoinEventInterface $event): array
    {
        return [
            'type' => 'join',
            'event' => $event::class,
            'field' => $event->getField(),
            'joinAlias' => $event->getJoinAlias(),
            'joinPath' => $event->getJoinPath(),
            'joinType' => $event->getJoinType(),
            'joinCondition' => $event->getJoinCondition(),
            'joinOutcome' => $event->getJoinOutcome(),
            'rootAlias' => $event->getRootAlias(),
            'aliasPresent' => $event->hasAlias($event->getJoinAlias()),
            'context' => self::sanitizeContext($event->getContext()),
            'handled' => $event->isHandled(),
            'propagationStopped' => $event->isPropagationStopped(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sortEventPayload(ApplySortEventInterface $event): array
    {
        return [
            'type' => 'sort',
            'event' => $event::class,
            'field' => $event->getField(),
            'direction' => $event->getDirection(),
            'expression' => $event->resolveExpression(),
            'rootAlias' => $event->getRootAlias(),
            'context' => self::sanitizeContext($event->getContext()),
            'handled' => $event->isHandled(),
            'propagationStopped' => $event->isPropagationStopped(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $beforeRows rows captured before dispatch
     *
     * @return array<string, mixed>
     */
    public static function transformEventPayload(TransformRowsEventInterface $event, array $beforeRows = []): array
    {
        $afterRows = $event->getRows();

        return [
            'type' => 'transform',
            'event' => $event::class,
            'rowCount' => \count($afterRows),
            'columns' => self::rowColumnKeys($afterRows),
            'changedColumns' => self::changedColumns($beforeRows, $afterRows),
            'context' => self::sanitizeContext($event->getContext()),
            'propagationStopped' => $event instanceof \Symfony\Contracts\EventDispatcher\Event
                ? $event->isPropagationStopped()
                : false,
        ];
    }

    /**
     * Union of row keys across a page (stable order: first row keys, then extras).
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<string>
     */
    public static function rowColumnKeys(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $name = (string) $key;
                if (!\in_array($name, $keys, true)) {
                    $keys[] = $name;
                }
            }
        }

        return $keys;
    }

    /**
     * Column names whose values differ between before/after for any row index.
     *
     * @param list<array<string, mixed>> $before
     * @param list<array<string, mixed>> $after
     *
     * @return list<string>
     */
    public static function changedColumns(array $before, array $after): array
    {
        $changed = [];
        $count = max(\count($before), \count($after));
        for ($i = 0; $i < $count; ++$i) {
            $left = $before[$i] ?? [];
            $right = $after[$i] ?? [];
            foreach (array_unique([...array_keys($left), ...array_keys($right)]) as $key) {
                $name = (string) $key;
                if (\in_array($name, $changed, true)) {
                    continue;
                }
                if (($left[$key] ?? null) !== ($right[$key] ?? null)) {
                    $changed[] = $name;
                }
            }
        }

        return $changed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTableQueries(): array
    {
        return $this->tableQueries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getApplies(): array
    {
        return $this->applies;
    }

    /**
     * Flattened ApplySelect / ApplyFilter / TransformRows payloads nested under applies.
     *
     * @return list<array<string, mixed>>
     */
    public function getEvents(): array
    {
        $events = [];
        foreach ($this->applies as $apply) {
            foreach ($apply['events'] ?? [] as $event) {
                if (\is_array($event)) {
                    $events[] = $event;
                }
            }
        }

        return $events;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public static function sanitizeContext(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $out[(string) $key] = self::sanitizeContextEntry((string) $key, $value);
        }

        return $out;
    }

    private static function sanitizeContextEntry(string $key, mixed $value): mixed
    {
        if ('entity' === $key && \is_string($value)) {
            return $value;
        }

        if (\in_array($key, ['allowed_fields', 'extra_allowed_fields', 'selected_fields'], true) && \is_array($value)) {
            return array_values(array_map(static fn (mixed $v): string => (string) $v, $value));
        }

        if ('field_map' === $key && \is_array($value)) {
            return self::sanitizeFieldMap($value);
        }

        if (\in_array($key, ['datetime_formats', 'datetime_format', 'joins'], true)) {
            return self::sanitizeValue($value);
        }

        return self::sanitizeValue($value);
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function sanitizeFieldMap(array $value): array
    {
        $map = [];
        foreach ($value as $accessor => $expression) {
            $map[(string) $accessor] = \is_scalar($expression) || null === $expression
                ? $expression
                : get_debug_type($expression);
        }

        return $map;
    }

    public static function sanitizeValue(mixed $value): mixed
    {
        if (null === $value || \is_bool($value) || \is_int($value) || \is_float($value) || \is_string($value)) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if (\is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[\is_int($k) ? $k : (string) $k] = self::sanitizeValue($v);
            }

            return $out;
        }

        return get_debug_type($value);
    }
}
