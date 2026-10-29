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
 * Copies the demo shadcn theme CSS (oklch hue ~265) used with TableColors defaults.
 */
final class MakeTanstackTheme extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:tstdt-theme';
    }

    public static function getCommandDescription(): string
    {
        return 'Install the demo shadcn theme CSS (matches TableColors semantic tokens)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing tanstack-theme.css'
            )
            ->addOption(
                'as-app-css',
                null,
                InputOption::VALUE_NONE,
                'Also write/overwrite assets/styles/app.css with the theme'
            )
        ;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $force = (bool) $input->getOption('force');
        $asAppCss = (bool) $input->getOption('as-app-css');
        $installer = SkeletonInstaller::create();

        $result = $installer->installKits($generator, ['theme'], $force);

        if ($asAppCss) {
            $source = $installer->kitRoot('theme').'/assets/styles/tanstack-theme.css';
            $extra = $installer->copyMappedFiles($generator, [
                'assets/styles/app.css' => $source,
            ], $force);
            $result = $installer->mergeResults($result, $extra);
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $installer->writeResultSections($io, $result);

        $io->newLine();
        $io->text([
            'Theme written to <fg=yellow>assets/styles/tanstack-theme.css</>.',
            $asAppCss
                ? 'Also wrote <fg=yellow>assets/styles/app.css</>.'
                : 'Merge or @import it from your <fg=yellow>assets/styles/app.css</>.',
            'Then require CSS deps:',
            '  <fg=yellow>php bin/console importmap:require shadcn/dist/tailwind.css tw-animate-css/dist/tw-animate.css</>',
            'TableColors defaults (<fg=yellow>border</>, <fg=yellow>muted</>, <fg=yellow>primary/15</>, …) resolve against these CSS variables.',
        ]);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command installs the demo shadcn theme
(oklch hue ~265) aligned with <info>TableColors</info> semantic tokens.

  <info>php %command.full_name%</info>
  <info>php %command.full_name% --as-app-css --force</info>
HELP;
    }
}
