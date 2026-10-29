<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Table;

use PHPUnit\Framework\TestCase;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableColors;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

final class TanstackDataTableTest extends TestCase
{
    public function testToArrayIncludesDefaultsAndColumns(): void
    {
        $table = TanstackDataTable::create('test')
            ->title('Products')
            ->url('/products/format/json')
            ->pageSize(10)
            ->columns([
                Column::make('edit')->buttons([ColumnButton::edit()]),
                Column::make('sku')->label('SKU')->field('p.sku'),
                Column::make('delete')->alignEnd()->buttons([ColumnButton::delete()]),
            ]);

        $config = $table->toArray();

        self::assertSame('Products', $config['title']);
        self::assertSame('/products/format/json', $config['url']);
        self::assertSame(10, $config['pageSize']);
        self::assertCount(3, $config['columns']);
        self::assertSame('edit', $config['columns'][0]['accessorKey']);
        self::assertSame('', $config['columns'][0]['header']);
        self::assertSame('buttons', $config['columns'][0]['meta']['format']);
        self::assertSame('sku', $config['columns'][1]['accessorKey']);
        self::assertSame('delete', $config['columns'][2]['accessorKey']);
        self::assertSame('', $config['columns'][2]['header']);
        self::assertCount(1, $config['columns'][2]['meta']['buttons']);
        self::assertArrayHasKey('colors', $config);
        self::assertSame('border', $config['colors']['border']);
        self::assertNotEmpty($config['headerActions']);
        self::assertSame([], $config['bulkActions']);
        self::assertSame('test', $config['id']);
    }

    public function testCreateRequiresNonEmptyId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TanstackDataTable::create('  ');
    }

    public function testTabTitlePatternIsSerialized(): void
    {
        $fromTemplate = TanstackDataTable::create('test')
            ->tabTitle('{name} · {category}')
            ->toArray();
        self::assertSame('{name} · {category}', $fromTemplate['tabTitle']);

        $fromFields = TanstackDataTable::create('test')
            ->tabTitle(['name', 'email'], ' — ')
            ->toArray();
        self::assertSame('{name} — {email}', $fromFields['tabTitle']);

        $default = TanstackDataTable::create('test')->toArray();
        self::assertNull($default['tabTitle']);
    }

    public function testOpensTabFlagIsSerialized(): void
    {
        self::assertFalse(TanstackDataTable::create('test')->toArray()['opensTab']);
        self::assertTrue(TanstackDataTable::create('test')->opensTab()->toArray()['opensTab']);
        self::assertFalse(TanstackDataTable::create('test')->opensTab(false)->toArray()['opensTab']);
        self::assertTrue(TanstackDataTable::create('test')->editOpensTab()->toArray()['opensTab']);
    }

    public function testAdvancedFiltersFlagIsSerialized(): void
    {
        self::assertTrue(TanstackDataTable::create('test')->toArray()['advancedFilters']);
        self::assertFalse(TanstackDataTable::create('test')->advancedFilters(false)->toArray()['advancedFilters']);
    }

    public function testDefaultHiddenIsSerialized(): void
    {
        $hidden = Column::make('createdAt')->label('Created')->defaultHidden()->toArray();
        self::assertFalse($hidden['defaultVisible']);
        self::assertTrue($hidden['meta']['hideable']);

        $visible = Column::make('sku')->defaultVisible(true)->toArray();
        self::assertTrue($visible['defaultVisible']);

        $default = Column::make('name')->toArray();
        self::assertArrayNotHasKey('defaultVisible', $default);
    }

    public function testEmptyLabelIsPreserved(): void
    {
        $blank = Column::make('edit')->label('')->buttons([ColumnButton::edit()])->toArray();
        self::assertSame('', $blank['header']);

        $buttonsDefault = Column::make('delete')->buttons([ColumnButton::delete()])->toArray();
        self::assertSame('', $buttonsDefault['header']);

        $dataDefault = Column::make('sku')->toArray();
        self::assertSame('sku', $dataDefault['header']);
    }

    public function testFieldMapOnlyIncludesColumnsWithField(): void
    {
        $table = TanstackDataTable::create('test')->columns([
            Column::make('sku')->field('p.sku'),
            Column::make('status'),
            Column::make('actions')->buttons([ColumnButton::edit()]),
        ]);

        self::assertSame(['sku' => 'p.sku'], $table->fieldMap());
    }

    public function testColorsMergePartialArray(): void
    {
        $table = TanstackDataTable::create('test')->colors(['border' => 'slate-400']);
        $colors = $table->toArray()['colors'];

        self::assertSame('slate-400', $colors['border']);
        self::assertSame('muted', $colors['headerBg']);
    }

    public function testColorsReplaceWithTableColorsInstance(): void
    {
        $table = TanstackDataTable::create('test')->colors(
            TableColors::create()->border('primary')->rowSelected('accent')
        );

        $colors = $table->toArray()['colors'];
        self::assertSame('primary', $colors['border']);
        self::assertSame('accent', $colors['rowSelected']);
    }

    public function testJsonPayloadShape(): void
    {
        $table = TanstackDataTable::create('test')->pageSize(10);
        $response = $table->json([
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
        ], 25, 2, 10);

        $payload = json_decode($response->getContent() ?: '[]', true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(25, $payload['total']);
        self::assertSame(2, $payload['page']);
        self::assertSame(10, $payload['pageSize']);
        self::assertSame(3, $payload['pageCount']);
        self::assertCount(2, $payload['data']);
    }

    public function testJsonProjectsFieldsFromTableQuery(): void
    {
        $table = TanstackDataTable::create('test')->pageSize(10);
        $query = new TableQuery(2, 10, fields: ['name']);
        $response = $table->json([
            ['id' => 1, 'name' => 'A', 'secret' => 'x'],
        ], 1, query: $query);

        $payload = json_decode($response->getContent() ?: '[]', true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(2, $payload['page']);
        self::assertSame(10, $payload['pageSize']);
        self::assertSame([['id' => 1, 'name' => 'A']], $payload['data']);
    }

    public function testRawHtmlMetaFlag(): void
    {
        $safe = Column::make('body')->html()->toArray();
        self::assertSame('html', $safe['meta']['format']);
        self::assertArrayNotHasKey('htmlTrusted', $safe['meta']);

        $raw = Column::make('body')->rawHtml()->toArray();
        self::assertSame('html', $raw['meta']['format']);
        self::assertTrue($raw['meta']['htmlTrusted']);
    }

    public function testPageSizeRejectsInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TanstackDataTable::create('test')->pageSize(0);
    }
}
