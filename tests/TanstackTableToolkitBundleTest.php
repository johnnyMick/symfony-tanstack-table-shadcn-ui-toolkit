<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use SymfonyTanstackTableShadcnUiToolkit\SymfonyTanstackTableShadcnUiToolkit;

final class TanstackTableToolkitBundleTest extends TestCase
{
    public function testBundleBootsInsideASymfonyKernel(): void
    {
        $kernel = new TanstackTableToolkitTestKernel('test', true);
        $kernel->boot();

        $bundles = $kernel->getBundles();

        self::assertArrayHasKey('SymfonyTanstackTableShadcnUiToolkit', $bundles);
        self::assertInstanceOf(
            SymfonyTanstackTableShadcnUiToolkit::class,
            $bundles['SymfonyTanstackTableShadcnUiToolkit']
        );

        $kernel->shutdown();
    }
}

final class TanstackTableToolkitTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SymfonyTanstackTableShadcnUiToolkit();
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/tstdt-toolkit-test';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test-secret',
            'test' => true,
        ]);
    }
}
