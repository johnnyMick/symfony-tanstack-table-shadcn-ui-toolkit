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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Scaffolds DemoSeeder + console reset command (+ optional HTTP reset / User entity).
 */
final class MakeTanstackDemoSeeder extends AbstractMaker
{
    use MakerOverwriteTrait;

    private const CATALOG_ENTITIES = [
        'Category',
        'Product',
        'Customer',
        'Order',
        'OrderItem',
    ];

    public static function getCommandName(): string
    {
        return 'make:tstdt-demo-seeder';
    }

    public static function getCommandDescription(): string
    {
        return 'Create DemoSeeder + app:demo:reset command (optional HTTP reset + User)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addOption(
                'with-http-reset',
                null,
                InputOption::VALUE_NONE,
                'Also create DemoResetController (POST /demo/reset, synced from demo)'
            )
            ->addOption(
                'with-entities',
                null,
                InputOption::VALUE_NONE,
                'Also install demo Doctrine entities (Category, Product, Customer, Order, OrderItem)'
            )
            ->addOption(
                'with-user',
                null,
                InputOption::VALUE_NONE,
                'Install User + UserRepository and seed admin/admin on reset (synced from demo)'
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

        $entityPath = $this->projectRoot().'/src/Entity/Category.php';
        $hasEntities = is_file($entityPath);
        if (!$input->getOption('with-entities') && !$hasEntities && $io->confirm('Install demo Doctrine entities (Category, Product, …)?', true)) {
            $input->setOption('with-entities', true);
        }

        $userPath = $this->projectRoot().'/src/Entity/User.php';
        $hasUser = is_file($userPath);
        if (!$input->getOption('with-user') && !$hasUser && $io->confirm('Install demo User entity + seed admin/admin on reset?', true)) {
            $input->setOption('with-user', true);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(EntityManagerInterface::class, 'doctrine/orm');
        $dependencies->addClassDependency(Connection::class, 'doctrine/dbal');
        $dependencies->addClassDependency(UserPasswordHasherInterface::class, 'symfony/password-hasher');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $force = (bool) $input->getOption('force');
        $withHttp = (bool) $input->getOption('with-http-reset');
        $withEntities = (bool) $input->getOption('with-entities');
        $withUser = (bool) $input->getOption('with-user');
        $installer = SkeletonInstaller::create();
        $makerDir = $installer->skeletonRoot().'/maker';
        $rootNs = $generator->getRootNamespace();

        $tables = ['category', 'product', 'customer', 'shop_order', 'order_item'];
        if ($withUser) {
            array_unshift($tables, 'app_user');
        }

        $entityResult = null;
        if ($withEntities || $withUser) {
            $entityResult = $installer->installDemoEntities(
                $generator,
                $force,
                withUser: $withUser,
                withCatalog: $withEntities,
            );
        }

        $entityFqcns = [];
        if ($withEntities) {
            foreach (self::CATALOG_ENTITIES as $short) {
                $entityFqcns[] = $rootNs.'\\Entity\\'.$short;
            }
        } elseif (is_file($this->projectRoot().'/src/Entity/Category.php')) {
            foreach (self::CATALOG_ENTITIES as $short) {
                if (is_file($this->projectRoot().'/src/Entity/'.$short.'.php')) {
                    $entityFqcns[] = $rootNs.'\\Entity\\'.$short;
                }
            }
        }

        $seederDetails = $generator->createClassNameDetails('DemoSeeder', 'Datatable\\');
        $commandDetails = $generator->createClassNameDetails('ResetDemo', 'Command\\', 'Command');

        $this->prepareOverwrite($generator, $this->classRelativePath($generator, $seederDetails->getFullName()), $force);
        $this->prepareOverwrite($generator, $this->classRelativePath($generator, $commandDetails->getFullName()), $force);

        $generator->generateClass(
            $seederDetails->getFullName(),
            $makerDir.'/DemoSeeder.tpl.php',
            [
                'tables' => $tables,
                'with_user' => $withUser,
                'user_fqcn' => $rootNs.'\\Entity\\User',
                'user_short' => 'User',
                'entity_fqcns' => $entityFqcns,
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

        if (null !== $entityResult) {
            $installer->writeResultSections($io, $entityResult);
        }

        $io->text(array_merge(['Created:'], array_map(static fn (string $p): string => '  • '.$p, $created)));
        $io->newLine();
        $next = [
            'Next steps:',
            $withEntities || $withUser
                ? '  1. Create schema: <fg=yellow>php bin/console doctrine:schema:update --force</>'
                : '  1. Install entities: <fg=yellow>php bin/console make:tstdt-demo-entities</>',
            '  2. Implement inserts in <fg=yellow>'.$seederDetails->getShortName().'::seed()</> (see bundle demo seeder; needs <fg=yellow>fakerphp/faker</>)',
            '  3. Run <fg=yellow>php bin/console app:demo:reset</>',
        ];
        if ($withUser) {
            $next[] = '  4. Wire <fg=yellow>security.yaml</fg> entity provider to <fg=yellow>'.$rootNs.'\\Entity\\User</> (property: username); demo login is <fg=yellow>admin</>/<fg=yellow>admin</>';
            $next[] = $withHttp
                ? '  5. Pair with <fg=yellow>make:tstdt-settings-page --with-reset</> (or <fg=yellow>make:tstdt-demo-dashboard --with-http-reset</>)'
                : '  5. Optional HTTP form: re-run with <fg=yellow>--with-http-reset</> or <fg=yellow>make:tstdt-demo-dashboard --with-http-reset</>';
        } else {
            $next[] = $withHttp
                ? '  4. Pair with <fg=yellow>make:tstdt-settings-page --with-reset</> (or <fg=yellow>make:tstdt-demo-dashboard --with-http-reset</>)'
                : '  4. Optional HTTP form: re-run with <fg=yellow>--with-http-reset</> or <fg=yellow>make:tstdt-demo-dashboard --with-http-reset</>';
        }
        $io->text($next);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command scaffolds:

  • <info>Datatable/DemoSeeder</info> (truncate / schema rebuild + seed)
  • <info>app:demo:reset</info> console command
  • Optional <info>DemoResetController</info> (<info>--with-http-reset</info>, synced from demo)
  • Optional demo Doctrine entities (<info>--with-entities</info>)
  • Optional <info>User</info> + <info>UserRepository</info> + admin seed (<info>--with-user</info>)

  <info>php %command.full_name%</info>
  <info>php %command.full_name% --with-http-reset --with-entities --with-user</info>
HELP;
    }
}
