<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Table;

use PHPUnit\Framework\TestCase;
use SymfonyTanstackTableShadcnUiToolkit\Table\BulkAction;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

final class BulkActionTest extends TestCase
{
    public function testSerializesUrlAndCsrf(): void
    {
        $action = BulkAction::delete()
            ->url('/catalog/products/bulk-delete')
            ->csrfToken('csrf-demo')
            ->toArray();

        self::assertSame('delete', $action['id']);
        self::assertSame('Delete', $action['label']);
        self::assertSame('tanstack-table:bulk-delete', $action['event']);
        self::assertSame('/catalog/products/bulk-delete', $action['url']);
        self::assertSame('csrf-demo', $action['csrfToken']);
        self::assertSame('_token', $action['csrfField']);
    }

    public function testBulkActionsAreEmptyByDefault(): void
    {
        self::assertSame([], TanstackDataTable::create('test')->toArray()['bulkActions']);
    }

    public function testBulkActionsConfiguredOnTable(): void
    {
        $config = TanstackDataTable::create('test')
            ->bulkActions([
                BulkAction::freeze()
                    ->url('/items/bulk-freeze')
                    ->csrfToken('tok')
                    ->csrfField('_csrf'),
            ])
            ->toArray();

        self::assertCount(1, $config['bulkActions']);
        self::assertSame('freeze', $config['bulkActions'][0]['id']);
        self::assertSame('/items/bulk-freeze', $config['bulkActions'][0]['url']);
        self::assertSame('tok', $config['bulkActions'][0]['csrfToken']);
        self::assertSame('_csrf', $config['bulkActions'][0]['csrfField']);
    }
}
