<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySortEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySortEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\AsTanStackDatatableField;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\QueryEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\TransformRowsEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\TransformRowsEventInterface;

/**
 * Base class for app field handlers (join / select / filter / sort / transform).
 *
 * 1. Put {@see AsTanStackDatatableField} on your subclass.
 * 2. Override the hooks you need ({@see join()}, {@see select()}, …).
 * 3. Call {@see QueryEventInterface::handleAndStop()} when you fully own that field.
 *
 * <code>
 * #[AsTanStackDatatableField('status', entity: Product::class)]
 * final class ProductStatusSubscriber extends AbstractDatatableFieldSubscriber
 * {
 *     protected static function priority(): int { return 10; }
 *
 *     protected function select(ApplySelectEventInterface $event): void { ... }
 *     protected function filter(ApplyFilterEventInterface $event): void { ... }
 * }
 * </code>
 */
// Tag is also registered in the bundle (this class is excluded from service loading).
#[AutoconfigureTag('tstdt.datatable_field')]
abstract class AbstractDatatableFieldSubscriber implements EventSubscriberInterface
{
    /** @var list<AsTanStackDatatableField>|null */
    private ?array $fieldAttributes = null;

    // -------------------------------------------------------------------------
    // Wiring
    // -------------------------------------------------------------------------

    public static function getSubscribedEvents(): array
    {
        return [
            ApplyJoinEvent::NAME => ['onApplyJoin', static::priority()],
            ApplySelectEvent::NAME => ['onApplySelect', static::priority()],
            ApplyFilterEvent::NAME => ['onApplyFilter', static::priority()],
            ApplySortEvent::NAME => ['onApplySort', static::priority()],
            TransformRowsEvent::NAME => ['onTransformRows', static::priority()],
        ];
    }

    /** Higher runs earlier. Override in the concrete subscriber (demo uses 10). */
    protected static function priority(): int
    {
        return 0;
    }

    /**
     * Attributes on this class (also used by {@see \SymfonyTanstackTableShadcnUiToolkit\Query\DatatableFieldJoinEnricher}).
     *
     * @return list<AsTanStackDatatableField>
     */
    final public function getFieldAttributes(): array
    {
        return $this->readFieldAttributes();
    }

    // -------------------------------------------------------------------------
    // Event entry points — skip if already done or this subscriber does not match
    // -------------------------------------------------------------------------

    public function onApplyJoin(ApplyJoinEventInterface $event): void
    {
        if (!$this->shouldHandle($event)) {
            return;
        }

        $this->join($event);
    }

    public function onApplySelect(ApplySelectEventInterface $event): void
    {
        if (!$this->shouldHandle($event)) {
            return;
        }

        $this->select($event);
    }

    public function onApplyFilter(ApplyFilterEventInterface $event): void
    {
        if (!$this->shouldHandle($event)) {
            return;
        }

        $this->filter($event);
    }

    public function onApplySort(ApplySortEventInterface $event): void
    {
        if (!$this->shouldHandle($event)) {
            return;
        }

        $this->sort($event);
    }

    public function onTransformRows(TransformRowsEventInterface $event): void
    {
        $context = $event->getContext();
        $selectedFields = $this->selectedFieldsFromContext($context);
        [$entity, $controller, $action] = $this->contextTargets($context);

        foreach ($this->readFieldAttributes() as $attribute) {
            if (!$attribute->matchesContext($entity, $controller, $action)) {
                continue;
            }

            // Request asked for a subset of columns — skip fields that were not selected.
            if ([] !== $selectedFields && !\in_array($attribute->field, $selectedFields, true)) {
                continue;
            }

            $this->transform($event, $attribute->field);
        }
    }

    // -------------------------------------------------------------------------
    // Override these in your subscriber (defaults do nothing)
    // -------------------------------------------------------------------------

    /** Custom JOIN (e.g. INNER / WITH). Default: leave it to DefaultJoinSubscriber. */
    protected function join(ApplyJoinEventInterface $event): void
    {
    }

    /** Custom SELECT expression. Default: leave it to DefaultSelectSubscriber. */
    protected function select(ApplySelectEventInterface $event): void
    {
    }

    /** Custom WHERE for this field. Default: leave it to DefaultFilterSubscriber. */
    protected function filter(ApplyFilterEventInterface $event): void
    {
    }

    /** Custom ORDER BY for this field. Default: leave it to DefaultSortSubscriber. */
    protected function sort(ApplySortEventInterface $event): void
    {
    }

    /** Map raw DB values after fetch (e.g. bool → badge label). */
    protected function transform(TransformRowsEventInterface $event, string $field): void
    {
    }

    // -------------------------------------------------------------------------
    // Matching
    // -------------------------------------------------------------------------

    /**
     * True when this subscriber should run for the event:
     * - event not already handled / stopped
     * - field name matches an attribute on this class
     * - entity / controller / action precision matches context
     */
    protected function matches(QueryEventInterface $event): bool
    {
        $eventField = $event->getField();
        [$entity, $controller, $action] = $this->contextTargets($event->getContext());

        foreach ($this->readFieldAttributes() as $attribute) {
            if ($eventField !== $attribute->field) {
                continue;
            }

            if ($attribute->matchesContext($entity, $controller, $action)) {
                return true;
            }
        }

        return false;
    }

    private function shouldHandle(QueryEventInterface $event): bool
    {
        if ($event->isPropagationStopped() || $event->isHandled()) {
            return false;
        }

        return $this->matches($event);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{0: ?string, 1: ?string, 2: ?string} entity, controller, action
     */
    private function contextTargets(array $context): array
    {
        return [
            $this->stringFromContext($context, 'entity'),
            $this->stringFromContext($context, 'controller'),
            $this->stringFromContext($context, 'action'),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function stringFromContext(array $context, string $key): ?string
    {
        $value = $context[$key] ?? null;
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<string>
     */
    private function selectedFieldsFromContext(array $context): array
    {
        $selected = $context['selected_fields'] ?? null;
        if (!\is_array($selected)) {
            return [];
        }

        return array_map(static fn (mixed $v): string => (string) $v, $selected);
    }

    /**
     * @return list<AsTanStackDatatableField>
     */
    private function readFieldAttributes(): array
    {
        if (null !== $this->fieldAttributes) {
            return $this->fieldAttributes;
        }

        $attributes = [];
        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getAttributes(AsTanStackDatatableField::class) as $attribute) {
            $attributes[] = $attribute->newInstance();
        }

        return $this->fieldAttributes = $attributes;
    }
}
