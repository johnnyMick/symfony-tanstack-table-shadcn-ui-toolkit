<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Advanced-filter definition for a {@see Column}.
 *
 * Example:
 *
 * <code>
 * Column::make('price')->currency()->filterNumber();
 * Column::make('name')->filterString();
 * Column::make('createdAt')->filterDateRange();
 * Column::make('status')->filterChoices(['active' => 'Active', 'inactive' => 'Inactive']);
 * Column::make('tags')->filterChoices($tags, multiple: true);
 * Column::make('internal')->withoutFilter();
 * </code>
 */
final class ColumnFilter
{
    public const TYPE_STRING = 'string';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_DATE_RANGE = 'dateRange';
    public const TYPE_CHOICE = 'choice';

    /** @var array<string, string> */
    private array $choices = [];

    private bool $multiple = false;

    private function __construct(
        private readonly string $type,
    ) {
    }

    public static function string(): self
    {
        return new self(self::TYPE_STRING);
    }

    public static function number(): self
    {
        return new self(self::TYPE_NUMBER);
    }

    public static function date(): self
    {
        return new self(self::TYPE_DATE);
    }

    public static function dateRange(): self
    {
        return new self(self::TYPE_DATE_RANGE);
    }

    /**
     * @param array<string, string> $choices value => label
     */
    public static function choice(array $choices, bool $multiple = false): self
    {
        $filter = new self(self::TYPE_CHOICE);
        $filter->choices = $choices;
        $filter->multiple = $multiple;

        return $filter;
    }

    public function multiple(bool $multiple = true): self
    {
        if (self::TYPE_CHOICE !== $this->type) {
            throw new \LogicException('multiple() is only valid for choice filters.');
        }
        $this->multiple = $multiple;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array{
     *     type: string,
     *     multiple: bool,
     *     choices: array<string, string>,
     *     operators: list<array{id: string, label: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'multiple' => $this->multiple,
            'choices' => $this->choices,
            'operators' => $this->defaultOperators(),
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function defaultOperators(): array
    {
        return match ($this->type) {
            self::TYPE_NUMBER => [
                ['id' => 'equals', 'label' => 'Equal to'],
                ['id' => 'gt', 'label' => 'Greater than'],
                ['id' => 'gte', 'label' => 'Greater or equal'],
                ['id' => 'lt', 'label' => 'Less than'],
                ['id' => 'lte', 'label' => 'Less or equal'],
                ['id' => 'between', 'label' => 'Between'],
            ],
            self::TYPE_DATE => [
                ['id' => 'equals', 'label' => 'On'],
                ['id' => 'before', 'label' => 'Before'],
                ['id' => 'after', 'label' => 'After'],
                ['id' => 'between', 'label' => 'Between'],
            ],
            self::TYPE_DATE_RANGE => [
                ['id' => 'between', 'label' => 'Between'],
            ],
            self::TYPE_CHOICE => $this->multiple
                ? [['id' => 'in', 'label' => 'Is any of']]
                : [['id' => 'equals', 'label' => 'Is']],
            default => [
                ['id' => 'contains', 'label' => 'Contains'],
                ['id' => 'startsWith', 'label' => 'Starts with'],
                ['id' => 'endsWith', 'label' => 'Ends with'],
                ['id' => 'equals', 'label' => 'Equal to'],
            ],
        };
    }
}
