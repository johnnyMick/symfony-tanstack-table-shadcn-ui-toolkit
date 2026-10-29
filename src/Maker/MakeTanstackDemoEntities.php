<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Maker;

use Doctrine\ORM\Mapping\Entity;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Scaffolds the demo Catalog/Sales Doctrine entities used by DemoSeeder.
 */
final class MakeTanstackDemoEntities extends AbstractMaker
{
    use MakerOverwriteTrait;

    public static function getCommandName(): string
    {
        return 'make:tstdt-demo-entities';
    }

    public static function getCommandDescription(): string
    {
        return 'Create demo Doctrine entities (Category, Product, …; optional User)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addOption(
                'with-user',
                null,
                InputOption::VALUE_NONE,
                'Also install User + UserRepository (synced from the bundle demo)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing entity files'
            )
        ;
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        $userPath = $this->projectRoot().'/src/Entity/User.php';
        $hasUser = is_file($userPath);
        if (!$input->getOption('with-user') && !$hasUser && $io->confirm('Also install demo User entity + UserRepository?', true)) {
            $input->setOption('with-user', true);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(Entity::class, 'doctrine/orm');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $force = (bool) $input->getOption('force');
        $withUser = (bool) $input->getOption('with-user');
        $installer = SkeletonInstaller::create();

        $result = $installer->installDemoEntities(
            $generator,
            $force,
            withUser: $withUser,
            withCatalog: true,
        );
        $generator->writeChanges();

        $this->writeSuccessMessage($io);
        $installer->writeResultSections($io, $result);

        $io->newLine();
        $io->text([
            'Next steps:',
            '  1. Create schema: <fg=yellow>php bin/console doctrine:schema:update --force</>',
            '     (or <fg=yellow>doctrine:migrations:diff</> / <fg=yellow>migrate</>)',
            '  2. Scaffold seeder: <fg=yellow>php bin/console make:tstdt-demo-seeder --with-http-reset'.($withUser ? ' --with-user' : '').'</>',
            '  3. Implement inserts in DemoSeeder (or copy the bundle demo seeder) and require <fg=yellow>fakerphp/faker</>',
            $withUser
                ? '  4. Wire security entity provider to <fg=yellow>'.$generator->getRootNamespace().'\\Entity\\User</> (username); demo login admin/admin'
                : '  4. Optional User: re-run with <fg=yellow>--with-user</>',
        ]);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command copies the demo Doctrine entities used by
the Catalog / Sales tables and <info>make:tstdt-demo-seeder</info>:

  • <info>Entity/Category</info>
  • <info>Entity/Product</info>
  • <info>Entity/Customer</info>
  • <info>Entity/Order</info> (table <info>shop_order</info>)
  • <info>Entity/OrderItem</info>
  • <info>EventSubscriber/Query/ProductStatusFilterSubscriber</info> (maps product status → active)
  • <info>EventSubscriber/Query/SalesStatusFilterSubscriber</info> (whitelists order status filter)
  • <info>EventSubscriber/Query/ProductCategoryJoinSubscriber</info> (example ApplyJoin via attribute {@code join:})
  • <info>EventSubscriber/Query/OrderCustomerSelectSubscriber</info> (example ApplySelect CONCAT)
  • Optional <info>Entity/User</info> + <info>Repository/UserRepository</info> (<info>--with-user</info>)

Namespace is rewritten to your app root (e.g. <info>App\Entity</info>).

  <info>php %command.full_name%</info>
  <info>php %command.full_name% --with-user --force</info>
HELP;
    }
}
