<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

/**
 * Contract for per-field relation join events.
 *
 * Dispatched when a selected/filtered/sorted field declares a relation join in
 * {@code context['joins'][field]} before the matching select/filter/sort runs.
 */
interface ApplyJoinEventInterface extends QueryEventInterface
{
    public const TYPE_LEFT = 'left';
    public const TYPE_INNER = 'inner';

    /**
     * DQL join alias (e.g. {@code c} for {@code LEFT JOIN p.category c}).
     */
    public function getJoinAlias(): string;

    /**
     * Association path (e.g. {@code p.category}).
     */
    public function getJoinPath(): string;

    /**
     * Join type: {@see self::TYPE_LEFT} or {@see self::TYPE_INNER}.
     */
    public function getJoinType(): string;

    /**
     * Optional WITH / ON condition DQL fragment (no leading WITH keyword).
     */
    public function getJoinCondition(): ?string;

    /**
     * Outcome after default applicator: {@code applied}, {@code skipped_alias_present},
     * {@code skipped_unsafe}, or {@code null} before handling.
     */
    public function getJoinOutcome(): ?string;

    public function setJoinOutcome(string $outcome): void;
}
