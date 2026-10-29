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
 * Copies Lucide SVG icons used by the table + admin demo UI.
 */
final class MakeTanstackIcons extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:tstdt-icons';
    }

    public static function getCommandDescription(): string
    {
        return 'Install Lucide SVG icons used by the table and admin demo UI';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing icon files'
            )
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $force = (bool) $input->getOption('force');
        $installer = SkeletonInstaller::create();
        $result = $installer->installKits($generator, ['icons'], $force);
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $installer->writeResultSections($io, $result);

        $io->newLine();
        $io->text([
            'Icons installed under <fg=yellow>assets/icons/lucide/</>.',
            'Ensure UX Icons is configured to load that path (see Symfony UX Icon docs).',
        ]);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command copies the Lucide SVGs used by
<info>tstdt:TanstackDataTable</info> and the admin shell.

  <info>php %command.full_name%</info>
  <info>php %command.full_name% --force</info>
HELP;
    }
}
