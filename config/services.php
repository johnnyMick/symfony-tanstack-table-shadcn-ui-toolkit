<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackAdminLayout;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackCrudResource;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackDataTable;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackDataTableController;
use SymfonyTanstackTableShadcnUiToolkit\Maker\MakeTanstackDemoDashboard;
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
        ]);

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
