<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Maker;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Scaffolds a dashboard page with table row-count cards.
 */
final class MakeTanstackDemoDashboard extends AbstractMaker
{
    use MakerOverwriteTrait;

    public static function getCommandName(): string
    {
        return 'make:tstdt-demo-dashboard';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a dashboard page with table count cards (demo index style)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addOption(
                'with-admin-layout',
                null,
                InputOption::VALUE_NONE,
                'Wrap the dashboard in AdminLayout (installs kit if missing)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing controller / template'
            )
        ;
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        $filesystem = new Filesystem();
        $hasLayout = $filesystem->exists($this->projectRoot().'/templates/components/tstdt_AdminLayout.html.twig');
        if (!$input->getOption('with-admin-layout') && $io->confirm('Wrap dashboard in AdminLayout?', $hasLayout)) {
            $input->setOption('with-admin-layout', true);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(AbstractController::class, 'symfony/framework-bundle');
        $dependencies->addClassDependency(Connection::class, 'doctrine/dbal');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $force = (bool) $input->getOption('force');
        $withAdminLayout = (bool) $input->getOption('with-admin-layout');
        $installer = SkeletonInstaller::create();
        $kitResult = ['created' => [], 'updated' => [], 'skipped' => []];

        if ($withAdminLayout) {
            $filesystem = new Filesystem();
            if ($force || !$filesystem->exists($generator->getRootDirectory().'/templates/components/tstdt_AdminLayout.html.twig')) {
                $kitResult = $installer->installKits($generator, ['admin-layout'], $force);
            }
        }

        $controllerDetails = $generator->createClassNameDetails('Demo', 'Controller\\', 'Controller');
        $controllerFullName = $controllerDetails->getFullName();
        $controllerRelativePath = $this->classRelativePath($generator, $controllerFullName);
        $this->prepareOverwrite($generator, $controllerRelativePath, $force);

        $templateTarget = 'templates/demo/index.html.twig';
        $this->prepareOverwrite($generator, $templateTarget, $force);

        $makerDir = $installer->skeletonRoot().'/maker';
        $cards = [
            'product' => ['label' => 'Products', 'route' => 'app_catalog_products', 'icon' => 'lucide:package'],
            'category' => ['label' => 'Categories', 'route' => 'app_catalog_categories', 'icon' => 'lucide:tags'],
            'shop_order' => ['label' => 'Orders', 'route' => 'app_sales_orders', 'icon' => 'lucide:shopping-cart'],
            'customer' => ['label' => 'Customers', 'route' => 'app_sales_customers', 'icon' => 'lucide:users'],
        ];

        $generator->generateClass(
            $controllerFullName,
            $makerDir.'/DemoDashboardController.tpl.php',
            [
                'route_path' => '/',
                'route_name' => 'app_demo',
                'template' => 'demo/index.html.twig',
                'cards' => $cards,
            ]
        );

        $generator->generateFile(
            $templateTarget,
            $makerDir.'/demo_dashboard.html.twig.tpl.php',
            [
                'with_admin_layout' => $withAdminLayout,
            ]
        );

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $installer->writeResultSections($io, $kitResult);

        $io->text([
            'Created:',
            '  • '.$controllerRelativePath,
            '  • '.$templateTarget,
        ]);
        $io->newLine();
        $io->text([
            'Next steps:',
            '  1. Adjust CARD tables / routes in <fg=yellow>'.$controllerDetails->getShortName().'</>',
            '  2. Ensure Card UI exists (from <fg=yellow>make:tstdt-data-table</>)',
            '  3. Open <fg=yellow>/</>',
        ]);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command scaffolds a dashboard with count cards
(similar to the demo home page).

  <info>php %command.full_name%</info>
  <info>php %command.full_name% --with-admin-layout</info>
HELP;
    }
}
