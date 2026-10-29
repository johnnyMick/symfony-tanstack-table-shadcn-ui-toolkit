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
                'Wrap the page in AdminLayout with workspace edit tabs'
            )
            ->addOption(
                'with-controller-target',
                null,
                InputOption::VALUE_NONE,
                'Pass controller: self::class into queryContext for AsTanStackDatatableField matching'
            )
            ->addOption(
                'with-action-target',
                null,
                InputOption::VALUE_NONE,
                'Pass action: __FUNCTION__ into queryContext for AsTanStackDatatableField matching'
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
        $adminLayoutExists = $filesystem->exists($this->projectRoot().'/templates/components/tstdt/AdminLayout.html.twig');
        if (!$input->getOption('with-admin-layout') && $adminLayoutExists) {
            if ($io->confirm('Wrap page in AdminLayout with workspace tabs?', true)) {
                $input->setOption('with-admin-layout', true);
            }
        } elseif (!$input->getOption('with-admin-layout') && !$adminLayoutExists) {
            if ($io->confirm('Install AdminLayout kit (workspace tabs) and wrap the page?', false)) {
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
        $withControllerTarget = (bool) $input->getOption('with-controller-target');
        $withActionTarget = (bool) $input->getOption('with-action-target');

        if (!$input->getOption('no-doctrine') && !$withDoctrine) {
            $io->warning('Doctrine ORM not found — generating stub row data. Pass --no-doctrine to silence this.');
        }

        $installer = SkeletonInstaller::create();
        $kitResult = ['created' => [], 'updated' => [], 'skipped' => []];

        if ($withAdminLayout) {
            $filesystem = new Filesystem();
            if ($force || !$filesystem->exists($generator->getRootDirectory().'/templates/components/tstdt/AdminLayout.html.twig')) {
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
                'with_admin_layout' => $withAdminLayout,
                'with_controller_target' => $withControllerTarget,
                'with_action_target' => $withActionTarget,
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
                'edit_route_name' => $resource['route_name_edit'],
            ]
        );

        $newPartialTarget = 'templates/'.$resource['new_partial_template'];
        $this->prepareOverwrite($generator, $newPartialTarget, $force);
        $generator->generateFile(
            $newPartialTarget,
            $makerDir.'/tanstack_resource_new_partial.html.twig.tpl.php',
            [
                'singular_label' => $resource['singular_label'],
                'new_route_name' => $resource['route_name_new'],
                'csrf_token_id' => $resource['csrf_new_id'],
            ]
        );

        $created = [
            '  • '.$controllerRelativePath,
            '  • '.$templateTarget,
            '  • '.$newPartialTarget,
        ];

        if ($withAdminLayout) {
            $listPartialTarget = 'templates/'.$resource['list_partial_template'];
            $this->prepareOverwrite($generator, $listPartialTarget, $force);
            $generator->generateFile(
                $listPartialTarget,
                $makerDir.'/tanstack_resource_list_partial.html.twig.tpl.php',
                []
            );

            $editPartialTarget = 'templates/'.$resource['edit_partial_template'];
            $editPageTarget = 'templates/'.$resource['edit_template'];

            $this->prepareOverwrite($generator, $editPartialTarget, $force);
            $generator->generateFile(
                $editPartialTarget,
                $makerDir.'/tanstack_resource_edit_partial.html.twig.tpl.php',
                [
                    'singular_label' => $resource['singular_label'],
                    'edit_route_name' => $resource['route_name_edit'],
                    'csrf_id_prefix' => $resource['csrf_id_prefix'],
                    'with_doctrine' => $withDoctrine,
                ]
            );

            $this->prepareOverwrite($generator, $editPageTarget, $force);
            $generator->generateFile(
                $editPageTarget,
                $makerDir.'/tanstack_resource_edit.html.twig.tpl.php',
                [
                    'page_title' => 'Edit '.$resource['singular_label'],
                    'edit_route_name' => $resource['route_name_edit'],
                    'edit_partial_template' => $resource['edit_partial_template'],
                ]
            );

            $created[] = '  • '.$listPartialTarget;
            $created[] = '  • '.$editPartialTarget;
            $created[] = '  • '.$editPageTarget;
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $installer->writeResultSections($io, $kitResult);

        $io->text(array_merge(['Created:'], $created));

        $io->newLine();
        $nextSteps = [
            'Next steps:',
            '  1. Ensure UI: <fg=yellow>php bin/console make:tstdt-data-table --with-controllers --with-icons</>',
        ];
        if ($withAdminLayout) {
            $nextSteps[] = '  2. Customize <fg=yellow>adminLayout()</> in <fg=yellow>'.$controllerClassDetails->getShortName().'</> (brand / nav / user)';
            $nextSteps[] = '  3. Map entity fields in the controller + edit partial';
            $nextSteps[] = '  4. Delete uses <fg=yellow>DeleteButton</> (confirm + POST); tabs open via <fg=yellow>WorkspaceTabButton</>';
            $nextSteps[] = '  5. Open <fg=yellow>'.$routePrefix.'/'.$resource['route_segment'].'</>';
        } else {
            $nextSteps[] = '  2. Optional shell + tabs: <fg=yellow>php bin/console make:tstdt-admin-layout</> then re-run with <fg=yellow>--with-admin-layout</>';
            $nextSteps[] = '  3. Map entity fields in <fg=yellow>'.$controllerClassDetails->getShortName().'</>';
            $nextSteps[] = '  4. Delete uses <fg=yellow>DeleteButton</> (confirm + POST); listen for <fg=yellow>tanstack-table:row-edit</> if needed';
            $nextSteps[] = '  5. Open <fg=yellow>'.$routePrefix.'/'.$resource['route_segment'].'</>';
        }
        $io->text($nextSteps);
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
        $singularSnake = Str::asSnakeCase($singular);

        return [
            'entity_short' => $singular,
            'entity_fqcn' => $rootNamespace.'\\Entity\\'.$singular,
            'method' => $pluralCamel,
            'json_method' => $pluralCamel.'Json',
            'edit_method' => Str::asLowerCamelCase($singular).'Edit',
            'delete_method' => Str::asLowerCamelCase($singular).'Delete',
            'new_method' => Str::asLowerCamelCase($singular).'New',
            'table_method' => $pluralCamel.'Table',
            'rows_method' => Str::asLowerCamelCase($singular).'Rows',
            'route_segment' => $routeSegment,
            'route_name' => $routeNamePrefix.'_'.$pluralSnake,
            'route_name_json' => $routeNamePrefix.'_'.$pluralSnake.'_json',
            'route_name_edit' => $routeNamePrefix.'_'.$pluralSnake.'_edit',
            'route_name_delete' => $routeNamePrefix.'_'.$pluralSnake.'_delete',
            'route_name_new' => $routeNamePrefix.'_'.$pluralSnake.'_new',
            'template' => $templateDir.'/'.$pluralSnake.'.html.twig',
            'list_partial_template' => $templateDir.'/_'.$pluralSnake.'.html.twig',
            'edit_template' => $templateDir.'/'.$singularSnake.'_edit.html.twig',
            'edit_partial_template' => $templateDir.'/_'.$singularSnake.'_edit.html.twig',
            'new_partial_template' => $templateDir.'/_'.$singularSnake.'_new.html.twig',
            'csrf_id_prefix' => $singularSnake.'_edit_',
            'csrf_new_id' => $singularSnake.'_new',
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
  • Doctrine filters via <info>DatatableQueryApplier</info> (<info>field_map</info> + whitelist)
  • Columns with edit / <info>DeleteButton</info> (confirm + POST) row actions
  • Optional <info>AdminLayout</info> with workspace edit tabs (<info>WorkspaceTabButton</info>,
    edit route, tab partial + full-page edit templates)

  <info>php %command.full_name% Product</info>
  <info>php %command.full_name% Order -c Sales --with-admin-layout</info>
  <info>php %command.full_name% Item --no-doctrine</info>
  <info>php %command.full_name% Product --with-controller-target --with-action-target</info>
HELP;
    }
}
