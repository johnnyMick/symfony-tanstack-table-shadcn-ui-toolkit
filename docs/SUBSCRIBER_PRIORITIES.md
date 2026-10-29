# Subscriber priorities

Order of whitelist → app `#[AsTanStackDatatableField]` → defaults for join / select /
filter / sort / transform. See also [Events & Subscribers](EVENTS.md) for the
doc index and [Custom field subscribers](CUSTOM_FIELD_SUBSCRIBERS.md) for app
handlers.

---

```mermaid
flowchart LR
  subgraph SelectFilterSort["Select / Filter / Sort / Join"]
    direction TB
    W["FieldWhitelistSubscriber<br/>priority 100"]
    C["App #[AsTanStackDatatableField]<br/>priority 10 typical"]
    Def["Defaults<br/>priority -100"]
    W --> C --> Def
  end

  subgraph Transform["TransformRows"]
    FT["Field transform()<br/>priority 10"]
    DT["DateTimeFormatSubscriber<br/>-100"]
    FT --> DT
  end
```

| Subscriber | Priority | Job |
|------------|----------|-----|
| `FieldWhitelistSubscriber` | `100` | Gate join/select/filter/sort |
| App `AbstractDatatableFieldSubscriber` | usually `10` | `join` / `select` / `filter` / `sort` / `transform` |
| `DefaultJoinSubscriber` | `-100` | LEFT/INNER JOIN via `LeftJoinApplicator` |
| `DefaultSelectSubscriber` | `-100` | `addSelect(expression AS field)` |
| `DefaultFilterSubscriber` | `-100` | Common operators |
| `DefaultSortSubscriber` | `-100` | `addOrderBy` |
| `DateTimeFormatSubscriber` | `-100` | Format dates from `datetime_formats` |

`ComputedFieldSubscriber` is an **example only** (excluded from DI) — copy into
the app.
