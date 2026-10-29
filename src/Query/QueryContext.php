<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Query;

use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

/**
 * Typed context bag for {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier}.
 *
 * Prefer {@see fromTable()} / fluent builders over hand-rolled arrays. Events still receive
 * the array form via {@see toArray()}.
 *
 * @phpstan-type JoinSpec array{alias?: string, path: string, type?: string, condition?: string|null}
 * @phpstan-type JoinConfig string|JoinSpec|list<string|JoinSpec>
 */
final class QueryContext
{
    /** @var array<string, string> */
    private array $fieldMap = [];

    /** @var list<string>|null */
    private ?array $allowedFields = null;

    /** @var list<string> */
    private array $extraAllowedFields = [];

    private ?string $entity = null;

    private ?string $controller = null;

    private ?string $action = null;

    /** @var array<string, JoinConfig> */
    private array $joins = [];

    /** @var array<string, string> */
    private array $datetimeFormats = [];

    /** @var list<string> */
    private array $selectedFields = [];

    /**
     * Build context from a table's {@see TanstackDataTable::fieldMap()} / {@see TanstackDataTable::joinMap()}.
     *
     * @param list<string>              $extraAllowed
     * @param array<string, string>     $datetimeFormats
     * @param array<string, JoinConfig> $joins extra/override joins (merged over table joinMap)
     * @param class-string|null         $controller Controller FQCN for {@see \SymfonyTanstackTableShadcnUiToolkit\Event\Query\AsTanStackDatatableField} matching
     * @param string|null               $action     Action / method name for attribute matching
     */
    public static function fromTable(
        TanstackDataTable $table,
        ?string $entity = null,
        array $extraAllowed = [],
        array $datetimeFormats = [],
        array $joins = [],
        ?string $controller = null,
        ?string $action = null,
    ): self {
        $ctx = new self();
        $fieldMap = $table->fieldMap();
        $ctx->fieldMap = $fieldMap;
        $ctx->joins = array_replace($table->joinMap(), $joins);
        $ctx->extraAllowedFields = array_values(array_filter(
            $extraAllowed,
            static fn (mixed $f): bool => \is_string($f) && '' !== $f,
        ));
        $ctx->allowedFields = array_values(array_unique([
            ...array_keys($fieldMap),
            ...$ctx->extraAllowedFields,
        ]));
        $ctx->entity = $entity;
        $ctx->controller = self::nullableString($controller);
        $ctx->action = self::nullableString($action);
        $ctx->datetimeFormats = $datetimeFormats;

        return $ctx;
    }

    /**
     * Hydrate from a legacy/context array (also accepts another {@see QueryContext}).
     *
     * @param array<string, mixed>|self $context
     */
    public static function from(array|self $context): self
    {
        if ($context instanceof self) {
            return clone $context;
        }

        $ctx = new self();
        $ctx->fieldMap = self::stringMap($context['field_map'] ?? null);
        $ctx->extraAllowedFields = self::stringList($context['extra_allowed_fields'] ?? null);
        $ctx->datetimeFormats = self::stringMap($context['datetime_formats'] ?? null);
        $ctx->selectedFields = self::stringList($context['selected_fields'] ?? null);

        if (\array_key_exists('allowed_fields', $context)) {
            $ctx->allowedFields = \is_array($context['allowed_fields'])
                ? self::stringList($context['allowed_fields'])
                : [];
        }

        $entity = $context['entity'] ?? null;
        if (\is_string($entity) && '' !== $entity) {
            $ctx->entity = $entity;
        }

        $ctx->controller = self::nullableString($context['controller'] ?? null);
        $ctx->action = self::nullableString($context['action'] ?? null);

        $joins = $context['joins'] ?? null;
        if (\is_array($joins)) {
            /** @var array<string, JoinConfig> $joins */
            $ctx->joins = $joins;
        }

        return $ctx;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' !== $trimmed ? $trimmed : null;
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (\is_string($key) && \is_string($item) && '' !== $item) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $item) {
            if (!\is_string($item)) {
                continue;
            }
            $trimmed = trim($item);
            if ('' !== $trimmed) {
                $list[] = $trimmed;
            }
        }

        return $list;
    }

    /**
     * @param array<string, string> $fieldMap
     */
    public function withFieldMap(array $fieldMap): self
    {
        $clone = clone $this;
        $clone->fieldMap = $fieldMap;

        return $clone;
    }

    /**
     * @param list<string>|null $allowedFields null = omit key (open mode when no field_map fail-closed)
     */
    public function withAllowedFields(?array $allowedFields): self
    {
        $clone = clone $this;
        $clone->allowedFields = $allowedFields;

        return $clone;
    }

    /**
     * @param list<string> $extraAllowedFields
     */
    public function withExtraAllowedFields(array $extraAllowedFields): self
    {
        $clone = clone $this;
        $clone->extraAllowedFields = array_values($extraAllowedFields);

        return $clone;
    }

    public function withEntity(?string $entity): self
    {
        $clone = clone $this;
        $clone->entity = $entity;

        return $clone;
    }

    public function withController(?string $controller): self
    {
        $clone = clone $this;
        $clone->controller = self::nullableString($controller);

        return $clone;
    }

    public function withAction(?string $action): self
    {
        $clone = clone $this;
        $clone->action = self::nullableString($action);

        return $clone;
    }

    /**
     * @param array<string, JoinConfig> $joins
     */
    public function withJoins(array $joins): self
    {
        $clone = clone $this;
        $clone->joins = $joins;

        return $clone;
    }

    /**
     * @param array<string, string> $datetimeFormats
     */
    public function withDatetimeFormats(array $datetimeFormats): self
    {
        $clone = clone $this;
        $clone->datetimeFormats = $datetimeFormats;

        return $clone;
    }

    /**
     * @param list<string> $selectedFields
     */
    public function withSelectedFields(array $selectedFields): self
    {
        $clone = clone $this;
        $clone->selectedFields = array_values($selectedFields);

        return $clone;
    }

    /**
     * Fail-closed whitelist rules:
     * - No allowed_fields yet, but field_map exists → allowed = map keys + extras
     * - allowed_fields already set → merge in extras
     */
    public function normalize(): self
    {
        $clone = clone $this;

        $hasNoWhitelistYet = null === $clone->allowedFields;
        $hasFieldMap = [] !== $clone->fieldMap;

        if ($hasNoWhitelistYet && $hasFieldMap) {
            $clone->allowedFields = array_values(array_unique([
                ...array_keys($clone->fieldMap),
                ...$clone->extraAllowedFields,
            ]));

            return $clone;
        }

        if (null !== $clone->allowedFields && [] !== $clone->extraAllowedFields) {
            $clone->allowedFields = array_values(array_unique([
                ...$clone->allowedFields,
                ...$clone->extraAllowedFields,
            ]));
        }

        return $clone;
    }

    public function isFieldAllowed(string $field): bool
    {
        $normalized = $this->normalize();
        if (null === $normalized->allowedFields) {
            return true;
        }

        return \in_array($field, $normalized->allowedFields, true);
    }

    /**
     * Fail-closed whitelist after {@see normalize()}, or null when open.
     *
     * @return list<string>|null
     */
    public function getAllowedFields(): ?array
    {
        return $this->normalize()->allowedFields;
    }

    /**
     * @return array<string, string>
     */
    public function getFieldMap(): array
    {
        return $this->fieldMap;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function getController(): ?string
    {
        return $this->controller;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @return array<string, JoinConfig>
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $normalized = $this->normalize();
        $out = [];
        if ([] !== $normalized->fieldMap) {
            $out['field_map'] = $normalized->fieldMap;
        }
        if (null !== $normalized->allowedFields) {
            $out['allowed_fields'] = $normalized->allowedFields;
        }
        if ([] !== $normalized->extraAllowedFields) {
            $out['extra_allowed_fields'] = $normalized->extraAllowedFields;
        }
        if (null !== $normalized->entity) {
            $out['entity'] = $normalized->entity;
        }
        if (null !== $normalized->controller) {
            $out['controller'] = $normalized->controller;
        }
        if (null !== $normalized->action) {
            $out['action'] = $normalized->action;
        }
        if ([] !== $normalized->joins) {
            $out['joins'] = $normalized->joins;
        }
        if ([] !== $normalized->datetimeFormats) {
            $out['datetime_formats'] = $normalized->datetimeFormats;
        }
        if ([] !== $normalized->selectedFields) {
            $out['selected_fields'] = $normalized->selectedFields;
        }

        return $out;
    }
}
