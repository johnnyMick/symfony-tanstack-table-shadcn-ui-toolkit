<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies the session {@code _locale} (set by the account language switcher) to each request.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    private const LOCALES = ['en', 'fr', 'de', 'it'];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        $locale = $request->getSession()->get('_locale');
        if (!\is_string($locale) || !\in_array($locale, self::LOCALES, true)) {
            return;
        }

        $request->setLocale($locale);
    }
}
