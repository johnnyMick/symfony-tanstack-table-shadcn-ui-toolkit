<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Table;

use PHPUnit\Framework\TestCase;
use SymfonyTanstackTableShadcnUiToolkit\Table\AddButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\HeaderAction;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

final class AddButtonTest extends TestCase
{
    public function testSerializesAddDrawerFlags(): void
    {
        $button = AddButton::create()
            ->url('/catalog/products/new')
            ->title('Add product')
            ->side('left')
            ->toArray();

        self::assertSame('add-row', $button['id']);
        self::assertTrue($button['addButton']);
        self::assertSame('/catalog/products/new', $button['url']);
        self::assertSame('Add product', $button['title']);
        self::assertSame('left', $button['side']);
        self::assertSame('click->tstdt--tanstack-table#addAction', $button['action']);
    }

    public function testHeaderActionsAcceptsAddButton(): void
    {
        $config = TanstackDataTable::create('test')
            ->headerActions([
                HeaderAction::downloadCsv(),
                AddButton::create()->url('/items/new')->title('Add item'),
            ])
            ->toArray();

        self::assertTrue($config['headerActions'][1]['addButton']);
        self::assertSame('/items/new', $config['headerActions'][1]['url']);
    }

    public function testHeaderActionFactoryReturnsAddButton(): void
    {
        self::assertInstanceOf(AddButton::class, HeaderAction::addRow());
    }
}
