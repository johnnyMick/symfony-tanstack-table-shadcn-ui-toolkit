<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

/**
 * Label on a field subscriber: which column it owns, and how narrowly it targets a request.
 *
 * Use with {@see \SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\AbstractDatatableFieldSubscriber}.
 *
 * Targeting precision (higher levels need the lower ones set too):
 * 1. Maximum — entity + controller + action
 * 2. Medium  — entity + controller
 * 3. Low     — entity only
 * 4. None    — field name only (runs for any request context)
 *
 * Optional {@see $join} declares a relation hop without {@code Column::join()} (merged into query context).
 *
 * <code>
 * #[AsTanStackDatatableField(
 *     'category',
 *     entity: Product::class,
 *     controller: CatalogController::class,
 *     action: 'productsJson',
 *     join: 'p.category',
 *     joinAlias: 'c',
 * )]
 * </code>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class AsTanStackDatatableField
{
    public const PRECISION_NONE = 0;
    public const PRECISION_ENTITY = 1;
    public const PRECISION_CONTROLLER = 2;
    public const PRECISION_ACTION = 3;

    /**
     * @param class-string|null $entity        Matched against context {@code entity}
     * @param class-string|null $controller    Matched against context {@code controller} (needs entity)
     * @param string|null       $action        Matched against context {@code action} (needs controller + entity)
     * @param string|null       $join          Association path, e.g. {@code p.category}
     * @param string|null       $joinAlias     Join alias; inferred from field_map when omitted
     * @param string            $joinType      {@see ApplyJoinEventInterface::TYPE_LEFT} or {@see ApplyJoinEventInterface::TYPE_INNER}
     * @param string|null       $joinCondition Optional WITH clause (no leading WITH)
     */
    public function __construct(
        public readonly string $field,
        public readonly ?string $entity = null,
        public readonly ?string $controller = null,
        public readonly ?string $action = null,
        public readonly ?string $join = null,
        public readonly ?string $joinAlias = null,
        public readonly string $joinType = ApplyJoinEventInterface::TYPE_LEFT,
        public readonly ?string $joinCondition = null,
    ) {
        $this->rejectBlankOptional('entity', $this->entity);
        $this->rejectBlankOptional('controller', $this->controller);
        $this->rejectBlankOptional('action', $this->action);
        $this->assertPrecisionLadder();
    }

    /**
     * How narrowly this attribute targets a request (see class docblock).
     */
    public function precision(): int
    {
        if (null !== $this->action) {
            return self::PRECISION_ACTION;
        }

        if (null !== $this->controller) {
            return self::PRECISION_CONTROLLER;
        }

        if (null !== $this->entity) {
            return self::PRECISION_ENTITY;
        }

        return self::PRECISION_NONE;
    }

    /**
     * Does the query context satisfy this attribute’s precision level?
     */
    public function matchesContext(?string $entity, ?string $controller, ?string $action): bool
    {
        $level = $this->precision();

        if (self::PRECISION_ACTION === $level) {
            return $this->entity === $entity
                && $this->controller === $controller
                && $this->action === $action;
        }

        if (self::PRECISION_CONTROLLER === $level) {
            // Action on the request is ignored at medium precision.
            return $this->entity === $entity
                && $this->controller === $controller;
        }

        if (self::PRECISION_ENTITY === $level) {
            // Controller / action on the request are ignored at low precision.
            return $this->entity === $entity;
        }

        // Field-only: any context is fine.
        return true;
    }

    /**
     * Join hop for {@see \SymfonyTanstackTableShadcnUiToolkit\Query\DatatableFieldJoinEnricher}, or null.
     *
     * @return array{path: string, alias?: string, type: string, condition?: string}|null
     */
    public function toJoinHop(): ?array
    {
        if (null === $this->join || '' === $this->join) {
            return null;
        }

        $hop = [
            'path' => $this->join,
            'type' => $this->joinType,
        ];

        if (null !== $this->joinAlias && '' !== $this->joinAlias) {
            $hop['alias'] = $this->joinAlias;
        }

        if (null !== $this->joinCondition && '' !== $this->joinCondition) {
            $hop['condition'] = $this->joinCondition;
        }

        return $hop;
    }

    /**
     * action ⇒ needs controller + entity; controller ⇒ needs entity.
     */
    private function assertPrecisionLadder(): void
    {
        $hasEntity = null !== $this->entity;
        $hasController = null !== $this->controller;
        $hasAction = null !== $this->action;

        if ($hasAction && (!$hasController || !$hasEntity)) {
            throw new \InvalidArgumentException(
                'AsTanStackDatatableField: action requires controller and entity (maximum precision).',
            );
        }

        if ($hasController && !$hasEntity) {
            throw new \InvalidArgumentException(
                'AsTanStackDatatableField: controller requires entity (medium precision).',
            );
        }
    }

    private function rejectBlankOptional(string $name, ?string $value): void
    {
        if (null === $value) {
            return;
        }

        if ('' === trim($value)) {
            throw new \InvalidArgumentException(\sprintf(
                'AsTanStackDatatableField: %s must be null or a non-empty string.',
                $name,
            ));
        }
    }
}
