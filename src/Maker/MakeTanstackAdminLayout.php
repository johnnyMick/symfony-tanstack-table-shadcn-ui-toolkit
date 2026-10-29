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
        return 'Create the tstdt:AdminLayout shell (Sidebar, Sheet, Collapsible, Avatar, Tooltip)';
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
            '  2. Configure the shell in PHP with <fg=yellow>AdminLayout::create()</> (brand, nav, user, tabs)',
            '  3. Wrap list pages:',
            '     <fg=yellow><twig:tstdt:AdminLayout :config="layout"></>',
            '     <fg=yellow>  <twig:tstdt:TanstackDataTable :config="table" /></>',
            '     <fg=yellow></twig:tstdt:AdminLayout></>',
            '  4. Put a destination on the tab button: <fg=yellow>WorkspaceTabButton::create()->url(\'/…/{id}/edit\')</>',
            '  5. Or open from JS: <fg=yellow>workspace-tabs:open</> with <fg=yellow>{ url, label? }</> (url is the tab id)',
            '  6. Or scaffold a resource: <fg=yellow>php bin/console make:tstdt-crud-resource Product --with-admin-layout</>',
        ]);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command scaffolds the demo-style admin shell:

  • <info>AdminLayout</info> Twig component (partials under <info>components/tstdt/AdminLayout/</info>)
    + PHP config API (<info>AdminLayout::create()</info>)
  • Workspace tabs (main list + placeholder slots) keyed by button <info>url</info>
  • <info>Sidebar</info> (+ Sheet for mobile)
  • <info>Collapsible</info>, <info>Avatar</info>, <info>Tooltip</info>
  • Stimulus controllers under assets/controllers/tstdt/:
    sidebar, collapsible, dialog, tooltip, <info>workspace-tabs</info>
  • Related Lucide icons

Configure brand / nav / <info>tabCount</info> from the controller, pass
<info>:config="layout"</info>, and set <info>WorkspaceTabButton::url()</info>
(with <info>{id}</info> placeholders) so each tab button owns its destination.
Or dispatch <info>workspace-tabs:open</info> with <info>{ url }</info>.
Or run <info>make:tstdt-crud-resource --with-admin-layout</info>.

Requires DropdownMenu / Separator / Button from <info>make:tstdt-data-table</info>.

  <info>php %command.full_name%</info>
  <info>php %command.full_name% --force</info>
HELP;
    }
}
