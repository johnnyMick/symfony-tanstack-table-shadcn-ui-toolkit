<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Toggle-switch column: renders a switch cell and optionally POSTs the new value via AJAX.
 *
 * Extends {@see Column} so you keep field / filter / class helpers while configuring
 * switch-specific URL, CSRF, and on/off values.
 *
 * <code>
 * ColumnSwitch::make('status')
 *     ->label('Status')
 *     ->url('/products/{id}/status')
 *     ->csrfToken($token)
 *     ->checkedValue('active')
 *     ->uncheckedValue('inactive')
 *     ->checkedLabel('Active')
 *     ->uncheckedLabel('Inactive');
 * </code>
 */
final class ColumnSwitch extends Column
{
    private ?string $switchUrl = null;

    private ?string $switchCsrfToken = null;

    private string $switchCsrfField = '_token';

    private string $switchValueField = 'value';

    private string $switchCheckedValue = '1';

    private string $switchUncheckedValue = '0';

    private string $switchCheckedLabel = '1';

    private string $switchUncheckedLabel = '0';

    public static function make(string $accessorKey): static
    {
        $column = new self($accessorKey);
        $column->syncSwitchMeta();

        return $column;
    }

    /**
     * Destination URL for the toggle POST. Supports {@code {id}} / {@code {field}} placeholders.
     */
    public function url(?string $url): static
    {
        $trimmed = null === $url ? '' : trim($url);
        $this->switchUrl = '' === $trimmed ? null : $trimmed;

        return $this->syncSwitchMeta();
    }

    public function csrfToken(?string $csrfToken): static
    {
        $trimmed = null === $csrfToken ? '' : trim($csrfToken);
        $this->switchCsrfToken = '' === $trimmed ? null : $trimmed;

        return $this->syncSwitchMeta();
    }

    public function csrfField(string $csrfField): static
    {
        $trimmed = trim($csrfField);
        $this->switchCsrfField = '' === $trimmed ? '_token' : $trimmed;

        return $this->syncSwitchMeta();
    }

    /**
     * Form field name for the posted on/off value (default {@code value}).
     */
    public function valueField(string $valueField): static
    {
        $trimmed = trim($valueField);
        $this->switchValueField = '' === $trimmed ? 'value' : $trimmed;

        return $this->syncSwitchMeta();
    }

    /**
     * @param string|bool|int $value Row / POST value when the switch is on
     */
    public function checkedValue(string|bool|int $value): static
    {
        return $this->withValues($value, $this->switchUncheckedValue);
    }

    /**
     * @param string|bool|int $value Row / POST value when the switch is off
     */
    public function uncheckedValue(string|bool|int $value): static
    {
        return $this->withValues($this->switchCheckedValue, $value);
    }

    public function checkedLabel(string $label): static
    {
        $this->switchCheckedLabel = $label;

        return $this->syncSwitchMeta();
    }

    public function uncheckedLabel(string $label): static
    {
        $this->switchUncheckedLabel = $label;

        return $this->syncSwitchMeta();
    }

    /**
     * Set both values (and optional labels) in one call.
     *
     * @param string|bool|int $checkedValue
     * @param string|bool|int $uncheckedValue
     */
    public function values(
        string|bool|int $checkedValue,
        string|bool|int $uncheckedValue,
        ?string $checkedLabel = null,
        ?string $uncheckedLabel = null,
    ): static {
        $column = $this->withValues($checkedValue, $uncheckedValue);
        if (null !== $checkedLabel) {
            $column = $column->checkedLabel($checkedLabel);
        }
        if (null !== $uncheckedLabel) {
            $column = $column->uncheckedLabel($uncheckedLabel);
        }

        return $column;
    }

    /**
     * @param string|bool|int $checkedValue
     * @param string|bool|int $uncheckedValue
     */
    private function withValues(string|bool|int $checkedValue, string|bool|int $uncheckedValue): static
    {
        $checked = self::normalizeValue($checkedValue);
        $unchecked = self::normalizeValue($uncheckedValue);
        if ($checked === $unchecked) {
            throw new \InvalidArgumentException('ColumnSwitch checkedValue and uncheckedValue must differ.');
        }

        // When labels still mirror the previous raw values, keep them in sync.
        if ($this->switchCheckedLabel === $this->switchCheckedValue) {
            $this->switchCheckedLabel = $checked;
        }
        if ($this->switchUncheckedLabel === $this->switchUncheckedValue) {
            $this->switchUncheckedLabel = $unchecked;
        }

        $this->switchCheckedValue = $checked;
        $this->switchUncheckedValue = $unchecked;

        return $this->syncSwitchMeta();
    }

    private function syncSwitchMeta(): static
    {
        return $this->meta([
            'format' => 'switch',
            'switchUrl' => $this->switchUrl,
            'switchCsrfToken' => $this->switchCsrfToken,
            'switchCheckedValue' => $this->switchCheckedValue,
            'switchUncheckedValue' => $this->switchUncheckedValue,
            'switchCsrfField' => $this->switchCsrfField,
            'switchValueField' => $this->switchValueField,
            'switchLabels' => [
                $this->switchCheckedValue => $this->switchCheckedLabel,
                $this->switchUncheckedValue => $this->switchUncheckedLabel,
            ],
        ]);
    }

    private static function normalizeValue(string|bool|int $value): string
    {
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
