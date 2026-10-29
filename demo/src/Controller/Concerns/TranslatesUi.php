<?php

declare(strict_types=1);

namespace App\Controller\Concerns;

use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * English message-id helpers for demo controllers ({@code messages} domain).
 *
 * Translator is injected (not via AbstractController’s limited service locator).
 */
trait TranslatesUi
{
    private TranslatorInterface $translator;

    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function t(string $id, array $parameters = [], string $domain = 'messages'): string
    {
        return $this->translator->trans($id, $parameters, $domain);
    }
}
