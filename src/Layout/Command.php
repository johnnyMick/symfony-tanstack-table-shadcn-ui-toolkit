<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Layout;

/**
 * A single command in {@see CommandPalette}.
 *
 * Built-in actions are interpreted by {@code tstdt--command-palette}.
 * Use {@see event()} for a custom DOM event, or {@see make()} + {@see action()}.
 */
final class Command
{
    public const ACTION_GO_MAIN = 'go-main';
    public const ACTION_REFRESH_MAIN = 'refresh-main';
    public const ACTION_CLOSE_ALL = 'close-all';
    public const ACTION_OPEN_SIDEBAR = 'open-sidebar';
    public const ACTION_CLOSE_SIDEBAR = 'close-sidebar';
    public const ACTION_GO_TAB_SLOTS = 'go-tab-slots';
    public const ACTION_CLOSE_TAB_SLOTS = 'close-tab-slots';
    public const ACTION_GO_TAB = 'go-tab';
    public const ACTION_CLOSE_TAB = 'close-tab';
    public const ACTION_SHOW_TAB_TITLES = 'show-tab-titles';
    public const ACTION_SHOW_TAB_URLS = 'show-tab-urls';
    public const ACTION_EVENT = 'event';

    private string $label;

    private string $group = 'Workspace';

    private string $keywords = '';

    private ?string $icon = null;

    private string $action;

    private ?string $event = null;

    private ?int $slotIndex = null;

    private bool $expandSlots = false;

    /** @var ?array{title: string, description: string, confirmLabel: string} */
    private ?array $confirm = null;

    private function __construct(
        private readonly string $id,
    ) {
        $this->label = $id;
        $this->action = self::ACTION_EVENT;
        $this->event = 'tanstack-command:'.$id;
    }

    public static function make(string $id): self
    {
        return new self($id);
    }

    public static function goToMainTab(): self
    {
        return self::make('go-main')
            ->label('Go to main tab')
            ->group('Workspace')
            ->keywords('go main tab list')
            ->icon('lucide:table-2')
            ->action(self::ACTION_GO_MAIN);
    }

    public static function refreshMainTable(): self
    {
        return self::make('refresh-main')
            ->label('Refresh main tab')
            ->group('Workspace')
            ->keywords('refresh table main reload tab page')
            ->icon('lucide:refresh-cw')
            ->action(self::ACTION_REFRESH_MAIN);
    }

    public static function closeAllTabs(): self
    {
        return self::make('close-all')
            ->label('Close all tabs')
            ->group('Workspace')
            ->keywords('close all tabs')
            ->icon('lucide:x')
            ->action(self::ACTION_CLOSE_ALL)
            ->confirm(
                'Close all tabs?',
                'All open tabs will be closed. The main list tab stays open.',
                'Close all',
            );
    }

    public static function openSidebar(): self
    {
        return self::make('open-sidebar')
            ->label('Open sidebar')
            ->group('Workspace')
            ->keywords('open sidebar navigation menu')
            ->icon('lucide:panel-left')
            ->action(self::ACTION_OPEN_SIDEBAR);
    }

    public static function closeSidebar(): self
    {
        return self::make('close-sidebar')
            ->label('Close sidebar')
            ->group('Workspace')
            ->keywords('close sidebar navigation menu')
            ->icon('lucide:panel-left-close')
            ->action(self::ACTION_CLOSE_SIDEBAR);
    }

    /**
     * Expands at runtime into “Go to tab 1…N” (N = layout tabCount).
     */
    public static function goToTabSlots(): self
    {
        return self::make('go-tab-slots')
            ->label('Go to tab {n}')
            ->group('Tabs')
            ->keywords('go tab')
            ->icon('lucide:panel-top')
            ->action(self::ACTION_GO_TAB_SLOTS)
            ->expandSlots();
    }

    /**
     * Expands at runtime into “Close tab 1…N” (N = layout tabCount).
     */
    public static function closeTabSlots(): self
    {
        return self::make('close-tab-slots')
            ->label('Close tab {n}')
            ->group('Tabs')
            ->keywords('close tab')
            ->icon('lucide:x')
            ->action(self::ACTION_CLOSE_TAB_SLOTS)
            ->expandSlots();
    }

    /**
     * Shown when tabs are currently labeled by URL — switches labels to titles.
     */
    public static function showTabTitles(): self
    {
        return self::make('show-tab-titles')
            ->label('Show tab titles')
            ->group('Tabs')
            ->keywords('show tab titles labels name')
            ->icon('lucide:type')
            ->action(self::ACTION_SHOW_TAB_TITLES);
    }

    /**
     * Shown when tabs are currently labeled by title — switches labels to URLs.
     */
    public static function showTabUrls(): self
    {
        return self::make('show-tab-urls')
            ->label('Show tab URLs')
            ->group('Tabs')
            ->keywords('show tab urls addresses links')
            ->icon('lucide:link')
            ->action(self::ACTION_SHOW_TAB_URLS);
    }

    public static function goToTab(int $slotIndex): self
    {
        if ($slotIndex < 1) {
            throw new \InvalidArgumentException('slotIndex must be >= 1.');
        }

        return self::make('go-tab-'.$slotIndex)
            ->label(\sprintf('Go to tab %d', $slotIndex))
            ->group('Tabs')
            ->keywords(\sprintf('go tab %d', $slotIndex))
            ->icon('lucide:panel-top')
            ->action(self::ACTION_GO_TAB)
            ->slotIndex($slotIndex);
    }

    public static function closeTab(int $slotIndex): self
    {
        if ($slotIndex < 1) {
            throw new \InvalidArgumentException('slotIndex must be >= 1.');
        }

        return self::make('close-tab-'.$slotIndex)
            ->label(\sprintf('Close tab %d', $slotIndex))
            ->group('Tabs')
            ->keywords(\sprintf('close tab %d', $slotIndex))
            ->icon('lucide:x')
            ->action(self::ACTION_CLOSE_TAB)
            ->slotIndex($slotIndex);
    }

    public function label(string $label): self
    {
        $trimmed = trim($label);
        $this->label = '' === $trimmed ? $this->id : $trimmed;

        return $this;
    }

    public function group(string $group): self
    {
        $trimmed = trim($group);
        $this->group = '' === $trimmed ? 'Workspace' : $trimmed;

        return $this;
    }

    public function keywords(string $keywords): self
    {
        $this->keywords = trim($keywords);

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function action(string $action): self
    {
        $this->action = trim($action);

        return $this;
    }

    /**
     * Dispatch a custom DOM event when the command runs ({@see ACTION_EVENT}).
     */
    public function event(?string $event): self
    {
        $this->event = null === $event ? null : trim($event);
        if (null !== $this->event && '' !== $this->event) {
            $this->action = self::ACTION_EVENT;
        }

        return $this;
    }

    public function slotIndex(int $slotIndex): self
    {
        if ($slotIndex < 1) {
            throw new \InvalidArgumentException('slotIndex must be >= 1.');
        }
        $this->slotIndex = $slotIndex;

        return $this;
    }

    public function expandSlots(bool $expand = true): self
    {
        $this->expandSlots = $expand;

        return $this;
    }

    /**
     * Show a confirmation dialog before running the command.
     *
     * Pass {@code false} to clear. Defaults: title “Are you sure?”, confirm label “Confirm”.
     *
     * @example Command::make('purge')->confirm('Purge cache?', 'This cannot be undone.', 'Purge')
     */
    public function confirm(string|bool $titleOrEnabled = true, ?string $description = null, ?string $confirmLabel = null): self
    {
        if (false === $titleOrEnabled) {
            $this->confirm = null;

            return $this;
        }

        $title = \is_string($titleOrEnabled) ? trim($titleOrEnabled) : '';
        $this->confirm = [
            'title' => '' !== $title ? $title : 'Are you sure?',
            'description' => trim((string) $description),
            'confirmLabel' => '' !== trim((string) $confirmLabel) ? trim((string) $confirmLabel) : 'Confirm',
        ];

        return $this;
    }

    /**
     * @return array{
     *     id: string,
     *     label: string,
     *     group: string,
     *     keywords: string,
     *     icon: ?string,
     *     action: string,
     *     event: ?string,
     *     slotIndex: ?int,
     *     expandSlots: bool,
     *     confirm: ?array{title: string, description: string, confirmLabel: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'group' => $this->group,
            'keywords' => $this->keywords,
            'icon' => $this->icon,
            'action' => $this->action,
            'event' => $this->event,
            'slotIndex' => $this->slotIndex,
            'expandSlots' => $this->expandSlots,
            'confirm' => $this->confirm,
        ];
    }
}
