<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

/**
 * Dispatched once per field when {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier::applySelects()} runs.
 *
 * Not final — subclass to attach app-specific payload, then override
 * {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier::createSelectEvent()}.
 * Always dispatch on {@see NAME} so bundle + app subscribers on this channel keep working.
 *
 * Listeners may call {@see stopPropagation()} after handling a custom/computed field
 * so the default select subscriber does not also add {@code alias.field}.
 *
 * @phpstan-type Context array<string, mixed>
 */
class ApplySelectEvent extends AbstractQueryEvent implements ApplySelectEventInterface
{
    /**
     * Bundle-scoped event name (stable channel FQCN).
     * Subclasses should keep dispatching on this constant, not {@code static::class}.
     */
    public const NAME = self::class;

    /**
     * Resolve the DQL expression for this field from {@code context['field_map']},
     * or {@code rootAlias.field} when no map is present.
     *
     * When a map is set and the field is absent, returns null so custom subscribers
     * (or nothing) handle the select — same fail-closed behaviour as filters.
     */
    public function resolveExpression(): ?string
    {
        return FieldExpression::resolve($this->getField(), $this->getRootAlias(), $this->getContext());
    }
}
