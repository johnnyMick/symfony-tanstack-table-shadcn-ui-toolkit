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
/**
 * Copies Stimulus controllers for the table + admin UI kits.
 */
final class MakeTanstackStimulus extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:tstdt-stimulus';
    }

    public static function getCommandDescription(): string
    {
        return 'Install Stimulus controllers under assets/controllers/tstdt/ (tstdt--tanstack-table, …)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing controllers'
            )
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // symfony/stimulus-bundle recommended; AssetMapper auto-loads *_controller.js
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $force = (bool) $input->getOption('force');
        $installer = SkeletonInstaller::create();
        $result = $installer->installKits($generator, ['stimulus'], $force);
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $installer->writeResultSections($io, $result);

        $io->newLine();
        $io->text([
            'Controllers installed under <fg=yellow>assets/controllers/tstdt/</>.',
            'Stimulus Bundle auto-registers <fg=yellow>*_controller.js</> when using AssetMapper.',
            'Also require TanStack Table: <fg=yellow>php bin/console importmap:require @tanstack/table-core</>',
        ]);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command copies Stimulus controllers into
<info>assets/controllers/tstdt/</info> (identifiers use the <info>tstdt--</info> prefix):

  tstdt--tanstack-table, tstdt--select, tstdt--dropdown-menu(+sub),
  tstdt--sidebar, tstdt--collapsible, tstdt--dialog, tstdt--tooltip,
  tstdt--workspace-tabs, tstdt--csrf-protection

  <info>php %command.full_name%</info>
  <info>php %command.full_name% --force</info>
HELP;
    }
}
