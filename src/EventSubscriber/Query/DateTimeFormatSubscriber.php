<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\TransformRowsEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\TransformRowsEventInterface;

/**
 * Formats {@see \DateTimeInterface} cell values for JSON.
 *
 * Context:
 * - {@code datetime_format} — default format (default {@code Y-m-d})
 * - {@code datetime_formats} — per-column overrides
 */
final class DateTimeFormatSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TransformRowsEvent::NAME => ['onTransformRows', -100],
        ];
    }

    public function onTransformRows(TransformRowsEventInterface $event): void
    {
        $defaultFormat = $this->defaultFormat($event->getContext());
        $perFieldFormats = $this->perFieldFormats($event->getContext());

        $rows = $event->getRows();
        $changed = false;

        foreach ($rows as $index => $row) {
            foreach ($row as $field => $value) {
                if (!$value instanceof \DateTimeInterface) {
                    continue;
                }

                $format = $perFieldFormats[$field] ?? $defaultFormat;
                $rows[$index][$field] = $value->format($format);
                $changed = true;
            }
        }

        if ($changed) {
            $event->setRows($rows);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function defaultFormat(array $context): string
    {
        $default = $context['datetime_format'] ?? 'Y-m-d';
        if (\is_string($default) && '' !== $default) {
            return $default;
        }

        return 'Y-m-d';
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, string>
     */
    private function perFieldFormats(array $context): array
    {
        $formats = $context['datetime_formats'] ?? [];
        if (!\is_array($formats)) {
            return [];
        }

        $out = [];
        foreach ($formats as $field => $format) {
            if (\is_string($field) && \is_string($format) && '' !== $format) {
                $out[$field] = $format;
            }
        }

        return $out;
    }
}
