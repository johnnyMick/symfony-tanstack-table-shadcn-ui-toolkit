# End-to-end sequence (`fetchScalarRows` / `fetchPage`)

Sequence of joins, selects, filters, sorts, and transform inside
`DatatableQueryApplier`. See also [Events & Subscribers](EVENTS.md) for the
doc index.

---

```mermaid
sequenceDiagram
  participant C as Controller
  participant A as DatatableQueryApplier
  participant D as EventDispatcher
  participant S as Subscribers
  participant QB as QueryBuilder

  C->>A: fetchPage(qbFactory, query, context, "p.id")
  A->>QB: COUNT clone + applyFilters (+ joins)
  A->>QB: page QB + applySorts (or idExpression DESC) + fetchScalarRows

  loop each field in fields[] (except id)
    opt context.joins[field] + field allowed
      A->>D: dispatch ApplyJoinEvent (per hop, deduped by alias)
      D->>S: whitelist → custom join() → DefaultJoin
      S->>QB: leftJoin / innerJoin (+ WITH)
    end
    A->>D: dispatch ApplySelectEvent
    D->>S: whitelist → custom → DefaultSelect
  end

  loop each filters[] / sorts[]
    Note over A,S: same join-then-event pattern
  end

  A->>QB: getArrayResult()
  A->>D: TransformRowsEvent (selected_fields hint)
  A-->>C: [rows, total]
```
