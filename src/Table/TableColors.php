<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Table;

/**
 * Table color tokens for {@see TanstackDataTable}.
 *
 * Defaults follow the shadcn / demo {@code app.css} theme (hue ~265):
 * {@code border}, {@code muted}, {@code background}, {@code accent}, {@code primary}.
 *
 * Values may be theme tokens ({@code muted}, {@code border}), Tailwind-style
 * names ({@code slate-400}), optional opacity suffixes ({@code muted/50},
 * {@code primary/15}), or raw CSS ({@code oklch(...)}).
 *
 * The Stimulus controller resolves them to CSS variables on the table root.
 *
 * Example:
 *
 * <code>
 * $table->colors(
 *     TableColors::create()
 *         ->border('border')
 *         ->headerBg('muted')
 *         ->rowOdd('background')
 *         ->rowEven('muted')
 *         ->rowHover('accent')
 *         ->rowSelected('primary/15')
 * );
 * </code>
 */
final class TableColors
{
    /** Matches {@code --border} in app.css. */
    private string $border = 'border';

    /** Matches {@code --muted}. */
    private string $headerBg = 'muted';

    /** Matches {@code --foreground}. */
    private string $headerText = 'foreground';

    /** Matches {@code --background}. */
    private string $rowOdd = 'background';

    /** Matches {@code --muted} / {@code --secondary}. */
    private string $rowEven = 'muted';

    /** Matches {@code --accent}. */
    private string $rowHover = 'accent';

    /** Light primary tint (theme {@code --primary} at 15% opacity). */
    private string $rowSelected = 'primary/15';

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Cell / row / outer table border color.
     */
    public function border(string $color): self
    {
        $this->border = $color;

        return $this;
    }

    /**
     * Header cell background.
     */
    public function headerBg(string $color): self
    {
        $this->headerBg = $color;

        return $this;
    }

    /**
     * Header cell text color.
     */
    public function headerText(string $color): self
    {
        $this->headerText = $color;

        return $this;
    }

    /**
     * Odd row background.
     */
    public function rowOdd(string $color): self
    {
        $this->rowOdd = $color;

        return $this;
    }

    /**
     * Even row background.
     */
    public function rowEven(string $color): self
    {
        $this->rowEven = $color;

        return $this;
    }

    /**
     * Row hover background.
     */
    public function rowHover(string $color): self
    {
        $this->rowHover = $color;

        return $this;
    }

    /**
     * Selected row background.
     */
    public function rowSelected(string $color): self
    {
        $this->rowSelected = $color;

        return $this;
    }

    /**
     * @param array{
     *     border?: string,
     *     headerBg?: string,
     *     headerText?: string,
     *     rowOdd?: string,
     *     rowEven?: string,
     *     rowHover?: string,
     *     rowSelected?: string
     * } $colors
     */
    public function merge(array $colors): self
    {
        if (isset($colors['border'])) {
            $this->border = $colors['border'];
        }
        if (isset($colors['headerBg'])) {
            $this->headerBg = $colors['headerBg'];
        }
        if (isset($colors['headerText'])) {
            $this->headerText = $colors['headerText'];
        }
        if (isset($colors['rowOdd'])) {
            $this->rowOdd = $colors['rowOdd'];
        }
        if (isset($colors['rowEven'])) {
            $this->rowEven = $colors['rowEven'];
        }
        if (isset($colors['rowHover'])) {
            $this->rowHover = $colors['rowHover'];
        }
        if (isset($colors['rowSelected'])) {
            $this->rowSelected = $colors['rowSelected'];
        }

        return $this;
    }

    /**
     * @return array{
     *     border: string,
     *     headerBg: string,
     *     headerText: string,
     *     rowOdd: string,
     *     rowEven: string,
     *     rowHover: string,
     *     rowSelected: string
     * }
     */
    public function toArray(): array
    {
        return [
            'border' => $this->border,
            'headerBg' => $this->headerBg,
            'headerText' => $this->headerText,
            'rowOdd' => $this->rowOdd,
            'rowEven' => $this->rowEven,
            'rowHover' => $this->rowHover,
            'rowSelected' => $this->rowSelected,
        ];
    }
}
