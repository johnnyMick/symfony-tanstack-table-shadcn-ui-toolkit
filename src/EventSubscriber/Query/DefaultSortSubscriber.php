<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySortEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySortEventInterface;

/**
 * Fallback sort handler: {@code addOrderBy(expression, ASC|DESC)}.
 */
final class DefaultSortSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ApplySortEvent::NAME => ['onApplySort', -100],
        ];
    }

    public function onApplySort(ApplySortEventInterface $event): void
    {
        if ($event->isPropagationStopped() || $event->isHandled()) {
            return;
        }

        $expression = $event->resolveExpression();
        if (null === $expression || '' === $expression) {
            return;
        }

        $direction = 'desc' === strtolower($event->getDirection()) ? 'DESC' : 'ASC';
        $event->getQueryBuilder()->addOrderBy($expression, $direction);
        $event->markHandled();
    }
}
