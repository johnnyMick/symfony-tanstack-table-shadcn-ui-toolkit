# TanStack DataTable — Events & Subscribers

How `TanstackDataTable` (frontend config) connects to Doctrine via
`DatatableQueryApplier` and Symfony’s event/subscriber model.

| Doc | Contents |
|-----|----------|
| [Big picture](BIG_PICTURE.md) | Architecture Mermaid, piece roles, preferred controller path |
| [Fetch sequence](FETCH_SEQUENCE.md) | `fetchPage` / `fetchScalarRows` end-to-end sequence |
| [Query events](QUERY_EVENTS.md) | Event table, `handleAndStop`, join outcomes, context keys |
| [Why handleAndStop](HANDLE_AND_STOP.md) | Why join / select (and filter / sort) handlers must stop defaults |
| [Joins](JOINS.md) | Per-field join pipeline + controller-to-Doctrine walkthrough |
| [Subscriber priorities](SUBSCRIBER_PRIORITIES.md) | Whitelist → app → defaults |
| [Custom field subscribers](CUSTOM_FIELD_SUBSCRIBERS.md) | `#[AsTanStackDatatableField]` routing and Sales / Product demos |
| [Anti-patterns](ANTI_PATTERNS.md) | What to avoid |

---

## Profiler

Panel **`tstdt.datatable`**: TableQuery (incl. sorts), applier calls, per-event
join outcomes (`applied` / `skipped_*`), handled + stopped flags, transform samples.

---

## Related code

| Path | Notes |
|------|--------|
| `src/Query/QueryContext.php` | Typed context |
| `src/Query/LeftJoinApplicator.php` | LEFT/INNER + WITH |
| `src/Service/DatatableQueryApplier.php` | `fetchPage`, joins, sorts |
| `src/Table/Column.php` | `join()` / `joins()` |
| `src/Table/TanstackDataTable.php` | `joinMap()` / `queryContext()` |
| `src/Event/Query/*` | Events + `markHandled` |
| `src/EventSubscriber/Query/*` | Defaults + abstract base |
| `demo/src/Controller/*` | Catalog / Sales examples |
