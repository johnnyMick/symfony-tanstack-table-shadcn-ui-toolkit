<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

use Symfony\Component\HttpFoundation\JsonResponse;
use SymfonyTanstackTableShadcnUiToolkit\Query\QueryContext;

/**
 * Configures a {@see TanstackDataTable} Twig component from a controller action.
 *
 * @example
 *  $table = TanstackDataTable::create('catalog-products')
 *      ->title('Products')
 *      ->description('Catalog products')
 *      ->url($this->generateUrl('app_products_json'))
 *      ->pageSize(10)
 *      ->columns([
 *          Column::make('sku')->label('SKU')->field('p.sku')->class('font-mono text-xs'),
 *          Column::make('price')->label('Price')->field('p.price')->currency(),
 *      ])
 *      ->colors(TableColors::create()->border('border')->rowEven('muted'));
 *
 *  return $this->render('products.html.twig', ['table' => $table->toArray()]);
 *
 *  Twig: {@code <twig:tstdt:TanstackDataTable :config="table" />}
 *  Stimulus reads the same array from {@code data-config}.
 *  Server filter: {@code $applier->applyFilters($qb, $query->filters, $table->queryContext(Product::class));}
 */
final class TanstackDataTable
{
    private ?string $title = null;

    private ?string $description = null;

    /** @var list<array<string, mixed>> */
    private array $columns = [];

    /** @var list<array<string, mixed>> */
    private array $data = [];

    private ?string $url = null;

    private int $pageSize = 25;

    private string $emptyMessage = 'No rows found.';

    private string $filterPlaceholder = 'Filter…';

    private bool $advancedFilters = true;

    private bool $columnVisibility = true;

    /** @var list<array<string, mixed>> */
    private array $headerActions = [];

    /** @var list<array<string, mixed>> */
    private array $bulkActions = [];

    /** @var array<string, string> */
    private array $colors = [];

    private string $id;

    /**
     * Workspace tab label pattern.
     * Placeholders are data keys, e.g. {@code '{name} · {category}'} or {@code '{name} · {email}'}.
     */
    private ?string $tabTitle = null;

    /**
     * When true, {@see WorkspaceTabButton} morphs to a “focus tab” control after open
     * (and reverts when the tab is closed). Also implied when a workspace-tab button is present.
     */
    private bool $opensTab = false;

    private function __construct(string $id)
    {
        $this->id = self::normalizeId($id);
        $this->headerActions = [
            HeaderAction::downloadCsv()->toArray(),
            AddButton::create()->toArray(),
        ];
        $this->colors = TableColors::create()->toArray();
    }

    /**
     * @param string $id Required stable table id/name (DOM id + bulk-action {@code tableId})
     */
    public static function create(string $id): self
    {
        return new self($id);
    }

    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Label used when opening a workspace tab from a {@see WorkspaceTabButton}.
     *
     * Pass a template with data-field placeholders:
     * {@code ->tabTitle('{name}')},
     * {@code ->tabTitle('{name} · {category}')},
     * {@code ->tabTitle('{name} · {email}')}.
     *
     * Or pass field keys to join with {@code $separator}:
     * {@code ->tabTitle(['name', 'category'])},
     * {@code ->tabTitle(['name', 'email'], ' — ')}.
     *
     * @param string|list<string> $pattern
     */
    public function tabTitle(string|array $pattern, string $separator = ' · '): self
    {
        if (\is_array($pattern)) {
            $parts = [];
            foreach ($pattern as $key) {
                if (\is_string($key) && '' !== $key) {
                    $parts[] = '{'.$key.'}';
                }
            }
            $this->tabTitle = [] === $parts ? null : implode($separator, $parts);

            return $this;
        }

        $trimmed = trim($pattern);
        $this->tabTitle = '' === $trimmed ? null : $trimmed;

        return $this;
    }

    /**
     * When enabled, a {@see WorkspaceTabButton} morphs into a focus control after the
     * tab opens; closing the tab restores the original button.
     */
    public function opensTab(bool $enabled = true): self
    {
        $this->opensTab = $enabled;

        return $this;
    }

    /**
     * @deprecated Use {@see opensTab()}.
     */
    public function editOpensTab(bool $enabled = true): self
    {
        return $this->opensTab($enabled);
    }

    /**
     * @param list<Column|WorkspaceTabButton|DeleteButton|array<string, mixed>> $columns
     */
    public function columns(array $columns): self
    {
        $normalized = [];
        foreach ($columns as $column) {
            if ($column instanceof WorkspaceTabButton || $column instanceof DeleteButton) {
                $normalized[] = $column->toColumn()->toArray();
            } elseif ($column instanceof Column) {
                $normalized[] = $column->toArray();
            } else {
                $normalized[] = $column;
            }
        }
        $this->columns = $normalized;

        return $this;
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    public function data(array $data): self
    {
        $this->data = array_values($data);

        return $this;
    }

    public function url(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function pageSize(int $pageSize): self
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('pageSize must be >= 1.');
        }
        $this->pageSize = $pageSize;

        return $this;
    }

    public function emptyMessage(string $emptyMessage): self
    {
        $this->emptyMessage = $emptyMessage;

        return $this;
    }

    public function filterPlaceholder(string $filterPlaceholder): self
    {
        $this->filterPlaceholder = $filterPlaceholder;

        return $this;
    }

    /**
     * Show the collapsible advanced filter panel (per-column rules, AND-combined).
     */
    public function advancedFilters(bool $advancedFilters = true): self
    {
        $this->advancedFilters = $advancedFilters;

        return $this;
    }

    public function columnVisibility(bool $columnVisibility = true): self
    {
        $this->columnVisibility = $columnVisibility;

        return $this;
    }

    /**
     * Replace header action buttons. Pass an empty array to hide the actions bar.
     *
     * @param list<HeaderAction|AddButton|array<string, mixed>> $headerActions
     */
    public function headerActions(array $headerActions): self
    {
        $normalized = [];
        foreach ($headerActions as $action) {
            if ($action instanceof HeaderAction || $action instanceof AddButton) {
                $normalized[] = $action->toArray();
            } else {
                $normalized[] = $action;
            }
        }
        $this->headerActions = $normalized;

        return $this;
    }

    public function addHeaderAction(HeaderAction|AddButton|array $action): self
    {
        if ($action instanceof HeaderAction || $action instanceof AddButton) {
            $this->headerActions[] = $action->toArray();
        } else {
            $this->headerActions[] = $action;
        }

        return $this;
    }

    public function withoutHeaderActions(): self
    {
        $this->headerActions = [];

        return $this;
    }

    /**
     * Bulk action options (select box). Set in the controller action; empty by default.
     * Non-empty enables row checkboxes. Each action should include {@see BulkAction::url()}
     * and {@see BulkAction::csrfToken()} so the play button can emit them on the DOM event.
     *
     * @param list<BulkAction|array<string, mixed>> $bulkActions
     */
    public function bulkActions(array $bulkActions): self
    {
        $normalized = [];
        foreach ($bulkActions as $action) {
            $normalized[] = $action instanceof BulkAction ? $action->toArray() : $action;
        }
        $this->bulkActions = $normalized;

        return $this;
    }

    public function addBulkAction(BulkAction|array $action): self
    {
        $this->bulkActions[] = $action instanceof BulkAction ? $action->toArray() : $action;

        return $this;
    }

    public function withoutBulkActions(): self
    {
        $this->bulkActions = [];

        return $this;
    }

    /**
     * Stable table id/name used as the DOM id prefix and as {@code tableId} on bulk-action events.
     */
    public function id(string $id): self
    {
        $this->id = self::normalizeId($id);

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Table border / header / row colors. Pass {@see TableColors} or a partial array
     * of tokens (merged over defaults).
     *
     * @param TableColors|array{
     *     border?: string,
     *     headerBg?: string,
     *     headerText?: string,
     *     rowOdd?: string,
     *     rowEven?: string,
     *     rowHover?: string,
     *     rowSelected?: string
     * } $colors
     */
    public function colors(TableColors|array $colors): self
    {
        if ($colors instanceof TableColors) {
            $this->colors = $colors->toArray();
        } else {
            $this->colors = TableColors::create()->merge($this->colors)->merge($colors)->toArray();
        }

        return $this;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * accessorKey => DQL/SQL expression for columns that defined {@see Column::field()}.
     *
     * Pass as {@code context['field_map']} to
     * {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier::applyFilters()}
     * (or to {@see TableQuery::applyDoctrineFilter()} for the simpler path).
     *
     * @return array<string, string>
     */
    public function fieldMap(): array
    {
        $map = [];
        foreach ($this->columns as $column) {
            $key = $column['accessorKey'] ?? $column['id'] ?? null;
            $field = $column['field'] ?? null;
            if (!\is_string($key) || '' === $key || !\is_string($field) || '' === $field) {
                continue;
            }
            $map[$key] = $field;
        }

        return $map;
    }

    /**
     * accessorKey => join config from {@see Column::join()} / {@see Column::joins()}.
     *
     * @return array<string, list<array{alias?: string, path: string, type?: string, condition?: string|null}>>
     */
    public function joinMap(): array
    {
        $map = [];
        foreach ($this->columns as $column) {
            $key = $column['accessorKey'] ?? $column['id'] ?? null;
            if (!\is_string($key) || '' === $key) {
                continue;
            }

            $normalized = $this->normalizeColumnJoins($column['joins'] ?? null);
            if ([] !== $normalized) {
                $map[$key] = $normalized;
            }
        }

        return $map;
    }

    /**
     * @return list<array{alias?: string, path: string, type?: string, condition?: string|null}>
     */
    private function normalizeColumnJoins(mixed $joins): array
    {
        if (!\is_array($joins) || [] === $joins) {
            return [];
        }

        $normalized = [];
        foreach ($joins as $join) {
            $spec = $this->normalizeOneJoin($join);
            if (null !== $spec) {
                $normalized[] = $spec;
            }
        }

        return $normalized;
    }

    /**
     * @return array{alias?: string, path: string, type?: string, condition?: string|null}|null
     */
    private function normalizeOneJoin(mixed $join): ?array
    {
        if (\is_string($join) && '' !== $join) {
            return ['path' => $join, 'type' => 'left'];
        }

        if (!\is_array($join)) {
            return null;
        }

        $path = $join['path'] ?? null;
        if (!\is_string($path) || '' === $path) {
            return null;
        }

        $spec = [
            'path' => $path,
            'type' => $join['type'] ?? 'left',
        ];

        $alias = $join['alias'] ?? null;
        if (\is_string($alias) && '' !== $alias) {
            $spec['alias'] = $alias;
        }

        $condition = $join['condition'] ?? null;
        if (\is_string($condition) && '' !== $condition) {
            $spec['condition'] = $condition;
        }

        return $spec;
    }

    /**
     * Build a typed {@see \SymfonyTanstackTableShadcnUiToolkit\Query\QueryContext} from this table.
     *
     * @param list<string>          $extraAllowed
     * @param array<string, string> $datetimeFormats
     * @param array<string, mixed>  $joins          Extra/override joins merged over {@see joinMap()}
     * @param class-string|null     $controller     For {@see \SymfonyTanstackTableShadcnUiToolkit\Event\Query\AsTanStackDatatableField} matching
     * @param string|null           $action         Action / method name for attribute matching
     */
    public function queryContext(
        ?string $entity = null,
        array $extraAllowed = [],
        array $datetimeFormats = [],
        array $joins = [],
        ?string $controller = null,
        ?string $action = null,
    ): QueryContext {
        return QueryContext::fromTable(
            $this,
            $entity,
            $extraAllowed,
            $datetimeFormats,
            $joins,
            $controller,
            $action,
        );
    }

    /**
     * Props for {@code <twig:tstdt:TanstackDataTable :config="table" />} (also serialized to {@code data-config}).
     *
     * @return array{
     *     title: ?string,
     *     description: ?string,
     *     columns: list<array<string, mixed>>,
     *     data: list<array<string, mixed>>,
     *     url: ?string,
     *     pageSize: int,
     *     emptyMessage: string,
     *     filterPlaceholder: string,
     *     advancedFilters: bool,
     *     columnVisibility: bool,
     *     headerActions: list<array<string, mixed>>,
     *     bulkActions: list<array<string, mixed>>,
     *     colors: array<string, string>,
     *     id: string,
     *     tabTitle: ?string,
     *     opensTab: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'columns' => $this->columns,
            'data' => $this->data,
            'url' => $this->url,
            'pageSize' => $this->pageSize,
            'emptyMessage' => $this->emptyMessage,
            'filterPlaceholder' => $this->filterPlaceholder,
            'advancedFilters' => $this->advancedFilters,
            'columnVisibility' => $this->columnVisibility,
            'headerActions' => $this->headerActions,
            'bulkActions' => $this->bulkActions,
            'colors' => $this->colors,
            'id' => $this->id,
            'tabTitle' => $this->tabTitle,
            'opensTab' => $this->opensTab,
        ];
    }

    private static function normalizeId(string $id): string
    {
        $trimmed = trim($id);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('TanstackDataTable id/name is required and must be non-empty.');
        }

        return $trimmed;
    }

    /**
     * JSON payload for AJAX endpoints ({@code /format/json}).
     *
     * When {@code $query} is passed, rows are projected to {@see TableQuery::$fields}
     * (visible columns from the client) before serialization.
     *
     * @param list<array<string, mixed>>|null $data     Page rows (defaults to configured data)
     * @param int|null                        $total    Total row count across all pages
     * @param int|null                        $page     1-based page index
     * @param int|null                        $pageSize Page size used for this response
     */
    public function json(
        ?array $data = null,
        ?int $total = null,
        ?int $page = null,
        ?int $pageSize = null,
        ?TableQuery $query = null,
    ): JsonResponse {
        $rows = array_values($data ?? $this->data);
        if (null !== $query) {
            $rows = $query->projectRows($rows);
            $page ??= $query->page;
            $pageSize ??= $query->pageSize;
        }
        $resolvedPageSize = $pageSize ?? $this->pageSize;
        $resolvedPage = $page ?? 1;
        $resolvedTotal = $total ?? \count($rows);
        $pageCount = $resolvedTotal > 0 ? (int) ceil($resolvedTotal / max(1, $resolvedPageSize)) : 0;

        return new JsonResponse([
            'data' => $rows,
            'total' => $resolvedTotal,
            'page' => $resolvedPage,
            'pageSize' => $resolvedPageSize,
            'pageCount' => $pageCount,
            'pagination' => [
                'page' => $resolvedPage,
                'pageSize' => $resolvedPageSize,
                'total' => $resolvedTotal,
                'pageCount' => $pageCount,
            ],
        ]);
    }
}
