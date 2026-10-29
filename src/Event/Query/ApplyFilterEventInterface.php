<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

/**
 * Contract for filter/WHERE application events.
 *
 * Apps may subclass {@see ApplyFilterEvent} or implement this interface for a custom event
 * dispatched via an extended {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier}.
 */
interface ApplyFilterEventInterface extends QueryEventInterface
{
    public function getOperator(): string;

    public function getValue(): mixed;

    public function getValueTo(): mixed;

    /**
     * Resolve the DQL expression for this field from {@code context['field_map']},
     * or fall back to {@code rootAlias.field} when no map is provided.
     */
    public function resolveExpression(): ?string;
}
