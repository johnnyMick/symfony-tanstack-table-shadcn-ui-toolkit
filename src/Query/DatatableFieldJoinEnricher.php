<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Query;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\AbstractDatatableFieldSubscriber;

/**
 * Merges join hops declared on {@see \SymfonyTanstackTableShadcnUiToolkit\Event\Query\AsTanStackDatatableField}
 * into {@see QueryContext} so {@see ApplyJoinEvent} runs without {@code Column::join()}.
 *
 * Existing {@code context['joins'][field]} from the table wins (column API overrides the attribute).
 */
final class DatatableFieldJoinEnricher
{
    /**
     * @param iterable<AbstractDatatableFieldSubscriber> $fieldSubscribers
     */
    public function __construct(
        #[AutowireIterator('tstdt.datatable_field')]
        private readonly iterable $fieldSubscribers = new \ArrayIterator([]),
    ) {
    }

    public function enrich(QueryContext $context): QueryContext
    {
        $entity = $context->getEntity();
        $controller = $context->getController();
        $action = $context->getAction();
        $joins = $context->getJoins();
        $changed = false;

        foreach ($this->fieldSubscribers as $subscriber) {
            if (!$subscriber instanceof AbstractDatatableFieldSubscriber) {
                continue;
            }

            foreach ($subscriber->getFieldAttributes() as $attribute) {
                $hop = $attribute->toJoinHop();
                if (null === $hop) {
                    continue;
                }

                if (!$attribute->matchesContext($entity, $controller, $action)) {
                    continue;
                }

                if (\array_key_exists($attribute->field, $joins)) {
                    continue;
                }

                $joins[$attribute->field] = [$hop];
                $changed = true;
            }
        }

        return $changed ? $context->withJoins($joins) : $context;
    }
}
