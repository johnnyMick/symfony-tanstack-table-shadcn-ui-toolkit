<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    private const LOCALES = ['en', 'fr', 'de', 'it'];

    #[Route(
        '/_locale/{_locale}',
        name: 'app_locale_switch',
        requirements: ['_locale' => 'en|fr|de|it'],
        methods: ['GET'],
    )]
    public function switch(string $_locale, Request $request, SessionInterface $session): RedirectResponse
    {
        if (!\in_array($_locale, self::LOCALES, true)) {
            throw $this->createNotFoundException();
        }

        $session->set('_locale', $_locale);

        $referer = $request->headers->get('Referer');
        if (\is_string($referer) && '' !== $referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_demo');
    }
}
