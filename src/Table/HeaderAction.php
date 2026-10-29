<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Fluent header action button for {@see TanstackDataTable}.
 *
 * Clicks run {@code tstdt--tanstack-table#headerAction}, which dispatches a bubbling
 * DOM {@code CustomEvent}. The default event name is {@code tanstack-table:{id}};
 * override it with {@see event()}.
 *
 * Example:
 *
 * <code>
 * $table->headerActions([
 *     HeaderAction::downloadCsv(),
 *     AddButton::create()->url('/catalog/products/new')->title('Add product'),
 *     HeaderAction::make('import')
 *         ->label('Import')
 *         ->icon('lucide:upload')
 *         ->variant('outline')
 *         ->event('catalog:import'),
 * ]);
 * </code>
 *
 * Listen in JS (custom actions):
 *
 * <code>
 * document.addEventListener('tanstack-table:download-csv', (e) => {
 *     // e.detail: { action, button, rows, config, table }
 * });
 * document.addEventListener('catalog:import', (e) => {
 *     // handle import using e.detail.rows / e.detail.config
 * });
 * </code>
 */
final class HeaderAction
{
    private string $label;

    private ?string $icon = null;

    private string $variant = 'default';

    private string $size = 'sm';

    private string $type = 'button';

    private ?string $action = null;

    private ?string $event = null;

    private function __construct(
        private readonly string $id,
    ) {
        $this->label = $id;
        $this->event = 'tanstack-table:'.$id;
    }

    public static function make(string $id): self
    {
        return new self($id);
    }

    public static function downloadCsv(): self
    {
        return self::make('download-csv')
            ->label('Download CSV')
            ->icon('lucide:download')
            ->variant('outline');
    }

    /**
     * Add button: opens a large dialog and loads {@see AddButton::url()}.
     *
     * Prefer {@see AddButton::create()}{@code ->url(...)} so the create form destination is set.
     */
    public static function addRow(): AddButton
    {
        return AddButton::create();
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function variant(string $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function size(string $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Stimulus action. Defaults to {@code click->tstdt--tanstack-table#headerAction} in Twig.
     * Override only when wiring a different controller method.
     */
    public function action(?string $action): self
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Custom DOM event name dispatched by the Stimulus controller on click
     * (default {@code tanstack-table:{id}}).
     */
    public function event(?string $event): self
    {
        $this->event = $event;

        return $this;
    }

    /**
     * @return array{
     *     id: string,
     *     label: string,
     *     icon: ?string,
     *     variant: string,
     *     size: string,
     *     type: string,
     *     action: ?string,
     *     event: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'icon' => $this->icon,
            'variant' => $this->variant,
            'size' => $this->size,
            'type' => $this->type,
            'action' => $this->action,
            'event' => $this->event,
        ];
    }
}
