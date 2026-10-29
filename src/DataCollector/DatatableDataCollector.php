<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * Symfony profiler panel for TableQuery, DatatableQueryApplier, Apply* and TransformRows events.
 *
 * Toolbar / menu id: {@code tstdt.datatable}
 */
final class DatatableDataCollector extends DataCollector implements LateDataCollectorInterface
{
    public const NAME = 'tstdt.datatable';

    public function __construct(
        private readonly DatatableTrace $trace,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // Live data is finalized in lateCollect() after the applier/subscribers finish.
    }

    public function lateCollect(): void
    {
        $queries = $this->trace->getTableQueries();
        $applies = $this->trace->getApplies();
        $events = $this->trace->getEvents();

        $this->data = [
            'tableQueries' => $this->cloneVar($queries),
            'applies' => $this->cloneVar($applies),
            'events' => $this->cloneVar($events),
            'counts' => [
                'tableQueries' => \count($queries),
                'applies' => \count($applies),
                'selectEvents' => \count(array_filter($events, static fn (array $e): bool => 'select' === ($e['type'] ?? null))),
                'filterEvents' => \count(array_filter($events, static fn (array $e): bool => 'filter' === ($e['type'] ?? null))),
                'joinEvents' => \count(array_filter($events, static fn (array $e): bool => 'join' === ($e['type'] ?? null))),
                'sortEvents' => \count(array_filter($events, static fn (array $e): bool => 'sort' === ($e['type'] ?? null))),
                'transformEvents' => \count(array_filter($events, static fn (array $e): bool => 'transform' === ($e['type'] ?? null))),
                'events' => \count($events),
                'stoppedEvents' => \count(array_filter($events, static fn (array $e): bool => !empty($e['propagationStopped']))),
            ],
            'raw' => [
                'tableQueries' => $queries,
                'applies' => $applies,
                'events' => $events,
            ],
        ];
    }

    public function reset(): void
    {
        $this->data = [];
        $this->trace->reset();
    }

    /**
     * @return array{
     *     tableQueries: int,
     *     applies: int,
     *     selectEvents: int,
     *     filterEvents: int,
     *     joinEvents: int,
     *     sortEvents: int,
     *     transformEvents: int,
     *     events: int,
     *     stoppedEvents: int
     * }
     */
    public function getCounts(): array
    {
        /** @var array{tableQueries: int, applies: int, selectEvents: int, filterEvents: int, joinEvents: int, sortEvents: int, transformEvents: int, events: int, stoppedEvents: int} */
        return $this->data['counts'] ?? [
            'tableQueries' => 0,
            'applies' => 0,
            'selectEvents' => 0,
            'filterEvents' => 0,
            'joinEvents' => 0,
            'sortEvents' => 0,
            'transformEvents' => 0,
            'events' => 0,
            'stoppedEvents' => 0,
        ];
    }

    public function getTableQueries(): Data|array
    {
        return $this->data['tableQueries'] ?? [];
    }

    public function getApplies(): Data|array
    {
        return $this->data['applies'] ?? [];
    }

    public function getEvents(): Data|array
    {
        return $this->data['events'] ?? [];
    }

    /**
     * Plain arrays for Twig tables (avoid dumping every cell).
     *
     * @return list<array<string, mixed>>
     */
    public function getRawTableQueries(): array
    {
        return $this->data['raw']['tableQueries'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRawApplies(): array
    {
        return $this->data['raw']['applies'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRawEvents(): array
    {
        return $this->data['raw']['events'] ?? [];
    }

    public function hasData(): bool
    {
        $counts = $this->getCounts();

        return $counts['tableQueries'] > 0 || $counts['applies'] > 0 || $counts['events'] > 0;
    }
}
