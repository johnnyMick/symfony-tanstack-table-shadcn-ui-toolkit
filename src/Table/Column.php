<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Fluent column definition for {@see TanstackDataTable}.
 *
 * {@see make()} sets the row / client {@code accessorKey} (JSON key + filterField id).
 * {@see label()} is the HTML UI header text. {@see field()} is the optional DQL/SQL
 * expression used by {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier}
 * (via {@see TanstackDataTable::fieldMap()} as {@code context['field_map']}).
 *
 * Example:
 *
 * <code>
 * Column::make('category')
 *     ->label('Category')
 *     ->field('c.name')
 *     ->join('p.category')
 *     ->class('font-medium');
 * </code>
 *
 * @phpstan-type JoinSpec array{alias?: string, path: string, type?: string, condition?: string|null}
 */
class Column
{
    private ?string $label = null;

    /**
     * DQL/SQL expression for server-side filtering (e.g. {@code p.sku}, {@code c.name}).
     */
    private ?string $field = null;

    /**
     * Relation joins required when this column is selected/filtered/sorted.
     *
     * @var list<JoinSpec>
     */
    private array $joins = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    private bool $enableSorting = true;

    private ?bool $enableHiding = null;

    /**
     * When false, the column starts hidden but remains in the Columns menu for the user to enable.
     * {@code null} = visible by default.
     */
    private ?bool $defaultVisible = null;

    /**
     * Advanced filter config. {@code null} = auto-infer from format; {@code false} = hidden.
     */
    private ColumnFilter|false|null $filter = null;

    protected function __construct(
        private readonly string $accessorKey,
    ) {
    }

    /**
     * @param string $accessorKey Row JSON key and client filterField id
     */
    public static function make(string $accessorKey): static
    {
        return new self($accessorKey);
    }

    /**
     * HTML UI display label (table header + filter dropdown).
     * Pass {@code ''} for a blank header (useful for icon-only action columns).
     * When omitted, the accessor key is used — except {@see buttons()} columns default to blank.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Database query expression for this column (Doctrine DQL / SQL fragment).
     * Used as the value in {@see TanstackDataTable::fieldMap()} keyed by accessorKey.
     */
    public function field(?string $field): static
    {
        $this->field = $field;

        return $this;
    }

    /**
     * Declare a relation join needed for this column (emitted into {@see TanstackDataTable::joinMap()}).
     *
     * @param string      $path      Association path (e.g. {@code p.category})
     * @param string|null $alias     Join alias; inferred from {@see field()} when omitted
     * @param string      $type      {@code left} (default) or {@code inner}
     * @param string|null $condition Optional WITH clause (no leading WITH)
     */
    public function join(
        string $path,
        ?string $alias = null,
        string $type = 'left',
        ?string $condition = null,
    ): static {
        $spec = ['path' => $path, 'type' => $type];
        if (null !== $alias && '' !== $alias) {
            $spec['alias'] = $alias;
        }
        if (null !== $condition && '' !== $condition) {
            $spec['condition'] = $condition;
        }
        $this->joins[] = $spec;

        return $this;
    }

    /**
     * Replace join chain for nested relations (e.g. order → customer).
     *
     * @param list<string|JoinSpec> $joins
     */
    public function joins(array $joins): static
    {
        $this->joins = [];
        foreach ($joins as $join) {
            if (\is_string($join) && '' !== $join) {
                $this->join($join);
                continue;
            }
            if (!\is_array($join)) {
                continue;
            }
            $path = $join['path'] ?? null;
            if (!\is_string($path) || '' === $path) {
                continue;
            }
            $this->join(
                $path,
                \is_string($join['alias'] ?? null) ? $join['alias'] : null,
                \is_string($join['type'] ?? null) ? $join['type'] : 'left',
                \is_string($join['condition'] ?? null) ? $join['condition'] : null,
            );
        }

        return $this;
    }

    /**
     * @return list<JoinSpec>
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function meta(array $meta): static
    {
        $this->meta = array_replace($this->meta, $meta);

        return $this;
    }

    public function class(string $class): static
    {
        $this->meta['class'] = $class;

        return $this;
    }

    public function headClass(string $class): static
    {
        $this->meta['headClass'] = $class;

        return $this;
    }

    /**
     * Cell / header text alignment: {@code start}|{@code left}, {@code center},
     * or {@code end}|{@code right}.
     */
    public function align(string $align): static
    {
        $normalized = match (mb_strtolower(trim($align))) {
            'start', 'left' => 'start',
            'center' => 'center',
            'end', 'right' => 'end',
            default => throw new \InvalidArgumentException(
                'Column::align() expects start|left, center, or end|right.',
            ),
        };
        $this->meta['align'] = $normalized;

        return $this;
    }

    public function alignStart(): static
    {
        return $this->align('start');
    }

    /** Alias of {@see alignStart()}. */
    public function alignLeft(): static
    {
        return $this->alignStart();
    }

    public function alignCenter(): static
    {
        return $this->align('center');
    }

    public function alignEnd(): static
    {
        return $this->align('end');
    }

    /** Alias of {@see alignEnd()}. */
    public function alignRight(): static
    {
        return $this->alignEnd();
    }

    public function format(string $format): static
    {
        $this->meta['format'] = $format;

        return $this;
    }

    public function currency(): static
    {
        return $this->format('currency')->alignEnd();
    }

    public function number(): static
    {
        return $this->format('number')->alignEnd();
    }

    /**
     * Render cell text escaped (safe). Prefer this over {@see rawHtml()}.
     */
    public function html(): static
    {
        return $this->format('html');
    }

    /**
     * Render unsanitized HTML from the cell value (XSS risk — only for trusted content).
     */
    public function rawHtml(): static
    {
        $this->meta['format'] = 'html';
        $this->meta['htmlTrusted'] = true;

        return $this;
    }

    /**
     * Badge cell format. Pass one {@see ColumnBadge} per value.
     *
     * <code>
     * ->badge([
     *     ColumnBadge::make('pending')->variant('secondary')->label('Pending'),
     *     ColumnBadge::make('paid')->variant('default')->label('Paid'),
     * ])
     * </code>
     *
     * @param list<ColumnBadge> $badges
     */
    public function badge(array $badges = []): static
    {
        $this->meta['format'] = 'badge';

        $map = [];
        $labels = [];
        foreach ($badges as $badge) {
            if (!$badge instanceof ColumnBadge) {
                throw new \InvalidArgumentException(\sprintf(
                    'Column::badge() expects a list of %s instances.',
                    ColumnBadge::class,
                ));
            }
            $map[$badge->getValue()] = $badge->getVariant();
            $labels[$badge->getValue()] = $badge->getLabel();
        }

        if ([] !== $map) {
            $this->meta['badgeMap'] = $map;
        }
        if ([] !== $labels) {
            $this->meta['badgeLabels'] = $labels;
        }

        return $this;
    }

    /**
     * Row action buttons (edit / delete / workspace tab / custom). Not sortable; typically not hideable.
     *
     * @param list<ColumnButton|WorkspaceTabButton|DeleteButton|array<string, mixed>> $buttons
     */
    public function buttons(array $buttons): static
    {
        $normalized = [];
        foreach ($buttons as $button) {
            if ($button instanceof ColumnButton || $button instanceof WorkspaceTabButton || $button instanceof DeleteButton) {
                $normalized[] = $button->toArray();
            } else {
                $normalized[] = $button;
            }
        }

        $this->meta['format'] = 'buttons';
        $this->meta['buttons'] = $normalized;
        $this->enableSorting = false;
        if (!\array_key_exists('hideable', $this->meta)) {
            $this->meta['hideable'] = false;
        }

        return $this;
    }

    public function enableSorting(bool $enable = true): static
    {
        $this->enableSorting = $enable;

        return $this;
    }

    public function enableHiding(bool $enable = true): static
    {
        $this->enableHiding = $enable;

        return $this;
    }

    public function hideable(bool $hideable = true): static
    {
        $this->meta['hideable'] = $hideable;

        return $this;
    }

    /**
     * Start hidden in the table, but keep the column in the Columns menu so the user can show it.
     * Implies the column is hideable (listed in the visibility UI).
     */
    public function defaultHidden(bool $hidden = true): static
    {
        $this->defaultVisible = !$hidden;
        if ($hidden && !\array_key_exists('hideable', $this->meta)) {
            $this->meta['hideable'] = true;
        }

        return $this;
    }

    /**
     * Explicit initial visibility ({@code false} = same as {@see defaultHidden()}).
     */
    public function defaultVisible(bool $visible = true): static
    {
        $this->defaultVisible = $visible;
        if (!$visible && !\array_key_exists('hideable', $this->meta)) {
            $this->meta['hideable'] = true;
        }

        return $this;
    }

    /**
     * Configure the advanced-filter control for this column.
     */
    public function filter(ColumnFilter $filter): static
    {
        $this->filter = $filter;

        return $this;
    }

    public function filterString(): static
    {
        return $this->filter(ColumnFilter::string());
    }

    public function filterNumber(): static
    {
        return $this->filter(ColumnFilter::number());
    }

    public function filterDate(): static
    {
        return $this->filter(ColumnFilter::date());
    }

    public function filterDateRange(): static
    {
        return $this->filter(ColumnFilter::dateRange());
    }

    /**
     * @param array<string, string> $choices value => label
     */
    public function filterChoices(array $choices, bool $multiple = false): static
    {
        return $this->filter(ColumnFilter::choice($choices, $multiple));
    }

    /**
     * Choice filter using labels (or map keys) already passed to {@see badge()}.
     * Call after {@see badge()} so you do not repeat the same value => label map.
     */
    public function filterBadgeChoices(bool $multiple = false): static
    {
        return $this->filterChoices($this->badgeChoiceLabels(), $multiple);
    }

    /**
     * Exclude this column from the advanced filter panel.
     */
    public function withoutFilter(): static
    {
        $this->filter = false;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    private function badgeChoiceLabels(): array
    {
        $labels = $this->meta['badgeLabels'] ?? null;
        if (\is_array($labels) && [] !== $labels) {
            $out = [];
            foreach ($labels as $value => $label) {
                $out[(string) $value] = (string) $label;
            }

            return $out;
        }

        $map = $this->meta['badgeMap'] ?? [];
        if (!\is_array($map) || [] === $map) {
            return [];
        }

        $out = [];
        foreach (array_keys($map) as $value) {
            $out[(string) $value] = (string) $value;
        }

        return $out;
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
        $isButtons = ($this->meta['format'] ?? null) === 'buttons';
        // Explicit label (including '') wins. Button columns default to a blank header;
        // other columns fall back to the accessor key.
        $header = $this->label ?? ($isButtons ? '' : $this->accessorKey);

        $column = [
            'accessorKey' => $this->accessorKey,
            // TanStack Table / Twig still consume "header" as the UI label.
            'header' => $header,
            'enableSorting' => $this->enableSorting,
        ];

        if (null !== $this->field && '' !== $this->field) {
            $column['field'] = $this->field;
        }

        if ([] !== $this->joins) {
            $column['joins'] = $this->joins;
        }

        if (null !== $this->enableHiding) {
            $column['enableHiding'] = $this->enableHiding;
        }

        if (null !== $this->defaultVisible) {
            $column['defaultVisible'] = $this->defaultVisible;
        }

        if ($this->meta !== []) {
            $column['meta'] = $this->meta;
        }

        $resolvedFilter = $this->resolveFilter();
        if (false === $resolvedFilter) {
            $column['filter'] = false;
        } elseif (null !== $resolvedFilter) {
            $column['filter'] = $resolvedFilter->toArray();
        }

        return $column;
    }

    /**
     * @return ColumnFilter|false|null false = disabled; null = not filterable (buttons)
     */
    private function resolveFilter(): ColumnFilter|false|null
    {
        if (false === $this->filter) {
            return false;
        }
        if ($this->filter instanceof ColumnFilter) {
            return $this->filter;
        }

        $format = $this->meta['format'] ?? null;
        if ('buttons' === $format) {
            return null;
        }
        if ('number' === $format || 'currency' === $format) {
            return ColumnFilter::number();
        }
        if ('badge' === $format) {
            $choices = $this->badgeChoiceLabels();
            if ([] !== $choices) {
                return ColumnFilter::choice($choices);
            }
        }
        if ('switch' === $format) {
            $labels = $this->meta['switchLabels'] ?? null;
            if (\is_array($labels) && [] !== $labels) {
                $choices = [];
                foreach ($labels as $value => $label) {
                    $choices[(string) $value] = (string) $label;
                }

                return ColumnFilter::choice($choices);
            }
        }

        return ColumnFilter::string();
    }
}
