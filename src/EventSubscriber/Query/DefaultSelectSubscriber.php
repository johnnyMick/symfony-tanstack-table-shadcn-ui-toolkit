<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEventInterface;

/**
 * Fallback select handler: {@code addSelect(expression AS field)} when nobody else handled the field.
 */
final class DefaultSelectSubscriber implements EventSubscriberInterface
{
    /** Accessor keys: letters, digits, underscore only. */
    private const SAFE_FIELD = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    public static function getSubscribedEvents(): array
    {
        return [
            ApplySelectEvent::NAME => ['onApplySelect', -100],
        ];
    }

    public function onApplySelect(ApplySelectEventInterface $event): void
    {
        if ($event->isPropagationStopped() || $event->isHandled()) {
            return;
        }

        $field = $event->getField();
        if (1 !== preg_match(self::SAFE_FIELD, $field)) {
            return;
        }

        $expression = $event->resolveExpression();
        if (null === $expression || '' === $expression) {
            return;
        }

        // Alias with the accessorKey so getArrayResult() keys match column ids.
        $event->getQueryBuilder()->addSelect($expression.' AS '.$field);
        $event->markHandled();
    }
}
