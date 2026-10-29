# Anti-patterns

Common pitfalls when wiring `TanstackDataTable` to Doctrine. See also
[Events & Subscribers](EVENTS.md) for the doc index.

---

| Avoid | Prefer |
|-------|--------|
| Eager `$qb->leftJoin(...)` on every COUNT/page QB | `Column::join()` + lazy `ApplyJoinEvent` |
| Hand-rolling `field_map` / `joins` / `allowed_fields` in three places | `$table->queryContext(...)` |
| Only `stopPropagation()` and hoping defaults skip | `handleAndStop()` / `markHandled()` — see [Why handleAndStop](HANDLE_AND_STOP.md) |
| Omitting whitelist with a partial `field_map` | Fail-closed: map keys become `allowed_fields` automatically |
| Relying on global `ComputedFieldSubscriber` | Copy the pattern into your app |
