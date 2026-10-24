<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Scaffolds tstdt_TanstackDataTable and optional shadcn kit dependencies.
 */
final class MakeTanstackDataTable extends AbstractMaker
{
    private const KIT_OPTIONS = [
        'with-badge' => 'badge',
        'with-dialog' => 'dialog',
        'with-tooltip' => 'tooltip',
        'with-avatar' => 'avatar',
        'with-table' => 'table',
        'with-admin-layout' => 'admin-layout',
    ];

    public static function getCommandName(): string
    {
        return 'make:tstdt-data-table';
    }

    public static function getCommandDescription(): string
    {
        return 'Create the tstdt_TanstackDataTable Twig component and its dependencies';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing files'
            )
            ->addOption(
                'with-controllers',
                null,
                InputOption::VALUE_NONE,
                'Also install Stimulus controllers (tanstack-table, select, dropdown-menu)'
            )
            ->addOption(
                'with-icons',
                null,
                InputOption::VALUE_NONE,
                'Also install Lucide SVG icons used by the table UI'
            )
            ->addOption(
                'with-badge',
                null,
                InputOption::VALUE_NONE,
                'Also install Badge component'
            )
            ->addOption(
                'with-dialog',
                null,
                InputOption::VALUE_NONE,
                'Also install Dialog component + controller'
            )
            ->addOption(
                'with-tooltip',
                null,
                InputOption::VALUE_NONE,
                'Also install Tooltip component + controller'
            )
            ->addOption(
                'with-avatar',
                null,
                InputOption::VALUE_NONE,
                'Also install Avatar component'
            )
            ->addOption(
                'with-table',
                null,
                InputOption::VALUE_NONE,
                'Also install static Table (Head/Cell/Row/…) components'
            )
            ->addOption(
                'with-admin-layout',
                null,
                InputOption::VALUE_NONE,
                'Also install AdminLayout + Sidebar / Sheet / Collapsible kit'
            )
        ;
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (!$input->getOption('with-controllers') && $io->confirm('Install Stimulus controllers (table, select, dropdown-menu)?', true)) {
            $input->setOption('with-controllers', true);
        }

        if (!$input->getOption('with-icons') && $io->confirm('Install Lucide icons used by the table UI?', true)) {
            $input->setOption('with-icons', true);
        }

        if (!$input->getOption('with-admin-layout') && $io->confirm('Install AdminLayout (sidebar shell)?', false)) {
            $input->setOption('with-admin-layout', true);
        }

        if (!$input->getOption('with-badge') && $io->confirm('Install Badge component?', false)) {
            $input->setOption('with-badge', true);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(
            AsTwigComponent::class,
            'symfony/ux-twig-component'
        );
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $force = (bool) $input->getOption('force');
        $withControllers = (bool) $input->getOption('with-controllers');
        $withIcons = (bool) $input->getOption('with-icons');

        $installer = SkeletonInstaller::create();
        $core = $installer->installCore($generator, $withControllers, $withIcons, $force);

        $kits = [];
        foreach (self::KIT_OPTIONS as $option => $kit) {
            if ($input->getOption($option)) {
                $kits[] = $kit;
            }
        }

        $kitResult = ['created' => [], 'updated' => [], 'skipped' => []];
        if ($kits !== []) {
            $kitResult = $installer->installKits($generator, $kits, $force);
        }

        $result = $installer->mergeResults($core, $kitResult);
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $installer->writeResultSections($io, $result);

        $io->newLine();
        $io->text([
            'Next steps:',
            '  1. Ensure shadcn CSS / theme is configured (see bundle README).',
            '  2. Require TanStack Table in your importmap if needed:',
            '     <fg=yellow>php bin/console importmap:require @tanstack/table-core</>',
            '  3. Use the component:',
            '     <fg=yellow><twig:tstdt_TanstackDataTable :config="table" /></>',
            '  4. Optional: <fg=yellow>php bin/console make:tstdt-crud-resource Product</>',
        ]);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command scaffolds <info>tstdt_TanstackDataTable</info>
and namespaced shadcn Twig components (<info>tstdt_Button</info>, <info>tstdt_Input</info>,
<info>tstdt_Select</info>, <info>tstdt_DropdownMenu</info>, <info>tstdt_Separator</info>, <info>tstdt_Card</info>).

Optional kits (from the demo app):

  <info>--with-badge</info>          tstdt_Badge
  <info>--with-dialog</info>         tstdt_Dialog + Stimulus controller
  <info>--with-tooltip</info>        tstdt_Tooltip + Stimulus controller
  <info>--with-avatar</info>         tstdt_Avatar
  <info>--with-table</info>          Static tstdt_Table primitives
  <info>--with-admin-layout</info>   tstdt_AdminLayout + Sidebar / Sheet / Collapsible

  <info>php %command.full_name% --with-controllers --with-icons</info>
  <info>php %command.full_name% --with-admin-layout --with-badge --force</info>
HELP;
    }
}
