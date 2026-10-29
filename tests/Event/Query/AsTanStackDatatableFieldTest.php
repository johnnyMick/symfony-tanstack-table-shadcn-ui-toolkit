<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests\Event\Query;

use PHPUnit\Framework\TestCase;
use SymfonyTanstackTableShadcnUiToolkit\Event\Query\AsTanStackDatatableField;

final class AsTanStackDatatableFieldTest extends TestCase
{
    public function testPrecisionLadder(): void
    {
        self::assertSame(
            AsTanStackDatatableField::PRECISION_NONE,
            (new AsTanStackDatatableField('status'))->precision(),
        );
        self::assertSame(
            AsTanStackDatatableField::PRECISION_ENTITY,
            (new AsTanStackDatatableField('status', entity: 'App\\Entity\\Product'))->precision(),
        );
        self::assertSame(
            AsTanStackDatatableField::PRECISION_CONTROLLER,
            (new AsTanStackDatatableField(
                'status',
                entity: 'App\\Entity\\Product',
                controller: 'App\\Controller\\CatalogController',
            ))->precision(),
        );
        self::assertSame(
            AsTanStackDatatableField::PRECISION_ACTION,
            (new AsTanStackDatatableField(
                'status',
                entity: 'App\\Entity\\Product',
                controller: 'App\\Controller\\CatalogController',
                action: 'productsJson',
            ))->precision(),
        );
    }

    public function testActionRequiresControllerAndEntity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('action requires controller and entity');

        new AsTanStackDatatableField('status', entity: 'App\\Entity\\Product', action: 'productsJson');
    }

    public function testControllerRequiresEntity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('controller requires entity');

        new AsTanStackDatatableField('status', controller: 'App\\Controller\\CatalogController');
    }

    public function testMatchesContextByPrecision(): void
    {
        $max = new AsTanStackDatatableField(
            'status',
            entity: 'App\\Entity\\Product',
            controller: 'App\\Controller\\CatalogController',
            action: 'productsJson',
        );
        self::assertTrue($max->matchesContext(
            'App\\Entity\\Product',
            'App\\Controller\\CatalogController',
            'productsJson',
        ));
        self::assertFalse($max->matchesContext(
            'App\\Entity\\Product',
            'App\\Controller\\CatalogController',
            'categoriesJson',
        ));
        self::assertFalse($max->matchesContext(
            'App\\Entity\\Product',
            'App\\Controller\\CatalogController',
            null,
        ));

        $medium = new AsTanStackDatatableField(
            'status',
            entity: 'App\\Entity\\Product',
            controller: 'App\\Controller\\CatalogController',
        );
        self::assertTrue($medium->matchesContext(
            'App\\Entity\\Product',
            'App\\Controller\\CatalogController',
            'anything',
        ));
        self::assertFalse($medium->matchesContext(
            'App\\Entity\\Product',
            'App\\Controller\\OtherController',
            'productsJson',
        ));

        $low = new AsTanStackDatatableField('status', entity: 'App\\Entity\\Product');
        self::assertTrue($low->matchesContext('App\\Entity\\Product', null, null));
        self::assertTrue($low->matchesContext(
            'App\\Entity\\Product',
            'App\\Controller\\CatalogController',
            'productsJson',
        ));
        self::assertFalse($low->matchesContext('App\\Entity\\Order', null, null));

        $none = new AsTanStackDatatableField('status');
        self::assertTrue($none->matchesContext(null, null, null));
        self::assertTrue($none->matchesContext('App\\Entity\\Order', 'X', 'y'));
    }
}
