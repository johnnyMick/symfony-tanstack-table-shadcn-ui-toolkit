<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Table;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;

final class TableQueryTest extends TestCase
{
    public function testFromRequestParsesAndClamps(): void
    {
        $query = TableQuery::fromRequest(new Request([
            'page' => '2',
            'pageSize' => '500',
            'filters' => [
                ['field' => 'name', 'operator' => 'contains', 'value' => ' acme '],
            ],
        ]), 25, 100);

        self::assertSame(2, $query->page);
        self::assertSame(100, $query->pageSize);
        self::assertSame('acme', $query->filter);
        self::assertSame('name', $query->filterField);
        self::assertSame(TableQuery::OPERATOR_CONTAINS, $query->filterOperator);
        self::assertSame(100, $query->getOffset());
        self::assertTrue($query->hasFilter());
    }

    public function testFromRequestFallsBackOnInvalidOperator(): void
    {
        $query = TableQuery::fromRequest(new Request([
            'filters' => [
                ['field' => 'name', 'operator' => 'regex', 'value' => 'x'],
            ],
        ]));

        self::assertSame(TableQuery::OPERATOR_CONTAINS, $query->filterOperator);
    }

    public function testFromRequestIgnoresLegacyFilterParams(): void
    {
        $query = TableQuery::fromRequest(new Request([
            'filter' => 'acme',
            'filterField' => 'name',
            'filterOperator' => 'contains',
        ]));

        self::assertFalse($query->hasFilter());
        self::assertSame([], $query->filters);
    }

    public function testPaginateRowsFiltersAndSlices(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alpha'],
            ['id' => 2, 'name' => 'Beta'],
            ['id' => 3, 'name' => 'Alpine'],
            ['id' => 4, 'name' => 'Gamma'],
        ];

        $query = new TableQuery(1, 2, filters: [
            ['field' => 'name', 'operator' => TableQuery::OPERATOR_STARTS_WITH, 'value' => 'al'],
        ]);
        [$page, $total] = $query->paginateRows($rows);

        self::assertSame(2, $total);
        self::assertCount(2, $page);
        self::assertSame(['Alpha', 'Alpine'], array_column($page, 'name'));
    }

    #[DataProvider('provideMatchCases')]
    public function testMatchesValue(string $operator, string $needle, mixed $value, bool $expected): void
    {
        $query = new TableQuery(1, 10, filters: [
            ['field' => 'name', 'operator' => $operator, 'value' => $needle],
        ]);
        self::assertSame($expected, $query->matchesValue($value));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: mixed, 3: bool}>
     */
    public static function provideMatchCases(): iterable
    {
        yield 'startsWith' => [TableQuery::OPERATOR_STARTS_WITH, 'al', 'Alpha', true];
        yield 'startsWith miss' => [TableQuery::OPERATOR_STARTS_WITH, 'al', 'Beta', false];
        yield 'endsWith' => [TableQuery::OPERATOR_ENDS_WITH, 'ha', 'Alpha', true];
        yield 'equals' => [TableQuery::OPERATOR_EQUALS, 'alpha', 'Alpha', true];
        yield 'contains' => [TableQuery::OPERATOR_CONTAINS, 'lp', 'Alpha', true];
        yield 'null value' => [TableQuery::OPERATOR_CONTAINS, 'x', null, false];
    }

    public function testPageCount(): void
    {
        $query = new TableQuery(1, 10);
        self::assertSame(0, $query->pageCount(0));
        self::assertSame(3, $query->pageCount(21));
    }

    public function testConstructorRejectsInvalidPage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TableQuery(0, 10);
    }

    public function testFromRequestParsesMultiFilters(): void
    {
        $query = TableQuery::fromRequest(new Request([
            'page' => '1',
            'pageSize' => '10',
            'filters' => [
                ['field' => 'name', 'operator' => 'contains', 'value' => ' al '],
                ['field' => 'sku', 'operator' => 'startsWith', 'value' => 'AB'],
                ['field' => 'empty', 'operator' => 'equals', 'value' => ''],
            ],
        ]));

        self::assertTrue($query->hasFilter());
        self::assertCount(2, $query->filters);
        self::assertSame('al', $query->filters[0]['value']);
        self::assertSame('name', $query->filterField);
        self::assertSame('al', $query->filter);
        self::assertSame(TableQuery::OPERATOR_CONTAINS, $query->filterOperator);
    }

    public function testFromRequestParsesFields(): void
    {
        $query = TableQuery::fromRequest(new Request([
            'fields' => ['sku', 'name', 'sku', 'bad.field', ''],
        ]));

        self::assertTrue($query->hasFields());
        self::assertSame(['sku', 'name'], $query->fields);
    }

    public function testProjectRowsKeepsIdAndSelectedFields(): void
    {
        $query = new TableQuery(1, 10, fields: ['name', 'status']);
        $rows = [
            ['id' => 1, 'name' => 'A', 'status' => 'active', 'secret' => 'x'],
            ['id' => 2, 'name' => 'B', 'status' => 'inactive', 'secret' => 'y'],
        ];

        $projected = $query->projectRows($rows);

        self::assertSame([
            ['id' => 1, 'name' => 'A', 'status' => 'active'],
            ['id' => 2, 'name' => 'B', 'status' => 'inactive'],
        ], $projected);
    }

    public function testProjectRowsNoopWithoutFields(): void
    {
        $query = new TableQuery(1, 10);
        $rows = [['id' => 1, 'name' => 'A', 'secret' => 'x']];

        self::assertSame($rows, $query->projectRows($rows));
    }

    public function testMultiFiltersAndTogether(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alpha', 'sku' => 'AB-1'],
            ['id' => 2, 'name' => 'Alpine', 'sku' => 'ZZ-1'],
            ['id' => 3, 'name' => 'Beta', 'sku' => 'AB-2'],
        ];

        $query = new TableQuery(1, 10, filters: [
            ['field' => 'name', 'operator' => 'startsWith', 'value' => 'Al'],
            ['field' => 'sku', 'operator' => 'startsWith', 'value' => 'AB'],
        ]);

        [$page, $total] = $query->paginateRows($rows);

        self::assertSame(1, $total);
        self::assertSame(['Alpha'], array_column($page, 'name'));
    }

    public function testNumberBetweenAndChoiceIn(): void
    {
        $rows = [
            ['id' => 1, 'price' => 10, 'status' => 'active'],
            ['id' => 2, 'price' => 25, 'status' => 'inactive'],
            ['id' => 3, 'price' => 40, 'status' => 'active'],
        ];

        $query = new TableQuery(1, 10, filters: [
            ['field' => 'price', 'operator' => 'between', 'value' => '20', 'valueTo' => '50'],
            ['field' => 'status', 'operator' => 'in', 'value' => 'active'],
        ]);

        [$page, $total] = $query->paginateRows($rows);

        self::assertSame(1, $total);
        self::assertSame([3], array_column($page, 'id'));
    }

    public function testDateRangeFilter(): void
    {
        $rows = [
            ['id' => 1, 'createdAt' => '2024-01-10'],
            ['id' => 2, 'createdAt' => '2024-02-15'],
            ['id' => 3, 'createdAt' => '2024-03-20'],
        ];

        $query = new TableQuery(1, 10, filters: [
            ['field' => 'createdAt', 'operator' => 'between', 'value' => '2024-02-01', 'valueTo' => '2024-02-28'],
        ]);

        [$page, $total] = $query->paginateRows($rows);

        self::assertSame(1, $total);
        self::assertSame([2], array_column($page, 'id'));
    }
}
