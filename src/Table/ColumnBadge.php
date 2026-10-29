<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * One badge option for {@see Column::badge()} — value, variant, and display label together.
 *
 * <code>
 * Column::make('status')->badge([
 *     ColumnBadge::make('pending')->variant('secondary')->label('Pending'),
 *     ColumnBadge::make('paid')->variant('default')->label('Paid'),
 * ]);
 * </code>
 */
final class ColumnBadge
{
    private string $variant = 'secondary';

    private ?string $label = null;

    private function __construct(
        private readonly string $value,
    ) {
    }

    public static function make(string $value): self
    {
        return new self($value);
    }

    public function variant(string $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getVariant(): string
    {
        return $this->variant;
    }

    public function getLabel(): string
    {
        return $this->label ?? $this->value;
    }

    /**
     * @return array{value: string, variant: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'variant' => $this->variant,
            'label' => $this->getLabel(),
        ];
    }
}
