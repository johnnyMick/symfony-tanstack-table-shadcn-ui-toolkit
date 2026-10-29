<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use SymfonyTanstackTableShadcnUiToolkit\DataCollector\DatatableDataCollector;
use SymfonyTanstackTableShadcnUiToolkit\DataCollector\DatatableTrace;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\TransformRowsEvent;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;

final class DatatableDataCollectorTest extends TestCase
{
    public function testLateCollectAggregatesTrace(): void
    {
        $trace = new DatatableTrace();
        $query = TableQuery::fromRequest(new Request([
            'page' => '1',
            'pageSize' => '10',
            'fields' => ['name', 'sku'],
            'filters' => [
                ['field' => 'name', 'operator' => 'contains', 'value' => 'acme'],
            ],
        ]));

        // fromRequest already recorded via DatatableTrace::current()
        self::assertNotEmpty($trace->getTableQueries());
        self::assertSame(['name', 'sku'], $trace->getTableQueries()[0]['fields']);

        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('getAllAliases')->willReturn(['p']);
        $event = new ApplyFilterEvent($qb, 'name', 'contains', 'acme', 'p', ['allowed_fields' => ['name']]);
        $event->stopPropagation();

        $trace->addApply('filters', [
            'rootAlias' => 'p',
            'filters' => $query->filters,
            'events' => [DatatableTrace::filterEventPayload($event)],
            'context' => ['allowed_fields' => ['name']],
            'dql' => 'SELECT p FROM Product p',
            'parameters' => [],
        ]);

        $collector = new DatatableDataCollector($trace);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertTrue($collector->hasData());
        self::assertSame(1, $collector->getCounts()['tableQueries']);
        self::assertSame(1, $collector->getCounts()['applies']);
        self::assertSame(1, $collector->getCounts()['filterEvents']);
        self::assertSame(0, $collector->getCounts()['joinEvents']);
        self::assertSame(0, $collector->getCounts()['transformEvents']);
        self::assertSame(1, $collector->getCounts()['stoppedEvents']);
        self::assertSame('tstdt.datatable', $collector->getName());
    }

    public function testLateCollectRecordsJoinEvents(): void
    {
        $trace = new DatatableTrace();
        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('getAllAliases')->willReturn(['p']);
        $event = new ApplyJoinEvent($qb, 'category', 'c', 'p.category', 'p', [
            'joins' => ['category' => 'p.category'],
        ]);
        $event->stopPropagation();

        $trace->addApply('selects', [
            'rootAlias' => 'p',
            'fields' => ['category'],
            'events' => [DatatableTrace::joinEventPayload($event)],
            'context' => DatatableTrace::sanitizeContext([
                'joins' => ['category' => 'p.category'],
            ]),
            'dql' => 'SELECT p FROM Product p LEFT JOIN p.category c',
            'parameters' => [],
        ]);

        $collector = new DatatableDataCollector($trace);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertTrue($collector->hasData());
        self::assertSame(1, $collector->getCounts()['joinEvents']);
        self::assertSame(1, $collector->getCounts()['stoppedEvents']);
        self::assertSame('join', $collector->getRawApplies()[0]['events'][0]['type']);
        self::assertSame('c', $collector->getRawApplies()[0]['events'][0]['joinAlias']);
        self::assertSame('p.category', $collector->getRawApplies()[0]['events'][0]['joinPath']);
    }

    public function testLateCollectRecordsTransformApply(): void
    {
        $trace = new DatatableTrace();
        $before = [
            ['id' => 1, 'status' => true, 'name' => 'A'],
            ['id' => 2, 'status' => false, 'name' => 'B'],
        ];
        $event = new TransformRowsEvent($before, [
            'entity' => 'App\\Entity\\Product',
            'datetime_formats' => ['createdAt' => 'Y-m-d'],
        ]);
        $event->mapColumn('status', static fn (mixed $v): string => $v ? 'active' : 'inactive');

        $trace->addApply('transform', [
            'rowCount' => 2,
            'columns' => DatatableTrace::rowColumnKeys($event->getRows()),
            'changedColumns' => DatatableTrace::changedColumns($before, $event->getRows()),
            'events' => [DatatableTrace::transformEventPayload($event, $before)],
            'context' => DatatableTrace::sanitizeContext($event->getContext()),
            'sampleBefore' => DatatableTrace::sanitizeValue(\array_slice($before, 0, 3)),
            'sampleAfter' => DatatableTrace::sanitizeValue(\array_slice($event->getRows(), 0, 3)),
        ]);

        $collector = new DatatableDataCollector($trace);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertTrue($collector->hasData());
        self::assertSame(1, $collector->getCounts()['applies']);
        self::assertSame(1, $collector->getCounts()['transformEvents']);
        self::assertSame(['status'], $collector->getRawApplies()[0]['changedColumns']);
        self::assertSame('active', $collector->getRawApplies()[0]['sampleAfter'][0]['status']);
    }

    public function testChangedColumnsDetectsDiffs(): void
    {
        $before = [['id' => 1, 'status' => true, 'sku' => 'A']];
        $after = [['id' => 1, 'status' => 'active', 'sku' => 'A']];

        self::assertSame(['status'], DatatableTrace::changedColumns($before, $after));
        self::assertSame(['id', 'status', 'sku'], DatatableTrace::rowColumnKeys($after));
    }

    public function testResetClearsTrace(): void
    {
        $trace = new DatatableTrace();
        $trace->addApply('selects', ['rootAlias' => 'e', 'fields' => ['id'], 'context' => [], 'dql' => '']);

        $collector = new DatatableDataCollector($trace);
        $collector->reset();

        self::assertSame([], $trace->getApplies());
        self::assertFalse($collector->hasData());
    }

    public function testFormatDqlInsertsKeywordLineBreaks(): void
    {
        $formatted = DatatableTrace::formatDql(
            'SELECT p FROM App\\Entity\\Product p WHERE p.active = true AND p.name LIKE :q ORDER BY p.name ASC'
        );

        self::assertStringContainsString("\nFROM", $formatted);
        self::assertStringContainsString("\nWHERE", $formatted);
        self::assertStringContainsString("\nAND", $formatted);
        self::assertStringContainsString("\nORDER BY", $formatted);
    }
}
