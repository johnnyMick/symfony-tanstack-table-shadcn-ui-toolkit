<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Table;

use PHPUnit\Framework\TestCase;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnBadge;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnFilter;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnSwitch;

final class ColumnFilterTest extends TestCase
{
    public function testStringFilterOperators(): void
    {
        $filter = ColumnFilter::string()->toArray();

        self::assertSame(ColumnFilter::TYPE_STRING, $filter['type']);
        self::assertSame(
            ['contains', 'startsWith', 'endsWith', 'equals'],
            array_column($filter['operators'], 'id'),
        );
    }

    public function testNumberFilterOperators(): void
    {
        $filter = ColumnFilter::number()->toArray();

        self::assertSame(ColumnFilter::TYPE_NUMBER, $filter['type']);
        self::assertContains('gt', array_column($filter['operators'], 'id'));
        self::assertContains('between', array_column($filter['operators'], 'id'));
    }

    public function testChoiceFilterSingleAndMultiple(): void
    {
        $single = ColumnFilter::choice(['a' => 'A', 'b' => 'B'])->toArray();
        self::assertSame(ColumnFilter::TYPE_CHOICE, $single['type']);
        self::assertFalse($single['multiple']);
        self::assertSame(['equals'], array_column($single['operators'], 'id'));

        $multi = ColumnFilter::choice(['a' => 'A'], multiple: true)->toArray();
        self::assertTrue($multi['multiple']);
        self::assertSame(['in'], array_column($multi['operators'], 'id'));
    }

    public function testColumnSerializesExplicitFilters(): void
    {
        $column = Column::make('price')
            ->label('Price')
            ->filterNumber()
            ->toArray();

        self::assertSame(ColumnFilter::TYPE_NUMBER, $column['filter']['type']);
    }

    public function testColumnInfersNumberFromCurrencyFormat(): void
    {
        $column = Column::make('price')->currency()->toArray();

        self::assertSame(ColumnFilter::TYPE_NUMBER, $column['filter']['type']);
    }

    public function testColumnInfersChoiceFromBadgeLabels(): void
    {
        $column = Column::make('status')
            ->badge([
                ColumnBadge::make('active')->variant('default')->label('Active'),
                ColumnBadge::make('inactive')->variant('secondary')->label('Inactive'),
            ])
            ->toArray();

        self::assertSame(ColumnFilter::TYPE_CHOICE, $column['filter']['type']);
        self::assertSame(['active' => 'Active', 'inactive' => 'Inactive'], $column['filter']['choices']);
        self::assertSame(['active' => 'default', 'inactive' => 'secondary'], $column['meta']['badgeMap']);
    }

    public function testFilterBadgeChoicesReusesLabelsWithoutDuplication(): void
    {
        $column = Column::make('status')
            ->badge([
                ColumnBadge::make('pending')->variant('secondary')->label('Pending'),
                ColumnBadge::make('paid')->variant('default')->label('Paid'),
            ])
            ->filterBadgeChoices(multiple: true)
            ->toArray();

        self::assertSame(ColumnFilter::TYPE_CHOICE, $column['filter']['type']);
        self::assertTrue($column['filter']['multiple']);
        self::assertSame([
            'pending' => 'Pending',
            'paid' => 'Paid',
        ], $column['filter']['choices']);
    }

    public function testBadgeRejectsNonColumnBadgeEntries(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Column::make('status')->badge([
            ['value' => 'active', 'variant' => 'default', 'label' => 'Active'],
        ]);
    }

    public function testColumnSwitchSerializesMetaAndInfersChoiceFilter(): void
    {
        $column = ColumnSwitch::make('status')
            ->label('Status')
            ->url('/products/{id}/status')
            ->csrfToken('csrf-token')
            ->values('active', 'inactive', 'Active', 'Inactive')
            ->toArray();

        self::assertSame('switch', $column['meta']['format']);
        self::assertSame('/products/{id}/status', $column['meta']['switchUrl']);
        self::assertSame('csrf-token', $column['meta']['switchCsrfToken']);
        self::assertSame('active', $column['meta']['switchCheckedValue']);
        self::assertSame('inactive', $column['meta']['switchUncheckedValue']);
        self::assertSame(ColumnFilter::TYPE_CHOICE, $column['filter']['type']);
        self::assertSame(['active' => 'Active', 'inactive' => 'Inactive'], $column['filter']['choices']);
    }

    public function testColumnSwitchRejectsIdenticalValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ColumnSwitch::make('status')->values('on', 'on');
    }

    public function testColumnSwitchExtendsColumn(): void
    {
        $column = ColumnSwitch::make('active')->field('p.active')->class('text-center');

        self::assertInstanceOf(Column::class, $column);
        $array = $column->toArray();
        self::assertSame('p.active', $array['field']);
        self::assertSame('text-center', $array['meta']['class']);
        self::assertSame('switch', $array['meta']['format']);
    }

    public function testColumnAlignNormalizesLeftCenterRight(): void
    {
        self::assertSame('start', Column::make('a')->alignLeft()->toArray()['meta']['align']);
        self::assertSame('start', Column::make('b')->align('left')->toArray()['meta']['align']);
        self::assertSame('center', Column::make('c')->alignCenter()->toArray()['meta']['align']);
        self::assertSame('end', Column::make('d')->alignRight()->toArray()['meta']['align']);
        self::assertSame('end', Column::make('e')->align('right')->toArray()['meta']['align']);
    }

    public function testColumnAlignRejectsInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Column::make('sku')->align('middle');
    }

    public function testWithoutFilterDisablesAdvancedFilter(): void
    {
        $column = Column::make('sku')->withoutFilter()->toArray();

        self::assertFalse($column['filter']);
    }

    public function testDateRangeFilter(): void
    {
        $column = Column::make('createdAt')->filterDateRange()->toArray();

        self::assertSame(ColumnFilter::TYPE_DATE_RANGE, $column['filter']['type']);
        self::assertSame(['between'], array_column($column['filter']['operators'], 'id'));
    }
}
