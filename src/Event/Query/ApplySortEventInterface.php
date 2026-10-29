<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

/**
 * Contract for per-field ORDER BY events.
 */
interface ApplySortEventInterface extends QueryEventInterface
{
    public const DIRECTION_ASC = 'asc';
    public const DIRECTION_DESC = 'desc';

    public function getDirection(): string;

    public function resolveExpression(): ?string;
}
