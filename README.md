# Symfony TanStack Table shadcn/ui Toolkit

Symfony bundle that ships a TanStack Table and shadcn/ui toolkit.

**Docs:** [Events & Subscribers](docs/EVENTS.md) — index for the Doctrine event/subscriber pipeline (Mermaid sections: big picture, fetch sequence, joins, priorities, [custom fields](docs/CUSTOM_FIELD_SUBSCRIBERS.md)).

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
| `make:tstdt-data-table` | Scaffolds `tstdt:TanstackDataTable` plus `tstdt:Button`, `tstdt:Input`, `tstdt:Select`, `tstdt:DropdownMenu`, `tstdt:Separator`, `tstdt:Card`. Optional `--with-controllers`, `--with-icons`, and kits: `--with-badge`, `--with-dialog`, `--with-tooltip`, `--with-avatar`, `--with-table`, `--with-admin-layout`. |
| `make:tstdt-admin-layout` | Scaffolds the admin shell: `tstdt:AdminLayout` (workspace edit tabs), `tstdt:Sidebar`, `tstdt:Sheet`, `tstdt:Collapsible`, `tstdt:Avatar`, `tstdt:Tooltip`, related Stimulus controllers (`tstdt--workspace-tabs`, …), and Lucide icons. |
| `make:tstdt-icons` | Copies Lucide SVGs used by the table and admin UI into `assets/icons/lucide/`. |
| `make:tstdt-stimulus` | Copies Stimulus controllers under `assets/controllers/tstdt/` (`tstdt--tanstack-table`, `tstdt--select`, `tstdt--dropdown-menu`, `tstdt--sidebar`, `tstdt--collapsible`, `tstdt--dialog`, `tstdt--tooltip`, `tstdt--workspace-tabs`, `tstdt--csrf-protection`). |
| `make:tstdt-theme` | Installs the demo shadcn theme CSS as `assets/styles/tanstack-theme.css` (matches `TableColors` semantic tokens). Use `--as-app-css` to also write `assets/styles/app.css`. |
| `make:tstdt-kit` | Install one or more kits by name (`badge`, `dialog`, `admin-layout`, `stimulus`, `theme`, …). Use `--list` to list kits. |

#### Controllers & resources

| Command | What it does |
|---------|----------------|
| `make:tstdt-data-table-controller` | Creates a Catalog/Sales-style controller with HTML pages + `/format/json` AJAX endpoints for one or more resources (`-r`). Doctrine mode wires `DatatableQueryApplier` for filters. Use `--no-doctrine` for stub rows. |
| `make:tstdt-crud-resource` | Creates a single CRUD-style table resource (controller + JSON + page) with edit / delete actions and `DatatableQueryApplier` filters. With `--with-admin-layout`: workspace tabs (`WorkspaceTabButton`, edit route, tab partial). Options: `-c` controller name, `--no-doctrine`. |

#### Demo app pieces

| Command | What it does |
|---------|----------------|
| `make:tstdt-demo-dashboard` | Creates a dashboard page with DB row-count cards (`DemoController` + `templates/demo/index.html.twig`). Optional `--with-admin-layout`, `--with-http-reset` (`POST /demo/reset` on DemoController, synced from demo). |
| `make:tstdt-settings-page` | Creates Settings pages: demo-data table (row counts via AJAX) + preferences. Options: `--with-admin-layout`, `--with-reset` (Reset button). |
| `make:tstdt-demo-entities` | Creates demo Doctrine entities (`Category`, `Product`, `Customer`, `Order`, `OrderItem`) under `src/Entity/`, plus query field subscribers (status filter/select, category join, customer select examples) for `DatatableQueryApplier`. Optional `--with-user` for `User` + `UserRepository` (synced from demo). Also via `make:tstdt-kit demo-entities [--with-user]`. |
| `make:tstdt-demo-seeder` | Creates `Datatable/DemoSeeder` + `app:demo:reset`. Optional `--with-http-reset`, `--with-entities`, `--with-user` (admin/admin seed + schema rebuild). |

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
php bin/console make:tstdt-demo-dashboard --with-admin-layout --with-http-reset
php bin/console make:tstdt-settings-page --with-admin-layout --with-reset
php bin/console make:tstdt-demo-entities --with-user
php bin/console make:tstdt-demo-seeder --with-http-reset --with-entities --with-user

# Assets only
php bin/console make:tstdt-icons
php bin/console make:tstdt-stimulus
php bin/console make:tstdt-theme --as-app-css --force
php bin/console make:tstdt-kit badge dialog --force
```

Bundle Twig path: after install, skeleton components are also available as `@tstdt/…` for `{% include %}` / `{% embed %}`. Anonymous `<twig:tstdt:*>` tags still need a scaffold (`make:tstdt-data-table`) so Stimulus controllers and UX component discovery work in the app.

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

### 4. Configure the admin layout (optional)

Same fluent pattern as the table — build the shell in the controller and pass `:config="layout"`:

```php
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminBrand;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminLayout;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminUser;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavCollapsible;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavGroup;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavLink;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;
use SymfonyTanstackTableShadcnUiToolkit\Table\WorkspaceTabButton;

$layout = AdminLayout::create()
    ->brand(
        AdminBrand::create()
            ->title('DataTables Demo')
            ->subtitle('Admin console')
            ->href($this->generateUrl('app_demo'))
    )
    ->nav([
        NavGroup::make('Platform')->items([
            NavCollapsible::make('Catalog')
                ->icon('lucide:package')
                ->children([
                    NavLink::make('Products')
                        ->href($this->generateUrl('app_catalog_products'))
                        ->routes(['app_catalog_products', 'app_catalog_products_edit']),
                ]),
        ]),
    ])
    ->user(AdminUser::create()->name('Admin')->logoutHref($this->generateUrl('app_logout')))
    ->mainTabLabel('Products')
    ->tabCount(5);

$table = TanstackDataTable::create('catalog-products')
    // …
    ->opensTab()
    ->columns([
        // Special workspace-tab column (pass WorkspaceTabButton directly):
        WorkspaceTabButton::create()
            ->url(str_replace('/0/', '/{id}/', $this->generateUrl('app_catalog_products_edit', ['id' => 0]))),
        // General-purpose tab column (any buttons), if needed:
        // Column::make('tab')->buttons([WorkspaceTabButton::create()->url('…'), …]),
        // …
    ]);

return $this->render('products.html.twig', [
    'layout' => $layout->toArray(),
    'table' => $table->toArray(),
]);
```

```twig
<twig:tstdt:AdminLayout :config="layout">
    <twig:tstdt:TanstackDataTable :config="table" />
</twig:tstdt:AdminLayout>
```

Each tab button carries its destination URL (`data-url`). That URL is the unique tab id.

Workspace **command palette** (header icon or `Ctrl/⌘ K`) is configured in PHP like `AdminLayout`:

```php
use SymfonyTanstackTableShadcnUiToolkit\Layout\Command;
use SymfonyTanstackTableShadcnUiToolkit\Layout\CommandPalette;

$layout = AdminLayout::create()
    ->commandPalette(
        CommandPalette::create()
            ->keyboardShortcut('k')
            ->commands([
                Command::goToMainTab(),
                Command::refreshMainTable(),
                Command::closeAllTabs(), // confirms before running
                Command::openSidebar(),
                Command::closeSidebar(),
                Command::goToTabSlots(),   // expands to Go to tab 1…N
                Command::closeTabSlots(), // expands to Close tab 1…N
                Command::showTabTitles(), // shown when labels are URLs
                Command::showTabUrls(),   // shown when labels are titles
                Command::make('import')
                    ->label('Import CSV')
                    ->icon('lucide:download')
                    ->group('Actions')
                    ->confirm('Import CSV?', 'Existing rows may be updated.', 'Import')
                    ->event('catalog:import'),
            ])
    );
// ->withoutCommandPalette() to hide
// Any command: ->confirm('Title?', 'Description.', 'Confirm label') or ->confirm(false)
```

Account menu **language selector** (English / Français / Deutsch / Italiano) via `AdminUser`:

```php
AdminUser::create()
    ->locale($request->getLocale())
    ->languages([
        ['code' => 'en', 'label' => 'English', 'href' => $this->generateUrl('app_locale_switch', ['_locale' => 'en'])],
        ['code' => 'fr', 'label' => 'Français', 'href' => $this->generateUrl('app_locale_switch', ['_locale' => 'fr'])],
        ['code' => 'de', 'label' => 'Deutsch', 'href' => $this->generateUrl('app_locale_switch', ['_locale' => 'de'])],
        ['code' => 'it', 'label' => 'Italiano', 'href' => $this->generateUrl('app_locale_switch', ['_locale' => 'it'])],
    ]);
// ->withoutLanguages() to hide
```

AdminLayout UI copy uses the `tstdt` translation domain (English message ids). Catalogs ship in `translations/tstdt.{fr,de,it}.yaml`.

```js
document.dispatchEvent(new CustomEvent('workspace-tabs:activate', { detail: { slotId: 'main' } }));
document.dispatchEvent(new CustomEvent('workspace-tabs:activate', { detail: { slotIndex: 2 } }));
document.dispatchEvent(new CustomEvent('workspace-tabs:close-slot', { detail: { slotIndex: 2 } }));
document.dispatchEvent(new CustomEvent('workspace-tabs:close-all'));

document.dispatchEvent(new CustomEvent('workspace-tabs:open', {
  detail: { url: '/catalog/products/42/edit', label: 'Widget' },
}));
document.dispatchEvent(new CustomEvent('workspace-tabs:focus', { detail: { url: '/catalog/products/42/edit' } }));
document.dispatchEvent(new CustomEvent('workspace-tabs:close', { detail: { url: '/catalog/products/42/edit' } }));
```

### 5. Configure the table in the controller

```php
use SymfonyTanstackTableShadcnUiToolkit\Table\AddButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\BulkAction;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnBadge;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\DeleteButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\HeaderAction;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableColors;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

$table = TanstackDataTable::create('catalog-products')
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
        Column::make('edit')->buttons([ColumnButton::edit()]),
        Column::make('sku')->label('SKU')->field('p.sku')->class('font-mono text-xs'),
        Column::make('price')->label('Price')->field('p.price')->currency(),
        Column::make('status')->label('Status')->badge([
            ColumnBadge::make('active')->variant('default')->label('Active'),
            ColumnBadge::make('inactive')->variant('secondary')->label('Inactive'),
        ]),
        // Confirm dialog, then POST to url with row id (+ optional CSRF):
        DeleteButton::create()
            ->url(str_replace('/0/', '/{id}/', $this->generateUrl('app_catalog_products_delete', ['id' => 0])))
            ->confirm('Delete “{name}”?')
            ->csrfToken($csrfTokenManager->getToken('delete')->getValue()),
    ])
    ->headerActions([
        HeaderAction::downloadCsv(),
        // Large dialog that loads the create form URL:
        AddButton::create()
            ->url($this->generateUrl('app_catalog_products_new'))
            ->title('Add product'),
        // Custom id + DOM event name (dispatched by tanstack_table_controller):
        HeaderAction::make('import')
            ->label('Import')
            ->icon('lucide:upload')
            ->variant('outline')
            ->event('catalog:import'),
    ])
    ->bulkActions([
        BulkAction::delete()
            ->url($this->generateUrl('app_catalog_products_bulk_delete'))
            ->csrfToken($csrfTokenManager->getToken('bulk_delete')->getValue()),
        BulkAction::freeze()
            ->url($this->generateUrl('app_catalog_products_bulk_freeze'))
            ->csrfToken($csrfTokenManager->getToken('bulk_freeze')->getValue()),
    ])

return $this->render('catalog/products.html.twig', [
    'table' => $table->toArray(),
]);
```

Header action clicks run `tstdt--tanstack-table#headerAction`, which dispatches a bubbling `CustomEvent`.
Default event name is `tanstack-table:{id}` (override with `->event(...)`). Listen in JS:

```js
document.addEventListener('tanstack-table:download-csv', (e) => {
  // e.detail: { action, button, rows, config, table }
});
document.addEventListener('catalog:import', (e) => { /* ... */ });
```

Row action buttons (`Column::buttons()` / `ColumnButton`) dispatch `tanstack-table:row-{id}` (or a custom `->event(...)`) with the row. `DeleteButton` is special: it shows a confirm dialog, then POSTs to `url()` with the row `id` (and optional CSRF) — no JS listener required.

```js
document.addEventListener('tanstack-table:row-edit', (e) => {
  // e.detail: { action, button, row, rowId, config, table }
});
```

Bulk actions are empty by default — configure them in the controller with `->bulkActions([...])` (target `url()` + `csrfToken()`). Non-empty enables row checkboxes and a “Bulk actions” select. The play button dispatches `tanstack-table:bulk-{id}` (or a custom `->event(...)`) with the selected rows plus url / CSRF:

```js
document.addEventListener('tanstack-table:bulk-delete', (e) => {
  // e.detail.rows = full selected row objects (not just ids)
  // e.detail: { action, event, tableId, url, csrfToken, csrfField, rows, rowIds, config, table }
});
```

Collapsible **Advanced filters** (Filters header action) uses per-column typed rules (AND-combined). Configure in PHP:

```php
Column::make('name')->filterString();
Column::make('price')->currency()->filterNumber();          // < > = between
Column::make('createdAt')->filterDate();                    // calendar
Column::make('createdAt')->filterDateRange();               // from / to calendars
Column::make('status')->filterChoices([                     // select
    'active' => 'Active',
    'inactive' => 'Inactive',
]);
Column::make('tags')->filterChoices($tags, multiple: true); // multi-select
Column::make('notes')->withoutFilter();                     // hide from panel
Column::make('createdAt')->defaultHidden();                 // hidden until enabled in Columns menu
```

Formats auto-infer when unset (`number`/`currency` → number, `badge` labels → choice, otherwise string). Disable the panel with `->advancedFilters(false)`.

JSON endpoint (query params `page`, `pageSize`, `fields[]` for visible columns, `filters[i][field|operator|value|valueTo]`):

```php
use SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;

public function __construct(
    private readonly DatatableQueryApplier $queryApplier,
) {}

public function productsJson(Request $request): JsonResponse
{
    $table = $this->productsTable();
    $query = TableQuery::fromRequest($request, $table->getPageSize());
    [$rows, $total] = $this->productRowsPage($query);

    // Projects rows to visible columns from fields[] (always keeps id).
    return $table->json($rows, $total, query: $query);
    // { "data": [...], "total": 60, "page": 1, "pageSize": 10, "pageCount": 6 }
}

// Prefer table-built context (or QueryContext::from([...]) for hand-rolled bags):
$this->queryApplier->applyFilters(
    $qb,
    $query->filters,
    $table->queryContext(Product::class),
);
```

`DatatableQueryApplier` dispatches join/select/filter/transform events so custom subscribers can handle computed fields and relation joins. Prefer `fetchScalarRows($qb, $query, $context, 'e.id')` for JSON pages. Prefer it over the lower-level `TableQuery::applyDoctrineFilter()`.

**Events & subscribers (detailed)** — see **[docs/EVENTS.md](docs/EVENTS.md)** (index) for Mermaid walkthroughs of the pipeline (`queryContext` → `fetchPage` → join/select/filter/sort/transform), priorities, and links to each section. For `#[AsTanStackDatatableField]` handlers, see **[docs/CUSTOM_FIELD_SUBSCRIBERS.md](docs/CUSTOM_FIELD_SUBSCRIBERS.md)**.

```php
Column::make('category')->field('c.name')->join('p.category');

[$rows, $total] = $this->queryApplier->fetchPage(
    fn () => $em->createQueryBuilder()->from(Product::class, 'p'),
    $query,
    $table->queryContext(Product::class, extraAllowed: ['status'], controller: self::class, action: __FUNCTION__),
    'p.id',
);
```

**Profiler** — with `symfony/web-profiler-bundle`, a **Datatable** panel (`tstdt.datatable`) shows `TableQuery` snapshots, applier calls, and each join/select/filter/transform event.

**Extending fields** — subclass `AbstractDatatableFieldSubscriber` with `#[AsTanStackDatatableField]` (see [docs/CUSTOM_FIELD_SUBSCRIBERS.md](docs/CUSTOM_FIELD_SUBSCRIBERS.md) and demos: status filter/select, `ProductCategoryJoinSubscriber`, `OrderCustomerSelectSubscriber`). Events are not `final`; override `DatatableQueryApplier::create*Event()` for richer payloads and keep dispatching on bundle `::NAME` constants.

Twig (config is also exposed as `data-config` for the Stimulus controller):

```twig
<twig:tstdt:TanstackDataTable :config="table" />
```
