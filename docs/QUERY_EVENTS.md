# Query events

Stable channel names, handled vs stopPropagation, join outcomes, and context
keys. See also [Events & Subscribers](EVENTS.md) for the doc index.

---

Stable channel name: `EventClass::NAME` (= FQCN). Subclass for richer payloads;
keep dispatching on bundle `::NAME`.

| Event | When | Typical handler |
|-------|------|-----------------|
| `ApplyJoinEvent` | Before select/filter/sort if `joins[field]` set **and field allowed** | `DefaultJoinSubscriber` → `LeftJoinApplicator` |
| `ApplySelectEvent` | Once per selected field | Custom or `DefaultSelectSubscriber` |
| `ApplyFilterEvent` | Once per filter rule | Custom or `DefaultFilterSubscriber` |
| `ApplySortEvent` | Once per sort rule | Custom or `DefaultSortSubscriber` |
| `TransformRowsEvent` | Once after fetch | Field `transform()` + `DateTimeFormatSubscriber` |

## Handled vs stopPropagation

| API | Meaning |
|-----|---------|
| `markHandled()` | Fallback defaults (`priority -100`) skip this field |
| `stopPropagation()` | No further listeners run |
| `handleAndStop()` | Both — preferred in custom select/filter/join/sort |

Defaults check `isHandled()` **and** `isPropagationStopped()`.

Why custom join / select handlers must stop the event (double `addSelect` /
double JOIN): see **[Why handleAndStop](HANDLE_AND_STOP.md)**.

## Join outcomes (profiler)

| Outcome | Meaning |
|---------|---------|
| `applied` | JOIN added to the QB |
| `skipped_alias_present` | Alias already on the QB |
| `skipped_dedupe` | Same alias already joined earlier in this apply call |
| `skipped_unsafe` | Path/alias failed safety regex |

## Context keys

| Key | Purpose |
|-----|---------|
| `field_map` | Column id → DQL (`TanstackDataTable::fieldMap()`) |
| `allowed_fields` | Whitelist (auto-derived from `field_map` keys when omitted — fail-closed) |
| `entity` | Optional FQCN for `#[AsTanStackDatatableField(..., entity: …)]` |
| `controller` | Optional controller FQCN for attribute matching |
| `action` | Optional action / method name for attribute matching |
| `joins` | Field → path string, `{alias, path, type?, condition?}`, or **list** of hops |
| `datetime_formats` | Field → PHP date format |
| `extra_allowed_fields` | Merged into allowed list |
| `selected_fields` | Set by `fetchScalarRows` so transform can skip unused columns |
