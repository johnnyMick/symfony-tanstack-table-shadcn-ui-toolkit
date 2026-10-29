<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use SymfonyTanstackTableShadcnUiToolkit\DataCollector\DatatableDataCollector;
use SymfonyTanstackTableShadcnUiToolkit\DataCollector\DatatableTrace;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackAdminLayout;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackCrudResource;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackDataTable;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackDataTableController;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackDemoDashboard;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackDemoEntities;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackDemoSeeder;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackIcons;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackKit;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackSettingsPage;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackStimulus;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackTheme;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private()
        ->load('SymfonyTanstackTableShadcnUiToolkit\\', '../src/')
        ->exclude([
            '../src/SymfonyTanstackTableShadcnUiToolkit.php',
            '../src/Maker/',
            '../src/Table/',
            // Event DTOs / attributes are instantiated by the applier or reflection, not the container.
            '../src/Event/',
            // Abstract base subscriber — apps extend it; do not register as a service.
            '../src/EventSubscriber/Query/AbstractDatatableFieldSubscriber.php',
            // Example only — copy into the app; do not register globally.
            '../src/EventSubscriber/Query/ComputedFieldSubscriber.php',
            // Data collectors are registered explicitly with profiler template metadata.
            '../src/DataCollector/',
        ]);

    // Request-scoped debug recorder (used by applier + TableQuery + profiler panel).
    $container->services()
        ->set(DatatableTrace::class)
            ->tag('kernel.reset', ['method' => 'reset']);

    // Profiler panel: TableQuery + DatatableQueryApplier + Apply* events.
    if (interface_exists(DataCollectorInterface::class)) {
        $container->services()
            ->set(DatatableDataCollector::class)
                ->args([service(DatatableTrace::class)])
                ->tag('data_collector', [
                    'template' => '@SymfonyTanstackTableShadcnUiToolkit/datatable.html.twig',
                    'id' => DatatableDataCollector::NAME,
                    'priority' => 260,
                ]);
    }

    // MakerBundle is optional (require-dev / suggest). Register only when present.
    if (class_exists(AbstractMaker::class)) {
        $container->services()
            ->set(MakeTanstackDataTable::class)
                ->tag('maker.command')
            ->set(MakeTanstackDataTableController::class)
                ->tag('maker.command')
            ->set(MakeTanstackAdminLayout::class)
                ->tag('maker.command')
            ->set(MakeTanstackCrudResource::class)
                ->tag('maker.command')
            ->set(MakeTanstackDemoDashboard::class)
                ->tag('maker.command')
            ->set(MakeTanstackSettingsPage::class)
                ->tag('maker.command')
            ->set(MakeTanstackDemoEntities::class)
                ->tag('maker.command')
            ->set(MakeTanstackDemoSeeder::class)
                ->tag('maker.command')
            ->set(MakeTanstackIcons::class)
                ->tag('maker.command')
            ->set(MakeTanstackStimulus::class)
                ->tag('maker.command')
            ->set(MakeTanstackTheme::class)
                ->tag('maker.command')
            ->set(MakeTanstackKit::class)
                ->tag('maker.command');
    }
};
