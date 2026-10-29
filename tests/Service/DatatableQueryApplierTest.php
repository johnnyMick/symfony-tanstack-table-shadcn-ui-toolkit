<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Service;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyFilterEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplyJoinEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\ApplySelectEvent;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\AsTanStackDatatableField;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\TransformRowsEventInterface;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\AbstractDatatableFieldSubscriber;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\ComputedFieldSubscriber;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\DateTimeFormatSubscriber;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\DefaultFilterSubscriber;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\DefaultJoinSubscriber;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\DefaultSelectSubscriber;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\DefaultSortSubscriber;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\FieldWhitelistSubscriber;
use SymfonyTanstackTableShadcnUiToolkit\Query\DatatableFieldJoinEnricher;
use SymfonyTanstackTableShadcnUiToolkit\Query\QueryContext;
use SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

final class DatatableQueryApplierTest extends TestCase
{
    public function testApplySelectsDispatchesPerFieldAndSkipsInvalid(): void
    {
        $dispatcher = new EventDispatcher();
        $seen = [];
        $dispatcher->addListener(ApplySelectEvent::NAME, static function (ApplySelectEvent $event) use (&$seen): void {
            $seen[] = $event->getField();
        });

        $qb = $this->createQueryBuilderStub();
        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects($qb, ['status', '', '  ', 12, 'title']);

        self::assertSame(['status', 'title'], $seen);
    }

    public function testApplyFiltersDispatchesNormalizedOperator(): void
    {
        $dispatcher = new EventDispatcher();
        /** @var list<ApplyFilterEvent> $seen */
        $seen = [];
        $dispatcher->addListener(ApplyFilterEvent::NAME, static function (ApplyFilterEvent $event) use (&$seen): void {
            $seen[] = $event;
        });

        $qb = $this->createQueryBuilderStub();
        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applyFilters($qb, [
            ['field' => 'status', 'operator' => 'EQ', 'value' => 'published'],
            ['operator' => 'eq', 'value' => 'missing-field'],
            'not-an-array',
        ]);

        self::assertCount(1, $seen);
        self::assertSame('status', $seen[0]->getField());
        self::assertSame('eq', $seen[0]->getOperator());
        self::assertSame('published', $seen[0]->getValue());
        self::assertSame('e', $seen[0]->getRootAlias());
    }

    public function testDefaultSelectAddsSafeField(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())
            ->method('addSelect')
            ->with('e.status AS status')
            ->willReturnSelf();

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects($qb, ['status'], $this->ctx(['allowed_fields' => ['status']]));
    }

    public function testFieldMapExpressionIsUsedForSelects(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())
            ->method('addSelect')
            ->with('c.name AS category')
            ->willReturnSelf();

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects(
            $qb,
            ['category'],
            $this->ctx(['field_map' => ['category' => 'c.name'], 'allowed_fields' => ['category']]),
        );
    }

    public function testJoinsContextDispatchesApplyJoinAndLeftJoinsBeforeSelect(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        /** @var list<ApplyJoinEvent> $joins */
        $joins = [];
        $dispatcher->addListener(ApplyJoinEvent::NAME, static function (ApplyJoinEvent $event) use (&$joins): void {
            $joins[] = $event;
        }, 100);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())
            ->method('leftJoin')
            ->with('p.category', 'c')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('addSelect')
            ->with('c.name AS category')
            ->willReturnSelf();

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects(
            $qb,
            ['category'],
            $this->ctx([
                'field_map' => ['category' => 'c.name'],
                'allowed_fields' => ['category'],
                'joins' => ['category' => 'p.category'],
            ]),
        );

        self::assertCount(1, $joins);
        self::assertSame('category', $joins[0]->getField());
        self::assertSame('c', $joins[0]->getJoinAlias());
        self::assertSame('p.category', $joins[0]->getJoinPath());
        self::assertTrue($joins[0]->isPropagationStopped());
    }

    public function testJoinAliasConfigOverridesInferredAlias(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())
            ->method('leftJoin')
            ->with('o.customer', 'cust')
            ->willReturnSelf();

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects(
            $qb,
            ['customer'],
            $this->ctx([
                'field_map' => ['customer' => 'cust.firstName'],
                'allowed_fields' => ['customer'],
                'joins' => ['customer' => ['alias' => 'cust', 'path' => 'o.customer']],
            ]),
        );
    }

    public function testJoinSkippedWhenAliasAlreadyPresent(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getRootAliases')->willReturn(['p']);
        $qb->method('getAllAliases')->willReturn(['p', 'c']);
        $qb->method('expr')->willReturn(new Expr());
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->expects(self::never())->method('leftJoin');

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applyFilters(
            $qb,
            [['field' => 'category', 'operator' => 'contains', 'value' => 'book']],
            $this->ctx([
                'field_map' => ['category' => 'c.name'],
                'joins' => ['category' => 'p.category'],
            ]),
        );
    }

    public function testNoJoinDispatchedWithoutJoinsContext(): void
    {
        $dispatcher = new EventDispatcher();
        $seen = 0;
        $dispatcher->addListener(ApplyJoinEvent::NAME, static function () use (&$seen): void {
            ++$seen;
        });

        $qb = $this->createQueryBuilderStub();
        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects($qb, ['category'], $this->ctx([
            'field_map' => ['category' => 'c.name'],
            'allowed_fields' => ['category'],
        ]));

        self::assertSame(0, $seen);
    }

    public function testJoinDedupedAcrossFieldsSharingAlias(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();
        $aliases = ['e'];
        $qb->method('getRootAliases')->willReturn(['e']);
        $qb->method('getAllAliases')->willReturnCallback(static function () use (&$aliases): array {
            return $aliases;
        });
        $qb->expects(self::once())
            ->method('leftJoin')
            ->with('o.customer', 'c')
            ->willReturnCallback(static function () use (&$aliases, $qb) {
                $aliases[] = 'c';

                return $qb;
            });

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects(
            $qb,
            ['customer', 'email'],
            $this->ctx([
                'field_map' => [
                    'customer' => 'c.firstName',
                    'email' => 'c.email',
                ],
                'allowed_fields' => ['customer', 'email'],
                'joins' => [
                    'customer' => ['alias' => 'c', 'path' => 'o.customer'],
                    'email' => ['alias' => 'c', 'path' => 'o.customer'],
                ],
            ]),
        );
    }

    public function testJoinChainDispatchesMultipleHops(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();
        $aliases = ['e'];
        $qb->method('getRootAliases')->willReturn(['e']);
        $qb->method('getAllAliases')->willReturnCallback(static function () use (&$aliases): array {
            return $aliases;
        });
        $qb->expects(self::exactly(2))
            ->method('leftJoin')
            ->willReturnCallback(static function (string $path, string $alias) use (&$aliases, $qb) {
                $aliases[] = $alias;

                return $qb;
            });

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects(
            $qb,
            ['country'],
            $this->ctx([
                'field_map' => ['country' => 'c.country'],
                'allowed_fields' => ['country'],
                'joins' => [
                    'country' => [
                        ['alias' => 'o', 'path' => 'i.order'],
                        ['alias' => 'c', 'path' => 'o.customer'],
                    ],
                ],
            ]),
        );
    }

    public function testFieldMapFailClosedDerivesAllowedFields(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::never())->method('addSelect');

        $applier = new DatatableQueryApplier($dispatcher);
        // secret not in field_map → whitelist derived from map keys blocks it
        $applier->applySelects($qb, ['secret'], $this->ctx([
            'field_map' => ['name' => 'e.name'],
        ]));
    }

    public function testQueryContextFromTableIncludesJoins(): void
    {
        $table = TanstackDataTable::create('t')
            ->columns([
                Column::make('category')->field('c.name')->join('p.category'),
                Column::make('sku')->field('p.sku'),
            ]);

        $context = $table->queryContext(entity: 'App\\Entity\\Product', extraAllowed: ['status']);
        $array = $context->toArray();

        self::assertSame(['category' => 'c.name', 'sku' => 'p.sku'], $array['field_map']);
        self::assertContains('status', $array['allowed_fields']);
        self::assertContains('category', $context->getAllowedFields() ?? []);
        self::assertSame('p.category', $array['joins']['category'][0]['path']);
        self::assertSame('App\\Entity\\Product', $array['entity']);
    }

    public function testAttributeJoinEnrichedWithoutColumnJoin(): void
    {
        $subscriber = new #[AsTanStackDatatableField('category', entity: 'App\\Entity\\Product', join: 'p.category', joinAlias: 'c')] class extends AbstractDatatableFieldSubscriber {
        };

        $enricher = new DatatableFieldJoinEnricher([$subscriber]);
        $context = $enricher->enrich(QueryContext::from([
            'entity' => 'App\\Entity\\Product',
            'field_map' => ['category' => 'c.name'],
            'allowed_fields' => ['category'],
        ]));

        self::assertSame('p.category', $context->getJoins()['category'][0]['path']);
        self::assertSame('c', $context->getJoins()['category'][0]['alias']);

        $dispatcher = $this->createConfiguredDispatcher();
        $dispatcher->addSubscriber($subscriber);
        /** @var list<ApplyJoinEvent> $joins */
        $joins = [];
        $dispatcher->addListener(ApplyJoinEvent::NAME, static function (ApplyJoinEvent $event) use (&$joins): void {
            $joins[] = $event;
        }, 50);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())
            ->method('leftJoin')
            ->with('p.category', 'c')
            ->willReturnSelf();

        $applier = new DatatableQueryApplier($dispatcher, joinEnricher: $enricher);
        $applier->applySelects(
            $qb,
            ['category'],
            QueryContext::from([
                'entity' => 'App\\Entity\\Product',
                'field_map' => ['category' => 'c.name'],
                'allowed_fields' => ['category'],
            ]),
        );

        self::assertCount(1, $joins);
        self::assertSame('category', $joins[0]->getField());
        self::assertSame('p.category', $joins[0]->getJoinPath());
    }

    public function testColumnJoinOverridesAttributeJoin(): void
    {
        $subscriber = new #[AsTanStackDatatableField('category', entity: 'App\\Entity\\Product', join: 'p.other', joinAlias: 'x')] class extends AbstractDatatableFieldSubscriber {
        };

        $enricher = new DatatableFieldJoinEnricher([$subscriber]);
        $context = $enricher->enrich(QueryContext::from([
            'entity' => 'App\\Entity\\Product',
            'joins' => ['category' => [['path' => 'p.category', 'alias' => 'c']]],
        ]));

        self::assertSame('p.category', $context->getJoins()['category'][0]['path']);
        self::assertSame('c', $context->getJoins()['category'][0]['alias']);
    }

    public function testAttributeJoinRespectsControllerAndAction(): void
    {
        $subscriber = new #[AsTanStackDatatableField(
            'category',
            entity: 'App\\Entity\\Product',
            controller: 'App\\Controller\\CatalogController',
            action: 'productsJson',
            join: 'p.category',
            joinAlias: 'c',
        )] class extends AbstractDatatableFieldSubscriber {
        };

        $enricher = new DatatableFieldJoinEnricher([$subscriber]);

        $miss = $enricher->enrich(QueryContext::from([
            'entity' => 'App\\Entity\\Product',
            'controller' => 'App\\Controller\\CatalogController',
            'action' => 'categoriesJson',
        ]));
        self::assertArrayNotHasKey('category', $miss->getJoins());

        $hit = $enricher->enrich(QueryContext::from([
            'entity' => 'App\\Entity\\Product',
            'controller' => 'App\\Controller\\CatalogController',
            'action' => 'productsJson',
        ]));
        self::assertSame('p.category', $hit->getJoins()['category'][0]['path']);
    }

    public function testApplySortsAddsOrderBy(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())
            ->method('addOrderBy')
            ->with('e.name', 'DESC')
            ->willReturnSelf();

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySorts(
            $qb,
            [['field' => 'name', 'direction' => 'desc']],
            $this->ctx(['field_map' => ['name' => 'e.name'], 'allowed_fields' => ['name']]),
        );
    }

    public function testMarkHandledPreventsDefaultSelect(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $dispatcher->addListener(ApplySelectEvent::NAME, static function (ApplySelectEvent $event): void {
            $event->getQueryBuilder()->addSelect('CUSTOM AS status');
            $event->markHandled();
        }, 50);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())
            ->method('addSelect')
            ->with('CUSTOM AS status')
            ->willReturnSelf();

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects($qb, ['status'], $this->ctx(['allowed_fields' => ['status']]));
    }

    public function testWhitelistBlocksUnknownSelectField(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::never())->method('addSelect');

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects($qb, ['secretColumn'], $this->ctx(['allowed_fields' => ['status']]));
    }

    public function testDefaultFilterAppliesEqWithUniqueParameter(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();

        $qb->expects(self::once())
            ->method('andWhere')
            ->with(self::callback(static function (mixed $expr): bool {
                return str_contains((string) $expr, 'e.status');
            }))
            ->willReturnSelf();

        $qb->expects(self::once())
            ->method('setParameter')
            ->with(self::callback(static fn (string $name): bool => str_starts_with($name, 'dtf_')), 'published')
            ->willReturnSelf();

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applyFilters(
            $qb,
            [['field' => 'status', 'operator' => 'eq', 'value' => 'published']],
            $this->ctx(['allowed_fields' => ['status']]),
        );
    }

    public function testComputedFullNameStopsDefaultSelect(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();

        $qb->expects(self::once())
            ->method('addSelect')
            ->with(self::callback(static function (string $select): bool {
                return str_contains($select, 'CONCAT') && str_contains($select, 'fullName');
            }))
            ->willReturnSelf();

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects($qb, ['fullName'], $this->ctx(['allowed_fields' => ['fullName']]));
    }

    public function testUnsafeFieldNamesAreIgnoredByDefaultSelect(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::never())->method('addSelect');

        // No whitelist — regex safety still rejects DQL injection attempts.
        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applySelects($qb, ['status; DROP', 'e.id', 'foo.bar']);
    }

    public function testFieldMapExpressionIsUsedForFilters(): void
    {
        $dispatcher = $this->createConfiguredDispatcher();
        $qb = $this->createQueryBuilderMock();

        $qb->expects(self::once())
            ->method('andWhere')
            ->with(self::callback(static function (mixed $expr): bool {
                return str_contains((string) $expr, 'c.name');
            }))
            ->willReturnSelf();

        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applyFilters(
            $qb,
            [['field' => 'category', 'operator' => 'contains', 'value' => 'book']],
            $this->ctx(['field_map' => ['category' => 'c.name']]),
        );
    }

    public function testApplyFiltersPassesValueTo(): void
    {
        $dispatcher = new EventDispatcher();
        /** @var list<ApplyFilterEvent> $seen */
        $seen = [];
        $dispatcher->addListener(ApplyFilterEvent::NAME, static function (ApplyFilterEvent $event) use (&$seen): void {
            $seen[] = $event;
        });

        $qb = $this->createQueryBuilderStub();
        $applier = new DatatableQueryApplier($dispatcher);
        $applier->applyFilters($qb, [
            ['field' => 'createdAt', 'operator' => 'between', 'value' => '2024-01-01', 'valueTo' => '2024-12-31'],
        ]);

        self::assertCount(1, $seen);
        self::assertSame('2024-01-01', $seen[0]->getValue());
        self::assertSame('2024-12-31', $seen[0]->getValueTo());
    }

    public function testTransformRowsMapsFieldViaSubscriber(): void
    {
        $subscriber = new #[AsTanStackDatatableField('status')] class extends AbstractDatatableFieldSubscriber {
            protected static function priority(): int
            {
                return 10;
            }

            protected function transform(TransformRowsEventInterface $event, string $field): void
            {
                $event->mapColumn($field, static fn (mixed $v): string => filter_var($v, \FILTER_VALIDATE_BOOLEAN) ? 'active' : 'inactive');
            }
        };

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new DateTimeFormatSubscriber());
        $dispatcher->addSubscriber($subscriber);

        $applier = new DatatableQueryApplier($dispatcher);
        $rows = $applier->transformRows([
            ['id' => 1, 'status' => true, 'createdAt' => new \DateTimeImmutable('2024-05-01')],
            ['id' => 2, 'status' => false, 'createdAt' => new \DateTimeImmutable('2024-06-02')],
        ], $this->ctx([
            'datetime_formats' => ['createdAt' => 'Y-m-d'],
        ]));

        self::assertSame([
            ['id' => 1, 'status' => 'active', 'createdAt' => '2024-05-01'],
            ['id' => 2, 'status' => 'inactive', 'createdAt' => '2024-06-02'],
        ], $rows);
    }


    /**
     * @param array<string, mixed> $context
     */
    private function ctx(array $context = []): QueryContext
    {
        return QueryContext::from($context);
    }

    private function createConfiguredDispatcher(): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new FieldWhitelistSubscriber());
        $dispatcher->addSubscriber(new ComputedFieldSubscriber());
        $dispatcher->addSubscriber(new DefaultJoinSubscriber());
        $dispatcher->addSubscriber(new DefaultSelectSubscriber());
        $dispatcher->addSubscriber(new DefaultFilterSubscriber());
        $dispatcher->addSubscriber(new DefaultSortSubscriber());
        $dispatcher->addSubscriber(new DateTimeFormatSubscriber());

        return $dispatcher;
    }

    private function createQueryBuilderStub(): QueryBuilder&\PHPUnit\Framework\MockObject\Stub
    {
        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('getRootAliases')->willReturn(['e']);
        $qb->method('getAllAliases')->willReturn(['e']);
        $qb->method('expr')->willReturn(new Expr());
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();

        return $qb;
    }

    private function createQueryBuilderMock(): QueryBuilder&\PHPUnit\Framework\MockObject\MockObject
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getRootAliases')->willReturn(['e']);
        $qb->method('getAllAliases')->willReturn(['e']);
        $qb->method('expr')->willReturn(new Expr());
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();

        return $qb;
    }
}
