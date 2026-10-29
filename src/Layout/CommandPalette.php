<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Layout;

/**
 * Configures the AdminLayout workspace command palette (header icon / Ctrl⌘K).
 *
 * Pass via {@see AdminLayout::commandPalette()}.
 *
 * @example
 *  AdminLayout::create()
 *      ->commandPalette(
 *          CommandPalette::create()
 *              ->keyboardShortcut('k')
 *              ->commands([
 *                  Command::goToMainTab(),
 *                  Command::refreshMainTable(),
 *                  Command::closeAllTabs(),
 *                  Command::goToTabSlots(),
 *                  Command::closeTabSlots(),
 *                  Command::make('import')
 *                      ->label('Import CSV')
 *                      ->icon('lucide:download')
 *                      ->group('Actions')
 *                      ->confirm('Import CSV?', 'Existing rows may be updated.', 'Import')
 *                      ->event('catalog:import'),
 *              ])
 *      );
 */
final class CommandPalette
{
    private bool $enabled = true;

    private string $keyboardShortcut = 'k';

    /** @var list<array<string, mixed>> */
    private array $commands = [];

    private function __construct()
    {
        $this->commands = self::defaultCommands();
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultCommands(): array
    {
        return [
            Command::goToMainTab()->toArray(),
            Command::refreshMainTable()->toArray(),
            Command::closeAllTabs()->toArray(),
            Command::openSidebar()->toArray(),
            Command::closeSidebar()->toArray(),
            Command::showTabTitles()->toArray(),
            Command::showTabUrls()->toArray(),
            Command::goToTabSlots()->toArray(),
            Command::closeTabSlots()->toArray(),
        ];
    }

    public function enabled(bool $enabled = true): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function disabled(): self
    {
        return $this->enabled(false);
    }

    /**
     * Single key combined with Ctrl/Cmd (empty string disables the shortcut).
     */
    public function keyboardShortcut(string $keyboardShortcut): self
    {
        $this->keyboardShortcut = strtolower(trim($keyboardShortcut));

        return $this;
    }

    /**
     * Replace the command list.
     *
     * @param list<Command|array<string, mixed>> $commands
     */
    public function commands(array $commands): self
    {
        $normalized = [];
        foreach ($commands as $command) {
            $normalized[] = $command instanceof Command ? $command->toArray() : $command;
        }
        $this->commands = $normalized;

        return $this;
    }

    public function addCommand(Command|array $command): self
    {
        $this->commands[] = $command instanceof Command ? $command->toArray() : $command;

        return $this;
    }

    public function withoutCommands(): self
    {
        $this->commands = [];

        return $this;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     keyboardShortcut: string,
     *     commands: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'keyboardShortcut' => $this->keyboardShortcut,
            'commands' => $this->commands,
        ];
    }
}
