<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Table;

use PHPUnit\Framework\TestCase;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;
use SymfonyTanstackTableShadcnUiToolkit\Table\WorkspaceTabButton;

final class WorkspaceTabButtonTest extends TestCase
{
    public function testSerializesWorkspaceFlags(): void
    {
        $button = WorkspaceTabButton::create()
            ->url('/catalog/products/{id}/edit')
            ->toArray();

        self::assertSame('tab', $button['id']);
        self::assertTrue($button['workspaceTab']);
        self::assertTrue($button['contextMenu']);
        self::assertFalse($button['nested']);
        self::assertSame('workspace-tabs:open', $button['event']);
        self::assertSame('/catalog/products/{id}/edit', $button['url']);
        self::assertSame('Open', $button['label']);
    }

    public function testSerializesNestedFlag(): void
    {
        $button = WorkspaceTabButton::create()
            ->url('/catalog/categories/{id}/edit')
            ->nested()
            ->toArray();

        self::assertTrue($button['nested']);
        self::assertTrue($button['workspaceTab']);
    }

    public function testToColumnBuildsBlankHeaderActionColumn(): void
    {
        $column = WorkspaceTabButton::create()
            ->url('/items/{id}')
            ->toColumn()
            ->toArray();

        self::assertSame('tab', $column['accessorKey']);
        self::assertSame('', $column['header']);
        self::assertSame('buttons', $column['meta']['format']);
        self::assertTrue($column['meta']['buttons'][0]['workspaceTab']);
        self::assertTrue($column['meta']['buttons'][0]['contextMenu']);
        self::assertSame('/items/{id}', $column['meta']['buttons'][0]['url']);
    }

    public function testColumnsAcceptsWorkspaceTabButtonDirectly(): void
    {
        $config = TanstackDataTable::create('test')
            ->columns([
                WorkspaceTabButton::create()->url('/items/{id}'),
                Column::make('name')->label('Name'),
            ])
            ->toArray();

        self::assertSame('tab', $config['columns'][0]['accessorKey']);
        self::assertTrue($config['columns'][0]['meta']['buttons'][0]['workspaceTab']);
        self::assertTrue($config['columns'][0]['meta']['buttons'][0]['contextMenu']);
        self::assertSame('name', $config['columns'][1]['accessorKey']);
    }

    public function testGeneralPurposeTabColumnCanMixButtons(): void
    {
        $column = Column::make('tab')
            ->buttons([
                WorkspaceTabButton::create()->url('/items/{id}'),
                ColumnButton::make('copy')->label('Copy')->icon('lucide:copy'),
            ])
            ->toArray();

        self::assertSame('tab', $column['accessorKey']);
        self::assertCount(2, $column['meta']['buttons']);
        self::assertTrue($column['meta']['buttons'][0]['workspaceTab']);
        self::assertTrue($column['meta']['buttons'][0]['contextMenu']);
        self::assertSame('copy', $column['meta']['buttons'][1]['id']);
    }

    public function testColumnButtonFactoryReturnsWorkspaceTabButton(): void
    {
        self::assertInstanceOf(WorkspaceTabButton::class, ColumnButton::workspaceTab());
    }
}
