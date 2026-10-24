<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Install one or more UI kits (consolidates optional make:tstdt-* kit flags).
 */
final class MakeTanstackKit extends AbstractMaker
{
    private const KITS = [
        'admin-layout',
        'avatar',
        'badge',
        'dialog',
        'icons',
        'stimulus',
        'table',
        'theme',
        'tooltip',
    ];

    public static function getCommandName(): string
    {
        return 'make:tstdt-kit';
    }

    public static function getCommandDescription(): string
    {
        return 'Install one or more tstdt UI kits (admin-layout, badge, stimulus, theme, …)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addArgument(
                'kits',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Kit names: '.implode(', ', self::KITS)
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing files'
            )
            ->addOption(
                'list',
                'l',
                InputOption::VALUE_NONE,
                'List available kits and exit'
            )
        ;

        $inputConfig->setArgumentAsNonInteractive('kits');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if ($input->getOption('list')) {
            return;
        }

        if (!$input->getArgument('kits')) {
            $chosen = $io->choice(
                'Which kit to install?',
                self::KITS,
                'badge'
            );
            $input->setArgument('kits', [$chosen]);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        if ($input->getOption('list')) {
            $io->section('Available kits');
            foreach (self::KITS as $kit) {
                $io->text('  • '.$kit);
            }

            return;
        }

        /** @var list<string> $kits */
        $kits = $input->getArgument('kits');
        $unknown = array_diff($kits, self::KITS);
        if ($unknown !== []) {
            throw new \InvalidArgumentException(\sprintf(
                'Unknown kit(s): %s. Available: %s',
                implode(', ', $unknown),
                implode(', ', self::KITS)
            ));
        }

        $force = (bool) $input->getOption('force');
        $installer = SkeletonInstaller::create();
        $result = $installer->installKits($generator, array_values($kits), $force);
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $installer->writeResultSections($io, $result);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command installs optional UI kits:

  <info>php %command.full_name% --list</info>
  <info>php %command.full_name% badge dialog</info>
  <info>php %command.full_name% admin-layout --force</info>

Dedicated makers (aliases) still exist, e.g. <info>make:tstdt-admin-layout</info>,
<info>make:tstdt-icons</info>, <info>make:tstdt-theme</info>.
HELP;
    }
}
