<?php

declare(strict_types=1);

namespace SymfonyTanstackTableShadcnUiToolkit\Maker;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

/**
 * Scaffolds a single CRUD-style table resource (controller + JSON + page with edit/delete buttons).
 */
final class MakeTanstackCrudResource extends AbstractMaker
{
    use MakerOverwriteTrait;

    public static function getCommandName(): string
    {
        return 'make:tstdt-crud-resource';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a single table resource with edit/delete row actions (controller + JSON + page)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Resource / entity name (e.g. <fg=yellow>Product</>)'
            )
            ->addOption(
                'controller',
                'c',
                InputOption::VALUE_REQUIRED,
                'Controller class name without suffix (default: same as resource)'
            )
            ->addOption(
                'no-doctrine',
                null,
                InputOption::VALUE_NONE,
                'Generate stub row data instead of Doctrine EntityManager queries'
            )
            ->addOption(
                'with-admin-layout',
                null,
                InputOption::VALUE_NONE,
                'Wrap the page template in <twig:tstdt_AdminLayout>'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing controller / templates'
            )
        ;

        $inputConfig->setArgumentAsNonInteractive('name');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (!$input->getArgument('name')) {
            $input->setArgument('name', $io->ask(
                'Resource / entity name',
                'Product',
                static fn (?string $value): string => $value ?: 'Product'
            ));
        }

        if (!$input->getOption('controller')) {
            $input->setOption('controller', (string) $input->getArgument('name'));
        }

        $filesystem = new Filesystem();
        $adminLayoutExists = $filesystem->exists($this->projectRoot().'/templates/components/tstdt_AdminLayout.html.twig');
        if (!$input->getOption('with-admin-layout') && $adminLayoutExists) {
            if ($io->confirm('Wrap page in AdminLayout?', true)) {
                $input->setOption('with-admin-layout', true);
            }
        } elseif (!$input->getOption('with-admin-layout') && !$adminLayoutExists) {
            if ($io->confirm('Install AdminLayout kit and wrap the page?', false)) {
                $input->setOption('with-admin-layout', true);
            }
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(AbstractController::class, 'symfony/framework-bundle');
        $dependencies->addClassDependency(TanstackDataTable::class, 'johnnymick/symfony-tanstack-table-shadcn-ui-toolkit');
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $force = (bool) $input->getOption('force');
        $withDoctrine = !$input->getOption('no-doctrine') && class_exists(EntityManagerInterface::class);
        $withAdminLayout = (bool) $input->getOption('with-admin-layout');

        if (!$input->getOption('no-doctrine') && !$withDoctrine) {
            $io->warning('Doctrine ORM not found — generating stub row data. Pass --no-doctrine to silence this.');
        }

        $installer = SkeletonInstaller::create();
        $kitResult = ['created' => [], 'updated' => [], 'skipped' => []];

        if ($withAdminLayout) {
            $filesystem = new Filesystem();
            if ($force || !$filesystem->exists($generator->getRootDirectory().'/templates/components/tstdt_AdminLayout.html.twig')) {
                $kitResult = $installer->installKits($generator, ['admin-layout'], $force);
            }
        }

        $resourceName = (string) $input->getArgument('name');
        $controllerRaw = (string) ($input->getOption('controller') ?: $resourceName);

        $controllerClassDetails = $generator->createClassNameDetails(
            Str::removeSuffix($controllerRaw, 'Controller'),
            'Controller\\',
            'Controller'
        );

        $baseName = Str::removeSuffix($controllerClassDetails->getShortName(), 'Controller');
        $routePrefix = '/'.str_replace('_', '-', Str::asSnakeCase($baseName));
        $routeNamePrefix = Str::asRouteName($baseName);
        $templateDir = Str::asSnakeCase($baseName);

        $resource = $this->buildResourceMeta(
            $baseName,
            $routeNamePrefix,
            $templateDir,
            $resourceName,
            $generator->getRootNamespace()
        );

        $makerDir = $installer->skeletonRoot().'/maker';
        $controllerFullName = $controllerClassDetails->getFullName();
        $controllerRelativePath = $this->classRelativePath($generator, $controllerFullName);
        $this->prepareOverwrite($generator, $controllerRelativePath, $force);

        $generator->generateClass(
            $controllerFullName,
            $makerDir.'/TanstackController.tpl.php',
            [
                'route_prefix' => $routePrefix,
                'with_doctrine' => $withDoctrine,
                'resources' => [$resource],
            ]
        );

        $templateTarget = 'templates/'.$resource['template'];
        $this->prepareOverwrite($generator, $templateTarget, $force);

        $generator->generateFile(
            $templateTarget,
            $makerDir.'/tanstack_resource.html.twig.tpl.php',
            [
                'page_title' => $resource['page_title'],
                'with_admin_layout' => $withAdminLayout,
            ]
        );

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $installer->writeResultSections($io, $kitResult);

        $io->text([
            'Created:',
            '  • '.$controllerRelativePath,
            '  • '.$templateTarget,
        ]);

        $io->newLine();
        $io->text([
            'Next steps:',
            '  1. Ensure UI: <fg=yellow>php bin/console make:tstdt-data-table --with-controllers --with-icons</>',
            $withAdminLayout
                ? '  2. Wire nav links in <fg=yellow>templates/components/tstdt_AdminLayout.html.twig</>'
                : '  2. Optional shell: <fg=yellow>php bin/console make:tstdt-admin-layout</>',
            '  3. Map entity fields in <fg=yellow>'.$controllerClassDetails->getShortName().'</>',
            '  4. Listen for row actions: <fg=yellow>tanstack-table:row-edit</> / <fg=yellow>tanstack-table:row-delete</>',
            '  5. Open <fg=yellow>'.$routePrefix.'/'.$resource['route_segment'].'</>',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function buildResourceMeta(
        string $controllerBaseName,
        string $routeNamePrefix,
        string $templateDir,
        string $resourceName,
        string $rootNamespace,
    ): array {
        $singular = Str::asClassName(trim($resourceName));
        $pluralCamel = Str::singularCamelCaseToPluralCamelCase($singular);
        $pluralSnake = Str::asSnakeCase($pluralCamel);
        $routeSegment = str_replace('_', '-', $pluralSnake);
        $singularLabel = strtolower(preg_replace('/(?<=\\w)([A-Z])/', ' $1', $singular) ?? $singular);

        return [
            'entity_short' => $singular,
            'entity_fqcn' => $rootNamespace.'\\Entity\\'.$singular,
            'method' => $pluralCamel,
            'json_method' => $pluralCamel.'Json',
            'table_method' => $pluralCamel.'Table',
            'rows_method' => Str::asLowerCamelCase($singular).'Rows',
            'route_segment' => $routeSegment,
            'route_name' => $routeNamePrefix.'_'.$pluralSnake,
            'route_name_json' => $routeNamePrefix.'_'.$pluralSnake.'_json',
            'template' => $templateDir.'/'.$pluralSnake.'.html.twig',
            'page_title' => ucfirst(str_replace('_', ' ', $pluralSnake)),
            'table_title' => trim($controllerBaseName.' '.str_replace('_', ' ', $pluralSnake)),
            'table_description' => \sprintf('%s table with edit / delete row actions.', ucfirst($singularLabel)),
            'empty_message' => \sprintf('No %s yet.', str_replace('_', ' ', $pluralSnake)),
            'singular_label' => $singularLabel,
        ];
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command scaffolds one CRUD-style table resource:

  • Controller with HTML + <info>/format/json</info> endpoints
  • Columns including <info>ColumnButton::edit()</info> / <info>delete()</info>
  • Optional <info>AdminLayout</info> page wrapper

  <info>php %command.full_name% Product</info>
  <info>php %command.full_name% Order -c Sales --with-admin-layout</info>
  <info>php %command.full_name% Item --no-doctrine</info>
HELP;
    }
}
