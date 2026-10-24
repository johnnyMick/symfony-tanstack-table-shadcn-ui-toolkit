<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Maker;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Scaffolds DemoSeeder + console reset command (+ optional HTTP reset controller).
 */
final class MakeTanstackDemoSeeder extends AbstractMaker
{
    use MakerOverwriteTrait;

    public static function getCommandName(): string
    {
        return 'make:tstdt-demo-seeder';
    }

    public static function getCommandDescription(): string
    {
        return 'Create DemoSeeder + app:demo:reset command (optional HTTP reset endpoint)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addOption(
                'with-http-reset',
                null,
                InputOption::VALUE_NONE,
                'Also create DemoResetController (POST /demo/reset)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing files'
            )
        ;
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (!$input->getOption('with-http-reset') && $io->confirm('Also create HTTP reset controller (POST /demo/reset)?', true)) {
            $input->setOption('with-http-reset', true);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(EntityManagerInterface::class, 'doctrine/orm');
        $dependencies->addClassDependency(Connection::class, 'doctrine/dbal');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $force = (bool) $input->getOption('force');
        $withHttp = (bool) $input->getOption('with-http-reset');
        $installer = SkeletonInstaller::create();
        $makerDir = $installer->skeletonRoot().'/maker';
        $tables = ['category', 'product', 'customer', 'shop_order', 'order_item'];

        $seederDetails = $generator->createClassNameDetails('DemoSeeder', 'Datatable\\');
        $commandDetails = $generator->createClassNameDetails('ResetDemo', 'Command\\', 'Command');

        $this->prepareOverwrite($generator, $this->classRelativePath($generator, $seederDetails->getFullName()), $force);
        $this->prepareOverwrite($generator, $this->classRelativePath($generator, $commandDetails->getFullName()), $force);

        $generator->generateClass(
            $seederDetails->getFullName(),
            $makerDir.'/DemoSeeder.tpl.php',
            [
                'tables' => $tables,
            ]
        );

        $generator->generateClass(
            $commandDetails->getFullName(),
            $makerDir.'/ResetDemoCommand.tpl.php',
            [
                'command_name' => 'app:demo:reset',
                'seeder_fqcn' => $seederDetails->getFullName(),
                'seeder_short' => $seederDetails->getShortName(),
            ]
        );

        $created = [
            $this->classRelativePath($generator, $seederDetails->getFullName()),
            $this->classRelativePath($generator, $commandDetails->getFullName()),
        ];

        if ($withHttp) {
            $resetDetails = $generator->createClassNameDetails('DemoReset', 'Controller\\', 'Controller');
            $this->prepareOverwrite($generator, $this->classRelativePath($generator, $resetDetails->getFullName()), $force);
            $generator->generateClass(
                $resetDetails->getFullName(),
                $makerDir.'/DemoResetController.tpl.php',
                [
                    'seeder_fqcn' => $seederDetails->getFullName(),
                    'seeder_short' => $seederDetails->getShortName(),
                    'redirect_route' => 'app_settings_demo_data',
                ]
            );
            $created[] = $this->classRelativePath($generator, $resetDetails->getFullName());
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);

        $io->text(array_merge(['Created:'], array_map(static fn (string $p): string => '  • '.$p, $created)));
        $io->newLine();
        $io->text([
            'Next steps:',
            '  1. Implement inserts in <fg=yellow>'.$seederDetails->getShortName().'::seed()</>',
            '  2. Align TABLES with your schema',
            '  3. Run <fg=yellow>php bin/console app:demo:reset</>',
            $withHttp
                ? '  4. Pair with <fg=yellow>make:tstdt-settings-page --with-reset</>'
                : '  4. Optional HTTP form: re-run with <fg=yellow>--with-http-reset</>',
        ]);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command scaffolds:

  • <info>Datatable/DemoSeeder</info> (truncate + TODO seed)
  • <info>app:demo:reset</info> console command
  • Optional <info>DemoResetController</info> for the Settings reset button

  <info>php %command.full_name%</info>
  <info>php %command.full_name% --with-http-reset</info>
HELP;
    }
}
