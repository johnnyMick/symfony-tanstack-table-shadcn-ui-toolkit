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
 * Scaffolds AdminLayout + Sidebar / Sheet / Collapsible / Avatar / Tooltip.
 */
final class MakeTanstackAdminLayout extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:tstdt-admin-layout';
    }

    public static function getCommandDescription(): string
    {
        return 'Create the tstdt_AdminLayout shell (Sidebar, Sheet, Collapsible, Avatar, Tooltip)';
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
        ;
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
        $installer = SkeletonInstaller::create();

        $result = $installer->installKits($generator, ['admin-layout'], $force);
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $installer->writeResultSections($io, $result);

        $io->newLine();
        $io->text([
            'Next steps:',
            '  1. Ensure core table UI exists: <fg=yellow>php bin/console make:tstdt-data-table --with-controllers --with-icons</>',
            '  2. Edit nav links in <fg=yellow>templates/components/tstdt_AdminLayout.html.twig</>',
            '  3. Wrap pages:',
            '     <fg=yellow>{% extends \'base.html.twig\' %}</>',
            '     <fg=yellow>{% block body %}</>',
            '     <fg=yellow>  <twig:tstdt_AdminLayout></>',
            '     <fg=yellow>    <twig:block name="title">Products</twig:block></>',
            '     <fg=yellow>    <twig:tstdt_TanstackDataTable :config="table" /></>',
            '     <fg=yellow>  </twig:tstdt_AdminLayout></>',
            '     <fg=yellow>{% endblock %}</>',
        ]);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command scaffolds the demo-style admin shell:

  • <info>AdminLayout</info>
  • <info>Sidebar</info> (+ Sheet for mobile)
  • <info>Collapsible</info>, <info>Avatar</info>, <info>Tooltip</info>
  • Stimulus controllers: sidebar, collapsible, dialog, tooltip
  • Related Lucide icons

Requires DropdownMenu / Separator / Button from <info>make:tstdt-data-table</info>.

  <info>php %command.full_name%</info>
  <info>php %command.full_name% --force</info>
HELP;
    }
}
