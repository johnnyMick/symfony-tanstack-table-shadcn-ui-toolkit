<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Event\Query;

/**
 * Shared helper: turn a field name + context into a DQL expression.
 *
 * Rules (top to bottom):
 * 1. If {@code field_map} is a non-empty array → use the mapped expression,
 *    or {@code null} when the field is missing (fail closed).
 * 2. Otherwise → {@code rootAlias.field} when the field name is a safe identifier.
 */
final class FieldExpression
{
    private const SAFE_FIELD = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /**
     * @param array<string, mixed> $context
     */
    public static function resolve(string $field, string $rootAlias, array $context): ?string
    {
        $map = $context['field_map'] ?? null;
        $hasFieldMap = \is_array($map) && [] !== $map;

        if ($hasFieldMap) {
            $expression = $map[$field] ?? null;
            if (\is_string($expression) && '' !== $expression) {
                return $expression;
            }

            return null;
        }

        if (1 !== preg_match(self::SAFE_FIELD, $field)) {
            return null;
        }

        return $rootAlias.'.'.$field;
    }
}
