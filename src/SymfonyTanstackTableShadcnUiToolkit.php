<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SymfonyTanstackTableShadcnUiToolkit extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');
    }

    /**
     * Expose skeleton Twig components under the {@code @tstdt} namespace for
     * {@code {% include '@tstdt/tstdt_Button.html.twig' %}} / embed without copying.
     *
     * Anonymous {@code <twig:tstdt_*>} tags still need components in the app
     * (run {@code make:tstdt-data-table}) so Stimulus targets and UX discovery work.
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('twig')) {
            return;
        }

        $componentsDir = \dirname(__DIR__).'/templates/skeleton/templates/components';
        if (!is_dir($componentsDir)) {
            return;
        }

        $builder->prependExtensionConfig('twig', [
            'paths' => [
                $componentsDir => 'tstdt',
            ],
        ]);
    }
}
