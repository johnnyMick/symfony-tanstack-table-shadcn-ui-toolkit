<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Configures a {@see TanstackDataTable} Twig component from a controller action.
 *
 * @example
 *  $table = TanstackDataTable::create()
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
 *  Twig: {@code <twig:tstdt_TanstackDataTable :config="table" />}
 *  Stimulus reads the same array from {@code data-config}.
 *  Server filter: {@code $query->applyDoctrineFilter($qb, $table->fieldMap());}
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

    private bool $columnVisibility = true;

    /** @var list<array<string, mixed>> */
    private array $headerActions = [];

    /** @var list<array<string, mixed>> */
    private array $bulkActions = [];

    /** @var array<string, string> */
    private array $colors = [];

    private ?string $id = null;

    private function __construct()
    {
        $this->headerActions = [
            HeaderAction::downloadCsv()->toArray(),
            HeaderAction::addRow()->toArray(),
        ];
        $this->bulkActions = [
            BulkAction::delete()->toArray(),
            BulkAction::update()->toArray(),
            BulkAction::freeze()->toArray(),
            BulkAction::unfreeze()->toArray(),
        ];
        $this->colors = TableColors::create()->toArray();
    }

    public static function create(): self
    {
        return new self();
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
     * @param list<Column|array<string, mixed>> $columns
     */
    public function columns(array $columns): self
    {
        $normalized = [];
        foreach ($columns as $column) {
            $normalized[] = $column instanceof Column ? $column->toArray() : $column;
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

    public function columnVisibility(bool $columnVisibility = true): self
    {
        $this->columnVisibility = $columnVisibility;

        return $this;
    }

    /**
     * Replace header action buttons. Pass an empty array to hide the actions bar.
     *
     * @param list<HeaderAction|array<string, mixed>> $headerActions
     */
    public function headerActions(array $headerActions): self
    {
        $normalized = [];
        foreach ($headerActions as $action) {
            $normalized[] = $action instanceof HeaderAction ? $action->toArray() : $action;
        }
        $this->headerActions = $normalized;

        return $this;
    }

    public function addHeaderAction(HeaderAction|array $action): self
    {
        $this->headerActions[] = $action instanceof HeaderAction ? $action->toArray() : $action;

        return $this;
    }

    public function withoutHeaderActions(): self
    {
        $this->headerActions = [];

        return $this;
    }

    /**
     * Bulk action options (select box). Non-empty enables row checkboxes.
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

    public function id(?string $id): self
    {
        $this->id = $id;

        return $this;
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
     * Pass to {@see TableQuery::applyDoctrineFilter()}.
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
     * Props for {@code <twig:tstdt_TanstackDataTable :config="table" />} (also serialized to {@code data-config}).
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
     *     columnVisibility: bool,
     *     headerActions: list<array<string, mixed>>,
     *     bulkActions: list<array<string, mixed>>,
     *     colors: array<string, string>,
     *     id: ?string
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
            'columnVisibility' => $this->columnVisibility,
            'headerActions' => $this->headerActions,
            'bulkActions' => $this->bulkActions,
            'colors' => $this->colors,
            'id' => $this->id,
        ];
    }

    /**
     * JSON payload for AJAX endpoints ({@code /format/json}).
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
    ): JsonResponse {
        $rows = array_values($data ?? $this->data);
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
