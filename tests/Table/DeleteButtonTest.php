<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Table;

use PHPUnit\Framework\TestCase;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\DeleteButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

final class DeleteButtonTest extends TestCase
{
    public function testSerializesDeleteFlags(): void
    {
        $button = DeleteButton::create()
            ->url('/catalog/products/{id}/delete')
            ->confirm('Delete “{name}”?')
            ->csrfToken('csrf-demo')
            ->toArray();

        self::assertSame('delete', $button['id']);
        self::assertTrue($button['deleteButton']);
        self::assertSame('/catalog/products/{id}/delete', $button['url']);
        self::assertSame('Delete “{name}”?', $button['confirm']);
        self::assertSame('csrf-demo', $button['csrfToken']);
        self::assertSame('_token', $button['csrfField']);
        self::assertSame('id', $button['idField']);
        self::assertSame('destructive', $button['variant']);
    }

    public function testToColumnBuildsBlankHeaderActionColumn(): void
    {
        $column = DeleteButton::create()
            ->url('/items/{id}/delete')
            ->toColumn()
            ->toArray();

        self::assertSame('delete', $column['accessorKey']);
        self::assertSame('', $column['header']);
        self::assertSame('buttons', $column['meta']['format']);
        self::assertTrue($column['meta']['buttons'][0]['deleteButton']);
        self::assertSame('end', $column['meta']['align']);
    }

    public function testColumnsAcceptsDeleteButtonDirectly(): void
    {
        $config = TanstackDataTable::create('test')
            ->columns([
                Column::make('name')->label('Name'),
                DeleteButton::create()->url('/items/{id}/delete'),
            ])
            ->toArray();

        self::assertSame('delete', $config['columns'][1]['accessorKey']);
        self::assertTrue($config['columns'][1]['meta']['buttons'][0]['deleteButton']);
    }

    public function testColumnButtonFactoryReturnsDeleteButton(): void
    {
        self::assertInstanceOf(DeleteButton::class, ColumnButton::delete());
    }
}
