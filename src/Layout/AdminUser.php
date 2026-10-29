<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Layout;

/**
 * Sidebar footer account menu for {@see AdminLayout}.
 */
final class AdminUser
{
    private string $name = 'Admin';

    private ?string $email = 'admin@example.test';

    private string $initials = 'AD';

    private ?string $logoutHref = null;

    private string $logoutLabel = 'Log out';

    private string $menuLabel = 'My account';

    private string $tooltip = 'Account';

    private string $languageLabel = 'Language';

    private string $locale = 'en';

    /** @var list<array{label: string, href: ?string, icon: ?string, variant: ?string}> */
    private array $menuItems = [];

    /** @var list<array{code: string, label: string, href: ?string}> */
    private array $languages = [];

    private function __construct()
    {
        $this->menuItems = [
            ['label' => 'Profile', 'href' => null, 'icon' => 'lucide:user', 'variant' => null],
        ];
        $this->languages = self::defaultLanguages();
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Built-in language options (English / French / German / Italian).
     * Set {@code href} via {@see languages()} so the selector can switch locale.
     *
     * @return list<array{code: string, label: string, href: ?string}>
     */
    public static function defaultLanguages(): array
    {
        return [
            ['code' => 'en', 'label' => 'English', 'href' => null],
            ['code' => 'fr', 'label' => 'Français', 'href' => null],
            ['code' => 'de', 'label' => 'Deutsch', 'href' => null],
            ['code' => 'it', 'label' => 'Italiano', 'href' => null],
        ];
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function email(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function initials(string $initials): self
    {
        $this->initials = $initials;

        return $this;
    }

    public function logoutHref(?string $logoutHref): self
    {
        $this->logoutHref = $logoutHref;

        return $this;
    }

    public function logoutLabel(string $logoutLabel): self
    {
        $this->logoutLabel = $logoutLabel;

        return $this;
    }

    public function menuLabel(string $menuLabel): self
    {
        $this->menuLabel = $menuLabel;

        return $this;
    }

    public function tooltip(string $tooltip): self
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function languageLabel(string $languageLabel): self
    {
        $this->languageLabel = $languageLabel;

        return $this;
    }

    /**
     * Current UI locale (highlights the matching language option).
     */
    public function locale(string $locale): self
    {
        $trimmed = strtolower(trim($locale));
        $this->locale = '' !== $trimmed ? $trimmed : 'en';

        return $this;
    }

    /**
     * Replace account dropdown items (logout is separate via {@see logoutHref()}).
     *
     * @param list<array{label: string, href?: ?string, icon?: ?string, variant?: ?string}> $menuItems
     */
    public function menuItems(array $menuItems): self
    {
        $normalized = [];
        foreach ($menuItems as $item) {
            $normalized[] = [
                'label' => $item['label'],
                'href' => $item['href'] ?? null,
                'icon' => $item['icon'] ?? null,
                'variant' => $item['variant'] ?? null,
            ];
        }
        $this->menuItems = $normalized;

        return $this;
    }

    public function withoutMenuItems(): self
    {
        $this->menuItems = [];

        return $this;
    }

    /**
     * Language options shown in the account menu.
     *
     * @param list<array{code: string, label: string, href?: ?string}> $languages
     */
    public function languages(array $languages): self
    {
        $normalized = [];
        foreach ($languages as $language) {
            $code = strtolower(trim((string) ($language['code'] ?? '')));
            $label = trim((string) ($language['label'] ?? ''));
            if ('' === $code || '' === $label) {
                continue;
            }
            $normalized[] = [
                'code' => $code,
                'label' => $label,
                'href' => $language['href'] ?? null,
            ];
        }
        $this->languages = $normalized;

        return $this;
    }

    public function withoutLanguages(): self
    {
        $this->languages = [];

        return $this;
    }

    /**
     * @return array{
     *     name: string,
     *     email: ?string,
     *     initials: string,
     *     logoutHref: ?string,
     *     logoutLabel: string,
     *     menuLabel: string,
     *     tooltip: string,
     *     languageLabel: string,
     *     locale: string,
     *     menuItems: list<array{label: string, href: ?string, icon: ?string, variant: ?string}>,
     *     languages: list<array{code: string, label: string, href: ?string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'initials' => $this->initials,
            'logoutHref' => $this->logoutHref,
            'logoutLabel' => $this->logoutLabel,
            'menuLabel' => $this->menuLabel,
            'tooltip' => $this->tooltip,
            'languageLabel' => $this->languageLabel,
            'locale' => $this->locale,
            'menuItems' => $this->menuItems,
            'languages' => $this->languages,
        ];
    }
}
