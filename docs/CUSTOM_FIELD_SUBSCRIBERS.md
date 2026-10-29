# Custom field subscribers

How `#[AsTanStackDatatableField]` + `AbstractDatatableFieldSubscriber` route app handlers
for join / select / filter / sort / transform. See also
[Events & Subscribers](EVENTS.md) for the doc index and
[Subscriber priorities](SUBSCRIBER_PRIORITIES.md).

---

`#[AsTanStackDatatableField]` is the **routing label**. `AbstractDatatableFieldSubscriber`
reads it via reflection and only runs your hooks when the event matches.

## What the attribute means

```php
#[AsTanStackDatatableField('status', entity: Order::class)]
final class SalesStatusFilterSubscriber extends AbstractDatatableFieldSubscriber
```

| Argument | Role |
|----------|------|
| `'status'` | Only events whose field id is `status` |
| `entity: Order::class` | Low precision — any action on that entity |
| `controller: SalesController::class` | Medium — requires `entity`; any action on that controller |
| `action: 'ordersJson'` | Maximum — requires `controller` **and** `entity` |

Precision ladder (invalid combos throw at attribute construct time):

| Level | Required args | Context match |
|-------|---------------|---------------|
| Maximum | `entity` + `controller` + `action` | All three equal |
| Medium | `entity` + `controller` | Entity + controller equal (action ignored) |
| Low | `entity` | Entity equal (controller/action ignored) |
| None | field only | Any context |

That is why Product and Sales can both expose `status`, and why two Product JSON
actions can use different subscribers when `action` differs.

## How it gets called

1. The controller builds context with entity **and** request targeting:

   ```php
   $context = $table->queryContext(
       Order::class,
       controller: self::class,
       action: __FUNCTION__, // e.g. ordersJson
   );
   ```

   That puts `entity`, `controller`, and `action` on the `QueryContext` (events
   still see them in the array bag).

2. When the UI filters on `status`, `DatatableQueryApplier` dispatches an
   `ApplyFilterEvent` with field `status` and that context.

3. On each join/select/filter/sort event, `AbstractDatatableFieldSubscriber`:
   - returns early if the event is already handled or propagation was stopped
   - calls `matches($event)`
   - if match → calls your `filter()` / `select()` / …

4. `matches()` requires:
   - event field === attribute field (`'status'`)
   - attribute precision matches context (see table above)

If matching fails (wrong entity / controller / action for that precision), the
subscriber does nothing.

## What `filter()` typically does (Sales demo)

`SalesStatusFilterSubscriber::filter()` only runs after that match. It:

1. Normalizes the filter value (comma string or array → lowercased tokens)
2. Intersects with `Order::STATUSES` (whitelist — unknown tokens never hit DQL)
3. Empty allowed set → `WHERE 1 = 0` (no rows)
4. All statuses selected → treat as “no filter”
5. Otherwise → `eq` / `in` on `{rootAlias}.status`
6. Calls `handleAndStop()` so default filter subscribers do not also handle `status`

Priority `10` means it runs **after** the whitelist gate (`100`) and **before**
the defaults (`-100`).

## Mental model

```
UI filter status=paid
  → ApplyFilterEvent(field=status, context.entity=Order)
  → matches? field + entity OK
  → SalesStatusFilterSubscriber::filter()
  → WHERE o.status = :… + handleAndStop()
```

## Full Product example

```php
#[AsTanStackDatatableField(
    'status',
    entity: Product::class,
    controller: CatalogController::class,
    action: 'productsJson',
)]
final class ProductStatusFilterSubscriber extends AbstractDatatableFieldSubscriber
{
    protected static function priority(): int { return 10; }

    protected function select(ApplySelectEventInterface $event): void
    {
        $event->getQueryBuilder()->addSelect($event->getRootAlias().'.active AS status');
        $event->handleAndStop();
    }

    protected function filter(ApplyFilterEventInterface $event): void
    {
        // … WHERE …
        $event->handleAndStop();
    }

    protected function transform(TransformRowsEventInterface $event, string $field): void
    {
        $event->mapColumn($field, static fn (mixed $v): string =>
            filter_var($v, \FILTER_VALIDATE_BOOLEAN) ? 'active' : 'inactive'
        );
    }
}
```

Optional hooks: `join(ApplyJoinEventInterface)`, `sort(ApplySortEventInterface)`.

Transform skips fields not listed in `selected_fields` when that list is non-empty.

## Join example (Catalog `category`)

Declare the hop on the attribute (`join` / `joinAlias`) instead of
`Column::join()` when a custom `join()` handler owns the relation.
`DatatableFieldJoinEnricher` merges that into query context so `ApplyJoinEvent`
still fires. `ProductCategoryJoinSubscriber` then applies an INNER JOIN and
calls `handleAndStop()` (see [Why handleAndStop](HANDLE_AND_STOP.md)).

```php
// Column: ->field('c.name')  — no ->join()
#[AsTanStackDatatableField(
    'category',
    entity: Product::class,
    controller: CatalogController::class,
    action: 'productsJson',
    join: 'p.category',
    joinAlias: 'c',
)]
final class ProductCategoryJoinSubscriber extends AbstractDatatableFieldSubscriber
{
    protected function join(ApplyJoinEventInterface $event): void
    {
        $event->getQueryBuilder()->innerJoin(
            $event->getJoinPath(),
            $event->getJoinAlias(),
        );
        $event->setJoinOutcome(ApplyJoinEvent::OUTCOME_APPLIED);
        $event->handleAndStop();
    }
}
```

Use `Column::join()` / `joins()` when the default LEFT/INNER applicator is enough
(no custom `join()` listener). Column hops override attribute hops for the same field.

## Select example (Sales `customer`)

Join stays on the column (`->join('o.customer', 'c')`). Select is owned by
`OrderCustomerSelectSubscriber` so `DefaultSelectSubscriber` does not re-add
`field_map['customer']`. Filters still use the CONCAT from `Column::field()`.

```php
#[AsTanStackDatatableField(
    'customer',
    entity: Order::class,
    controller: SalesController::class,
    action: 'ordersJson',
)]
final class OrderCustomerSelectSubscriber extends AbstractDatatableFieldSubscriber
{
    protected function select(ApplySelectEventInterface $event): void
    {
        $event->getQueryBuilder()->addSelect(
            "CONCAT(c.firstName, ' ', c.lastName) AS customer",
        );
        $event->handleAndStop();
    }
}
```

## Demo copies

- `demo/src/EventSubscriber/Query/SalesStatusFilterSubscriber.php`
- `demo/src/EventSubscriber/Query/ProductStatusFilterSubscriber.php`
- `demo/src/EventSubscriber/Query/ProductCategoryJoinSubscriber.php`
- `demo/src/EventSubscriber/Query/OrderCustomerSelectSubscriber.php`
