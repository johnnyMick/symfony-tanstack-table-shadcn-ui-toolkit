# Why join / select events must be stopped

Why custom handlers call `handleAndStop()` (or `markHandled()` +
`stopPropagation()`) on `ApplyJoinEvent` / `ApplySelectEvent` (and the same
pattern for filter / sort). See also [Events & Subscribers](EVENTS.md) and
[Subscriber priorities](SUBSCRIBER_PRIORITIES.md).

---

## The problem: one field, several listeners

For each selected (or filtered / sorted) field, `DatatableQueryApplier`
dispatches **one** event. Several subscribers listen on that same channel, in
priority order:

```text
FieldWhitelistSubscriber   (100)  — may block unknown fields
App #[AsTanStackDatatableField]    (~10)  — custom join / select / …
DefaultJoin / DefaultSelect (-100) — generic LEFT JOIN / addSelect
```

Defaults are **fallbacks**. They assume: “if nobody claimed this field, apply
the usual `field_map` expression / `joins` hop.”

If a custom handler mutates the `QueryBuilder` but does **not** mark the event
handled, the default still runs afterward. You then get **double work** on the
same field — often broken DQL or wrong SQL.

## What each API does

| API | Effect |
|-----|--------|
| `markHandled()` | Sets a flag. Defaults (`priority -100`) check `isHandled()` and skip. Later listeners at higher/equal priority can still run unless propagation is stopped. |
| `stopPropagation()` | Symfony: no further listeners on this dispatch. |
| `handleAndStop()` | Both — preferred when your listener fully owns the field. |

Defaults (`DefaultSelectSubscriber`, `DefaultJoinSubscriber` /
`LeftJoinApplicator`, filters, sorts) bail out when:

```php
if ($event->isPropagationStopped() || $event->isHandled()) {
    return;
}
```

So either flag alone can suppress the default, but **`handleAndStop()`** is the
safe habit: you mark ownership **and** you do not invite other mid-priority
listeners to pile on.

## Select: why stopping matters

Example: Product `status` is not `p.status` — it is derived from `p.active`, or
a `CONCAT`, or a CASE expression in an app subscriber.

```php
protected function select(ApplySelectEventInterface $event): void
{
    $event->getQueryBuilder()->addSelect(
        $event->getRootAlias().'.active AS status'
    );
    $event->handleAndStop();
}
```

Without `handleAndStop()`:

1. Your listener adds `p.active AS status`.
2. `DefaultSelectSubscriber` still sees an unhandled field, resolves
   `field_map['status']` (or `{root}.status`), and adds **another**
   `… AS status`.

Doctrine then has two selects aliased to the same name, or a missing map entry
and a bad `{alias}.status` path. The custom meaning of `status` is lost or the
query fails.

**Stopping says:** this field is done; do not apply the generic
`expression AS field` fallback.

The same applies to computed columns (`ComputedFieldSubscriber` pattern):
custom `addSelect` + `handleAndStop()` so the default never rewrites them.

## Join: why stopping matters

`ApplyJoinEvent` is one hop (path + alias + type + optional WITH). App code
might need an `INNER JOIN`, a WITH clause, or a non-standard path that
`LeftJoinApplicator` should not also apply.

```php
protected function join(ApplyJoinEventInterface $event): void
{
    $event->getQueryBuilder()->innerJoin(
        $event->getJoinPath(),
        $event->getJoinAlias(),
        'WITH',
        'c.active = true',
    );
    $event->handleAndStop();
}
```

Without stopping:

1. Your listener already joined `p.category` as `c`.
2. `DefaultJoinSubscriber` / `LeftJoinApplicator` may try again (or mark
   `skipped_alias_present` after a redundant check).

Worse: two different paths for the same alias, or a second LEFT JOIN when you
intentionally used INNER. **Stopping says:** this hop is applied; skip the
generic left/inner applicator.

When the default *does* apply the join, it calls `handleAndStop()` itself so
later listeners do not re-join.

## Whitelist is the other “stop”

`FieldWhitelistSubscriber` (priority `100`) calls `markHandled()` +
`stopPropagation()` for fields **not** in `allowed_fields`. That is the same
mechanism used defensively: block the field before any custom or default
listener touches the QB.

## When not to stop early

- **Transform** is different: many listeners may map different columns on one
  `TransformRowsEvent`. Field transforms usually do **not** stop the whole
  event; they only change their column.
- If you only **enrich** an event (logging, profiler hints) and leave the real
  join/select to the default, do **not** call `handleAndStop()` — leave the
  event unhandled so `-100` defaults still run.

## Mental model

```text
ApplySelectEvent(field=status)
  → custom select() adds DQL
  → handleAndStop()
  → DefaultSelect never runs
  → one AS status, correct expression

ApplyJoinEvent(path=p.category, alias=c)
  → custom join() or DefaultJoin applies once
  → handleAndStop()
  → no second JOIN for the same hop
```

**Rule of thumb:** if your listener fully defines how this field’s join or
select (or filter / sort) should look, end with `handleAndStop()`. If you only
observe, leave the event open for the defaults.
