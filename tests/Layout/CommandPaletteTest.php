<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Layout;

use PHPUnit\Framework\TestCase;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminLayout;
use SymfonyTanstackTableShadcnUiToolkit\Layout\Command;
use SymfonyTanstackTableShadcnUiToolkit\Layout\CommandPalette;

final class CommandPaletteTest extends TestCase
{
    public function testDefaultsIncludeWorkspaceCommands(): void
    {
        $config = CommandPalette::create()->toArray();

        self::assertTrue($config['enabled']);
        self::assertSame('k', $config['keyboardShortcut']);
        self::assertCount(9, $config['commands']);
        self::assertSame(Command::ACTION_GO_MAIN, $config['commands'][0]['action']);
        self::assertSame('lucide:table-2', $config['commands'][0]['icon']);
        self::assertNull($config['commands'][0]['confirm']);
        self::assertSame(Command::ACTION_CLOSE_ALL, $config['commands'][2]['action']);
        self::assertSame('Close all tabs?', $config['commands'][2]['confirm']['title']);
        self::assertSame('Close all', $config['commands'][2]['confirm']['confirmLabel']);
        self::assertSame(Command::ACTION_OPEN_SIDEBAR, $config['commands'][3]['action']);
        self::assertSame(Command::ACTION_CLOSE_SIDEBAR, $config['commands'][4]['action']);
        self::assertSame(Command::ACTION_SHOW_TAB_TITLES, $config['commands'][5]['action']);
        self::assertSame(Command::ACTION_SHOW_TAB_URLS, $config['commands'][6]['action']);
        self::assertSame(Command::ACTION_GO_TAB_SLOTS, $config['commands'][7]['action']);
        self::assertTrue($config['commands'][7]['expandSlots']);
        self::assertSame('lucide:panel-top', $config['commands'][7]['icon']);
        self::assertSame(Command::ACTION_CLOSE_TAB_SLOTS, $config['commands'][8]['action']);
    }

    public function testConfirmCanBeCustomizedOrCleared(): void
    {
        $withConfirm = Command::make('purge')
            ->label('Purge cache')
            ->confirm('Purge cache?', 'This cannot be undone.', 'Purge')
            ->toArray();
        self::assertSame('Purge cache?', $withConfirm['confirm']['title']);
        self::assertSame('This cannot be undone.', $withConfirm['confirm']['description']);
        self::assertSame('Purge', $withConfirm['confirm']['confirmLabel']);

        $cleared = Command::closeAllTabs()->confirm(false)->toArray();
        self::assertNull($cleared['confirm']);
    }

    public function testCustomCommandsAndDisable(): void
    {
        $config = CommandPalette::create()
            ->keyboardShortcut('j')
            ->commands([
                Command::goToMainTab()->label('Focus list'),
                Command::make('import')
                    ->label('Import CSV')
                    ->group('Actions')
                    ->event('catalog:import'),
            ])
            ->toArray();

        self::assertSame('j', $config['keyboardShortcut']);
        self::assertCount(2, $config['commands']);
        self::assertSame('Focus list', $config['commands'][0]['label']);
        self::assertSame(Command::ACTION_EVENT, $config['commands'][1]['action']);
        self::assertSame('catalog:import', $config['commands'][1]['event']);

        self::assertFalse(CommandPalette::create()->disabled()->toArray()['enabled']);
    }

    public function testAdminLayoutSerializesCommandPalette(): void
    {
        $default = AdminLayout::create()->toArray();
        self::assertArrayHasKey('commandPalette', $default);
        self::assertTrue($default['commandPalette']['enabled']);

        $custom = AdminLayout::create()
            ->commandPalette(
                CommandPalette::create()
                    ->commands([Command::closeAllTabs()])
            )
            ->toArray();
        self::assertCount(1, $custom['commandPalette']['commands']);

        $disabled = AdminLayout::create()->withoutCommandPalette()->toArray();
        self::assertFalse($disabled['commandPalette']['enabled']);
    }

    public function testGoToTabRequiresPositiveIndex(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Command::goToTab(0);
    }
}
