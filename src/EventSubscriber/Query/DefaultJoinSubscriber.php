<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Query\LeftJoinApplicator;

/**
 * Fallback join handler: applies {@code LEFT JOIN path alias} via {@see LeftJoinApplicator}
 * when {@see \SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier} dispatches
 * {@see ApplyJoinEvent} for a field listed in {@code context['joins']}.
 */
final class DefaultJoinSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LeftJoinApplicator $leftJoinApplicator = new LeftJoinApplicator(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ApplyJoinEvent::NAME => ['onApplyJoin', -100],
        ];
    }

    public function onApplyJoin(ApplyJoinEventInterface $event): void
    {
        $this->leftJoinApplicator->apply($event);
    }
}
