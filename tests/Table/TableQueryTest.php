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
            'filter' => ' acme ',
            'filterField' => 'name',
            'filterOperator' => 'contains',
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
            'filterOperator' => 'regex',
        ]));

        self::assertSame(TableQuery::OPERATOR_STARTS_WITH, $query->filterOperator);
    }

    public function testPaginateRowsFiltersAndSlices(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alpha'],
            ['id' => 2, 'name' => 'Beta'],
            ['id' => 3, 'name' => 'Alpine'],
            ['id' => 4, 'name' => 'Gamma'],
        ];

        $query = new TableQuery(1, 2, 'al', 'name', TableQuery::OPERATOR_STARTS_WITH);
        [$page, $total] = $query->paginateRows($rows);

        self::assertSame(2, $total);
        self::assertCount(2, $page);
        self::assertSame(['Alpha', 'Alpine'], array_column($page, 'name'));
    }

    #[DataProvider('provideMatchCases')]
    public function testMatchesValue(string $operator, string $needle, mixed $value, bool $expected): void
    {
        $query = new TableQuery(1, 10, $needle, 'name', $operator);
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
}
