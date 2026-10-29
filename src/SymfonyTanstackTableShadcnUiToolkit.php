<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use SymfonyTanstackTableShadcnUiToolkit\EventSubscriber\Query\AbstractDatatableFieldSubscriber;

class SymfonyTanstackTableShadcnUiToolkit extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        // Abstract base is excluded from service loading, so #[AutoconfigureTag] on it
        // never runs — register the tag here for DatatableFieldJoinEnricher.
        $builder->registerForAutoconfiguration(AbstractDatatableFieldSubscriber::class)
            ->addTag('tstdt.datatable_field');
    }

    /**
     * Expose skeleton Twig components under the {@code @tstdt} namespace for
     * {@code {% include '@tstdt/Button.html.twig' %}} / embed without copying.
     *
     * Anonymous {@code <twig:tstdt:*>} tags still need components in the app
     * (run {@code make:tstdt-data-table}) so Stimulus targets and UX discovery work.
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('twig')) {
            return;
        }

        $paths = [];

        $collectorDir = \dirname(__DIR__).'/templates/Collector';
        if (is_dir($collectorDir)) {
            // @SymfonyTanstackTableShadcnUiToolkit/datatable.html.twig (profiler panel)
            $paths[$collectorDir] = 'SymfonyTanstackTableShadcnUiToolkit';
        }

        $componentsDir = \dirname(__DIR__).'/templates/skeleton/templates/components/tstdt';
        if (is_dir($componentsDir)) {
            $paths[$componentsDir] = 'tstdt';
        }

        if ([] === $paths) {
            return;
        }

        $builder->prependExtensionConfig('twig', [
            'paths' => $paths,
        ]);
    }
}
