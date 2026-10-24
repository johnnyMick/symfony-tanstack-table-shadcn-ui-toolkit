# Symfony TanStack Table shadcn/ui Toolkit

Symfony bundle that ships a TanStack Table and shadcn/ui toolkit.

## Requirements

- PHP 8.2 or newer
- Symfony 7.4 or 8.0

## Install

The package is not published on Packagist yet. Point Composer at this repository, then require it:

```bash
composer config repositories.tanstack-table-toolkit vcs https://github.com/johnnyMick/symfony-tanstack-table-shadcn-ui-toolkit
composer require johnnymick/symfony-tanstack-table-shadcn-ui-toolkit:1.0.x-dev
```

Symfony Flex registers the bundle from the `symfony-bundle` package type. In an application that does not use Flex, register the bundle in `config/bundles.php`:

```php
return [
    SymfonyTanstackTableShadcnUiToolkit\SymfonyTanstackTableShadcnUiToolkit::class => ['all' => true],
];
```

## Post Install

### 1. Maker commands

Requires [MakerBundle](https://symfony.com/bundles/SymfonyMakerBundle/current/index.html) (`composer require --dev symfony/maker-bundle`).

Most makers accept `--force` (`-f`) to overwrite existing files.

#### Core UI

| Command | What it does |
|---------|----------------|
| `make:tstdt-data-table` | Scaffolds `tstdt_TanstackDataTable` plus `tstdt_Button`, `tstdt_Input`, `tstdt_Select`, `tstdt_DropdownMenu`, `tstdt_Separator`, `tstdt_Card`. Optional `--with-controllers`, `--with-icons`, and kits: `--with-badge`, `--with-dialog`, `--with-tooltip`, `--with-avatar`, `--with-table`, `--with-admin-layout`. |
| `make:tstdt-admin-layout` | Scaffolds the admin shell: `tstdt_AdminLayout`, `tstdt_Sidebar`, `tstdt_Sheet`, `tstdt_Collapsible`, `tstdt_Avatar`, `tstdt_Tooltip`, related Stimulus controllers, and Lucide icons. |
| `make:tstdt-icons` | Copies Lucide SVGs used by the table and admin UI into `assets/icons/lucide/`. |
| `make:tstdt-stimulus` | Copies Stimulus controllers only (`tanstack-table`, `select`, `dropdown-menu`, `sidebar`, `collapsible`, `dialog`, `tooltip`, `csrf-protection`). |
| `make:tstdt-theme` | Installs the demo shadcn theme CSS as `assets/styles/tanstack-theme.css` (matches `TableColors` semantic tokens). Use `--as-app-css` to also write `assets/styles/app.css`. |
| `make:tstdt-kit` | Install one or more kits by name (`badge`, `dialog`, `admin-layout`, `stimulus`, `theme`, …). Use `--list` to list kits. |

#### Controllers & resources

| Command | What it does |
|---------|----------------|
| `make:tstdt-data-table-controller` | Creates a Catalog/Sales-style controller with HTML pages + `/format/json` AJAX endpoints for one or more resources (`-r`). Use `--no-doctrine` for stub rows. |
| `make:tstdt-crud-resource` | Creates a single CRUD-style table resource (controller + JSON + page) with `ColumnButton::edit()` / `delete()`. Options: `-c` controller name, `--with-admin-layout`, `--no-doctrine`. |

#### Demo app pieces

| Command | What it does |
|---------|----------------|
| `make:tstdt-demo-dashboard` | Creates a dashboard page with DB row-count cards (`DemoController` + `templates/demo/index.html.twig`). Optional `--with-admin-layout`. |
| `make:tstdt-settings-page` | Creates Settings pages: demo-data table (row counts via AJAX) + preferences. Options: `--with-admin-layout`, `--with-reset` (Reset button). |
| `make:tstdt-demo-seeder` | Creates `Datatable/DemoSeeder` + `app:demo:reset` console command. Optional `--with-http-reset` for `POST /demo/reset`. |

#### Quick start examples

```bash
# Core table UI
php bin/console make:tstdt-data-table --with-controllers --with-icons
php bin/console make:tstdt-data-table --with-admin-layout --with-badge

# Admin shell only
php bin/console make:tstdt-admin-layout

# Controllers / resources
php bin/console make:tstdt-data-table-controller Catalog
php bin/console make:tstdt-data-table-controller Sales -r Order -r Customer
php bin/console make:tstdt-crud-resource Product --with-admin-layout

# Demo pieces
php bin/console make:tstdt-demo-dashboard --with-admin-layout
php bin/console make:tstdt-settings-page --with-admin-layout --with-reset
php bin/console make:tstdt-demo-seeder --with-http-reset

# Assets only
php bin/console make:tstdt-icons
php bin/console make:tstdt-stimulus
php bin/console make:tstdt-theme --as-app-css --force
php bin/console make:tstdt-kit badge dialog --force
```

Bundle Twig path: after install, skeleton components are also available as `@tstdt/…` for `{% include %}` / `{% embed %}`. Anonymous `<twig:tstdt_*>` tags still need a scaffold (`make:tstdt-data-table`) so Stimulus controllers and UX component discovery work in the app.

**Security:** `Column::html()` escapes cell output. Use `Column::rawHtml()` only for trusted HTML (XSS risk).

See also [CHANGELOG.md](CHANGELOG.md).

### 2. Shadcn UI kit

Required CSS and theme. Full instructions: [Symfony UX shadcn kit](https://ux.symfony.com/toolkit/kits/shadcn)

AssetMapper:

```bash
php bin/console importmap:require shadcn/dist/tailwind.css tw-animate-css/dist/tw-animate.css
```

Then update `assets/styles/app.css` with the theme variables and imports shown on that page.

Also install Tailwind merge support used by the Input component:

```bash
composer require tales-from-a-dev/twig-tailwind-extra
```

### 3. TanStack Table

```bash
php bin/console importmap:require @tanstack/table-core
```

### 4. Configure the table in the controller

```php
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\HeaderAction;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableColors;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

$table = TanstackDataTable::create()
    ->title('Catalog products')
    ->description('Sort, filter, and paginate client-side.')
    ->url($this->generateUrl('app_catalog_products_json'))
    ->pageSize(10)
    ->emptyMessage('No products yet.')
    ->colors(
        TableColors::create()
            ->border('border')
            ->headerBg('muted')
            ->rowOdd('background')
            ->rowEven('muted')
            ->rowHover('accent')
            ->rowSelected('primary/15')
    )
    // or partial: ->colors(['border' => 'slate-400', 'rowSelected' => 'primary/25'])
    ->columns([
        Column::make('sku')->label('SKU')->field('p.sku')->class('font-mono text-xs'),
        Column::make('price')->label('Price')->field('p.price')->currency(),
        Column::make('status')->label('Status')->badge(
            ['active' => 'default', 'inactive' => 'secondary'],
            ['active' => 'Active', 'inactive' => 'Inactive'],
        ),
        Column::make('actions')
            ->label('Actions')
            ->alignEnd()
            ->buttons([
                ColumnButton::edit(),
                ColumnButton::delete(),
            ]),
    ])
    ->headerActions([
        HeaderAction::downloadCsv(),
        HeaderAction::addRow(),
        // Custom id + DOM event name (dispatched by tanstack_table_controller):
        HeaderAction::make('import')
            ->label('Import')
            ->icon('lucide:upload')
            ->variant('outline')
            ->event('catalog:import'),
    ])
    // bulkActions default to delete / update / freeze / unfreeze
    // ->bulkActions([...]) to replace, ->withoutBulkActions() to hide

return $this->render('catalog/products.html.twig', [
    'table' => $table->toArray(),
]);
```

Header action clicks run `tanstack-table#headerAction`, which dispatches a bubbling `CustomEvent`.
Default event name is `tanstack-table:{id}` (override with `->event(...)`). Listen in JS:

```js
document.addEventListener('tanstack-table:download-csv', (e) => {
  // e.detail: { action, button, rows, config, table }
});
document.addEventListener('catalog:import', (e) => { /* ... */ });
```

Row action buttons (`Column::buttons()` / `ColumnButton`) dispatch `tanstack-table:row-{id}` (or a custom `->event(...)`) with the row:

```js
document.addEventListener('tanstack-table:row-edit', (e) => {
  // e.detail: { action, button, row, rowId, config, table }
});
document.addEventListener('tanstack-table:row-delete', (e) => {
  // e.detail.row = full row object
});
```

By default every table gets bulk actions (delete, update, freeze, unfreeze) with row checkboxes and a “Bulk actions” select.
Choosing an option dispatches `tanstack-table:bulk-{id}` (or a custom `->event(...)`) with the selected rows:

```js
document.addEventListener('tanstack-table:bulk-delete', (e) => {
  // e.detail.rows = full selected row objects (not just ids)
  // e.detail: { action, rows, rowIds, config, table }
});
```

JSON endpoint (query params `page`, `pageSize`, `filter`, `filterField`, `filterOperator`):

```php
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;

public function productsJson(Request $request): JsonResponse
{
    $table = $this->productsTable();
    $query = TableQuery::fromRequest($request, $table->getPageSize());
    [$rows, $total] = $this->productRowsPage($query);

    return $table->json($rows, $total, $query->page, $query->pageSize);
    // { "data": [...], "total": 60, "page": 1, "pageSize": 10, "pageCount": 6 }
}

// In productRowsPage() — field map comes from Column::field():
$query->applyDoctrineFilter($qb, $table->fieldMap());
```

Twig (config is also exposed as `data-config` for the Stimulus controller):

```twig
<twig:tstdt_TanstackDataTable :config="table" />
```
