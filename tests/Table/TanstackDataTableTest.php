<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Table;

use PHPUnit\Framework\TestCase;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableColors;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

final class TanstackDataTableTest extends TestCase
{
    public function testToArrayIncludesDefaultsAndColumns(): void
    {
        $table = TanstackDataTable::create()
            ->title('Products')
            ->url('/products/format/json')
            ->pageSize(10)
            ->columns([
                Column::make('sku')->label('SKU')->field('p.sku'),
                Column::make('actions')->buttons([
                    ColumnButton::edit(),
                    ColumnButton::delete(),
                ]),
            ]);

        $config = $table->toArray();

        self::assertSame('Products', $config['title']);
        self::assertSame('/products/format/json', $config['url']);
        self::assertSame(10, $config['pageSize']);
        self::assertCount(2, $config['columns']);
        self::assertSame('sku', $config['columns'][0]['accessorKey']);
        self::assertSame('buttons', $config['columns'][1]['meta']['format']);
        self::assertCount(2, $config['columns'][1]['meta']['buttons']);
        self::assertArrayHasKey('colors', $config);
        self::assertSame('border', $config['colors']['border']);
        self::assertNotEmpty($config['headerActions']);
        self::assertNotEmpty($config['bulkActions']);
    }

    public function testFieldMapOnlyIncludesColumnsWithField(): void
    {
        $table = TanstackDataTable::create()->columns([
            Column::make('sku')->field('p.sku'),
            Column::make('status'),
            Column::make('actions')->buttons([ColumnButton::edit()]),
        ]);

        self::assertSame(['sku' => 'p.sku'], $table->fieldMap());
    }

    public function testColorsMergePartialArray(): void
    {
        $table = TanstackDataTable::create()->colors(['border' => 'slate-400']);
        $colors = $table->toArray()['colors'];

        self::assertSame('slate-400', $colors['border']);
        self::assertSame('muted', $colors['headerBg']);
    }

    public function testColorsReplaceWithTableColorsInstance(): void
    {
        $table = TanstackDataTable::create()->colors(
            TableColors::create()->border('primary')->rowSelected('accent')
        );

        $colors = $table->toArray()['colors'];
        self::assertSame('primary', $colors['border']);
        self::assertSame('accent', $colors['rowSelected']);
    }

    public function testJsonPayloadShape(): void
    {
        $table = TanstackDataTable::create()->pageSize(10);
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
        TanstackDataTable::create()->pageSize(0);
    }
}
