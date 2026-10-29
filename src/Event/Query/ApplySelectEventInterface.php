<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

/**
 * Contract for select-clause application events.
 *
 * Apps may subclass {@see ApplySelectEvent} or implement this interface for a custom event
 * dispatched via an extended {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier}.
 */
interface ApplySelectEventInterface extends QueryEventInterface
{
    /**
     * Resolve the DQL expression for this field from {@code context['field_map']} or root alias.
     */
    public function resolveExpression(): ?string;
}
