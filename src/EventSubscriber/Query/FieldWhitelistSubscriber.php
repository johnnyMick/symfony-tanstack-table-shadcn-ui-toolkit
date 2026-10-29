<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySortEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySortEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\QueryEventInterface;

/**
 * High-priority gate for join / select / filter / sort.
 *
 * When {@code context['allowed_fields']} is set, unknown fields are blocked so
 * defaults and custom handlers never run for them.
 */
final class FieldWhitelistSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ApplyJoinEvent::NAME => ['onApplyJoin', 100],
            ApplySelectEvent::NAME => ['onApplySelect', 100],
            ApplyFilterEvent::NAME => ['onApplyFilter', 100],
            ApplySortEvent::NAME => ['onApplySort', 100],
        ];
    }

    public function onApplyJoin(ApplyJoinEventInterface $event): void
    {
        $this->blockIfNotAllowed($event);
    }

    public function onApplySelect(ApplySelectEventInterface $event): void
    {
        $this->blockIfNotAllowed($event);
    }

    public function onApplyFilter(ApplyFilterEventInterface $event): void
    {
        $this->blockIfNotAllowed($event);
    }

    public function onApplySort(ApplySortEventInterface $event): void
    {
        $this->blockIfNotAllowed($event);
    }

    private function blockIfNotAllowed(QueryEventInterface $event): void
    {
        $context = $event->getContext();

        // No whitelist key → open mode (other safety checks still apply later).
        if (!\array_key_exists('allowed_fields', $context)) {
            return;
        }

        $allowed = $context['allowed_fields'];
        if (!\is_array($allowed)) {
            $this->block($event);

            return;
        }

        $allowedNames = array_map(static fn (mixed $f): string => (string) $f, $allowed);
        $isAllowed = \in_array($event->getField(), $allowedNames, true);
        if ($isAllowed) {
            return;
        }

        $this->block($event);
    }

    private function block(QueryEventInterface $event): void
    {
        $event->markHandled();
        $event->stopPropagation();
    }
}
