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
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

/**
 * Scaffolds Settings pages (demo data table + preferences).
 */
final class MakeTanstackSettingsPage extends AbstractMaker
{
    use MakerOverwriteTrait;

    public static function getCommandName(): string
    {
        return 'make:tstdt-settings-page';
    }

    public static function getCommandDescription(): string
    {
        return 'Create Settings pages (demo data table counts + preferences)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addOption(
                'with-admin-layout',
                null,
                InputOption::VALUE_NONE,
                'Wrap pages in AdminLayout (installs kit if missing)'
            )
            ->addOption(
                'with-reset',
                null,
                InputOption::VALUE_NONE,
                'Show Reset data button (expects app_demo_reset from make:tstdt-demo-seeder)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing controller / templates'
            )
        ;
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        $filesystem = new Filesystem();
        $hasLayout = $filesystem->exists($this->projectRoot().'/templates/components/tstdt/AdminLayout.html.twig');
        if (!$input->getOption('with-admin-layout') && $io->confirm('Wrap settings pages in AdminLayout?', $hasLayout)) {
            $input->setOption('with-admin-layout', true);
        }
        if (!$input->getOption('with-reset') && $io->confirm('Include Reset data button?', true)) {
            $input->setOption('with-reset', true);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(AbstractController::class, 'symfony/framework-bundle');
        $dependencies->addClassDependency(Connection::class, 'doctrine/dbal');
        $dependencies->addClassDependency(TanstackDataTable::class, 'johnnymick/symfony-tanstack-table-shadcn-ui-toolkit');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $force = (bool) $input->getOption('force');
        $withAdminLayout = (bool) $input->getOption('with-admin-layout');
        $withReset = (bool) $input->getOption('with-reset');
        $installer = SkeletonInstaller::create();
        $kitResult = ['created' => [], 'updated' => [], 'skipped' => []];

        if ($withAdminLayout) {
            $filesystem = new Filesystem();
            if ($force || !$filesystem->exists($generator->getRootDirectory().'/templates/components/tstdt/AdminLayout.html.twig')) {
                $kitResult = $installer->installKits($generator, ['admin-layout'], $force);
            }
        }

        $controllerDetails = $generator->createClassNameDetails('Settings', 'Controller\\', 'Controller');
        $controllerFullName = $controllerDetails->getFullName();
        $controllerRelativePath = $this->classRelativePath($generator, $controllerFullName);
        $this->prepareOverwrite($generator, $controllerRelativePath, $force);

        $makerDir = $installer->skeletonRoot().'/maker';
        $tables = ['category', 'product', 'customer', 'shop_order', 'order_item'];

        $generator->generateClass(
            $controllerFullName,
            $makerDir.'/SettingsController.tpl.php',
            [
                'tables' => $tables,
                'with_admin_layout' => $withAdminLayout,
            ]
        );

        $templates = [
            'templates/settings/demo_data.html.twig' => 'settings_demo_data.html.twig.tpl.php',
            'templates/settings/preferences.html.twig' => 'settings_preferences.html.twig.tpl.php',
        ];

        foreach ($templates as $target => $tpl) {
            $this->prepareOverwrite($generator, $target, $force);
            $generator->generateFile($target, $makerDir.'/'.$tpl, [
                'with_admin_layout' => $withAdminLayout,
                'with_reset' => $withReset,
            ]);
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $installer->writeResultSections($io, $kitResult);

        $io->text([
            'Created:',
            '  • '.$controllerRelativePath,
            '  • templates/settings/demo_data.html.twig',
            '  • templates/settings/preferences.html.twig',
        ]);
        $io->newLine();
        $io->text([
            'Next steps:',
            '  1. Adjust DEMO_TABLES in <fg=yellow>SettingsController</>',
            $withReset
                ? '  2. Install reset endpoint: <fg=yellow>php bin/console make:tstdt-demo-seeder</>'
                : '  2. Optional reset: <fg=yellow>php bin/console make:tstdt-demo-seeder</>',
            '  3. Open <fg=yellow>/settings/demo-data</>',
        ]);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command scaffolds Settings pages:

  • Demo data table (row counts via AJAX)
  • Preferences placeholder
  • Optional Reset button + AdminLayout

  <info>php %command.full_name% --with-admin-layout --with-reset</info>
HELP;
    }
}
