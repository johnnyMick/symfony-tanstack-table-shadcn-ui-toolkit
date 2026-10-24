<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Fluent column definition for {@see TanstackDataTable}.
 *
 * {@see make()} sets the row / client {@code accessorKey} (JSON key + filterField id).
 * {@see label()} is the HTML UI header text. {@see field()} is the optional DQL/SQL
 * expression used by {@see TableQuery::applyDoctrineFilter()} via
 * {@see TanstackDataTable::fieldMap()}.
 *
 * Example:
 *
 * <code>
 * Column::make('category')
 *     ->label('Category')
 *     ->field('c.name')
 *     ->class('font-medium');
 * </code>
 */
final class Column
{
    private ?string $label = null;

    /**
     * DQL/SQL expression for server-side filtering (e.g. {@code p.sku}, {@code c.name}).
     */
    private ?string $field = null;

    /** @var array<string, mixed> */
    private array $meta = [];

    private bool $enableSorting = true;

    private ?bool $enableHiding = null;

    private function __construct(
        private readonly string $accessorKey,
    ) {
    }

    /**
     * @param string $accessorKey Row JSON key and client filterField id
     */
    public static function make(string $accessorKey): self
    {
        return new self($accessorKey);
    }

    /**
     * HTML UI display label (table header + filter dropdown).
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Database query expression for this column (Doctrine DQL / SQL fragment).
     * Used as the value in {@see TanstackDataTable::fieldMap()} keyed by accessorKey.
     */
    public function field(?string $field): self
    {
        $this->field = $field;

        return $this;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function meta(array $meta): self
    {
        $this->meta = array_replace($this->meta, $meta);

        return $this;
    }

    public function class(string $class): self
    {
        $this->meta['class'] = $class;

        return $this;
    }

    public function headClass(string $class): self
    {
        $this->meta['headClass'] = $class;

        return $this;
    }

    public function align(string $align): self
    {
        $this->meta['align'] = $align;

        return $this;
    }

    public function alignEnd(): self
    {
        return $this->align('end');
    }

    public function format(string $format): self
    {
        $this->meta['format'] = $format;

        return $this;
    }

    public function currency(): self
    {
        return $this->format('currency')->alignEnd();
    }

    public function number(): self
    {
        return $this->format('number')->alignEnd();
    }

    /**
     * Render cell text escaped (safe). Prefer this over {@see rawHtml()}.
     */
    public function html(): self
    {
        return $this->format('html');
    }

    /**
     * Render unsanitized HTML from the cell value (XSS risk — only for trusted content).
     */
    public function rawHtml(): self
    {
        $this->meta['format'] = 'html';
        $this->meta['htmlTrusted'] = true;

        return $this;
    }

    /**
     * @param array<string, string> $map    value => badge variant
     * @param array<string, string> $labels value => display label
     */
    public function badge(array $map = [], array $labels = [], ?string $defaultVariant = null): self
    {
        $this->meta['format'] = 'badge';
        if ($map !== []) {
            $this->meta['badgeMap'] = $map;
        }
        if ($labels !== []) {
            $this->meta['badgeLabels'] = $labels;
        }
        if (null !== $defaultVariant) {
            $this->meta['badgeVariant'] = $defaultVariant;
        }

        return $this;
    }

    /**
     * Row action buttons (edit / delete / custom). Not sortable; typically not hideable.
     *
     * @param list<ColumnButton|array<string, mixed>> $buttons
     */
    public function buttons(array $buttons): self
    {
        $normalized = [];
        foreach ($buttons as $button) {
            $normalized[] = $button instanceof ColumnButton ? $button->toArray() : $button;
        }

        $this->meta['format'] = 'buttons';
        $this->meta['buttons'] = $normalized;
        $this->enableSorting = false;
        if (!\array_key_exists('hideable', $this->meta)) {
            $this->meta['hideable'] = false;
        }

        return $this;
    }

    public function enableSorting(bool $enable = true): self
    {
        $this->enableSorting = $enable;

        return $this;
    }

    public function enableHiding(bool $enable = true): self
    {
        $this->enableHiding = $enable;

        return $this;
    }

    public function hideable(bool $hideable = true): self
    {
        $this->meta['hideable'] = $hideable;

        return $this;
    }

    public function getAccessorKey(): string
    {
        return $this->accessorKey;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $column = [
            'accessorKey' => $this->accessorKey,
            // TanStack Table / Twig still consume "header" as the UI label.
            'header' => $this->label ?? $this->accessorKey,
            'enableSorting' => $this->enableSorting,
        ];

        if (null !== $this->field && '' !== $this->field) {
            $column['field'] = $this->field;
        }

        if (null !== $this->enableHiding) {
            $column['enableHiding'] = $this->enableHiding;
        }

        if ($this->meta !== []) {
            $column['meta'] = $this->meta;
        }

        return $column;
    }
}
