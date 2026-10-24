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
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;

/**
 * Scaffolds a Catalog/Sales-style controller with TanstackDataTable + /format/json endpoints.
 */
final class MakeTanstackDataTableController extends AbstractMaker
{
    use MakerOverwriteTrait;

    public static function getCommandName(): string
    {
        return 'make:tstdt-data-table-controller';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a TanstackDataTable controller (Catalog/Sales style) with JSON endpoints';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setHelp($this->getHelpText())
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                \sprintf('Controller name (e.g. <fg=yellow>%s</> or <fg=yellow>%sController</>)', 'Catalog', 'Sales')
            )
            ->addOption(
                'resource',
                'r',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Resource / entity name to expose as a table (e.g. Product, Category). Repeatable.'
            )
            ->addOption(
                'no-doctrine',
                null,
                InputOption::VALUE_NONE,
                'Generate stub row data instead of Doctrine EntityManager queries'
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
                'Controller name (without path)',
                'Catalog',
                static fn (?string $value): string => $value ?: 'Catalog'
            ));
        }

        if (!$input->getOption('resource')) {
            $default = $this->guessDefaultResource((string) $input->getArgument('name'));
            $answer = $io->ask(
                'Resource name(s), comma-separated (e.g. Product, Category)',
                $default
            );
            $resources = array_values(array_filter(array_map(
                static fn (string $part): string => trim($part),
                explode(',', (string) $answer)
            )));
            $input->setOption('resource', $resources ?: [$default]);
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

        if (!$input->getOption('no-doctrine') && !$withDoctrine) {
            $io->warning('Doctrine ORM not found — generating stub row data. Pass --no-doctrine to silence this.');
        }

        $rawName = (string) $input->getArgument('name');
        $controllerClassDetails = $generator->createClassNameDetails(
            Str::removeSuffix($rawName, 'Controller'),
            'Controller\\',
            'Controller'
        );

        $baseName = Str::removeSuffix($controllerClassDetails->getShortName(), 'Controller');
        $routePrefix = '/'.str_replace('_', '-', Str::asSnakeCase($baseName));
        $routeNamePrefix = Str::asRouteName($baseName);
        $templateDir = Str::asSnakeCase($baseName);

        /** @var list<string> $resourceNames */
        $resourceNames = $input->getOption('resource');
        $resources = [];
        foreach ($resourceNames as $resourceName) {
            $resources[] = $this->buildResourceMeta(
                $baseName,
                $routeNamePrefix,
                $templateDir,
                $resourceName,
                $generator->getRootNamespace()
            );
        }

        $makerDir = \dirname(__DIR__, 2).'/templates/skeleton/maker';
        $controllerFullName = $controllerClassDetails->getFullName();
        $controllerRelativePath = $this->classRelativePath($generator, $controllerFullName);
        $this->prepareOverwrite($generator, $controllerRelativePath, $force);

        $generator->generateClass(
            $controllerFullName,
            $makerDir.'/TanstackController.tpl.php',
            [
                'route_prefix' => $routePrefix,
                'with_doctrine' => $withDoctrine,
                'resources' => $resources,
            ]
        );

        $createdTemplates = [];
        foreach ($resources as $resource) {
            $templateTarget = 'templates/'.$resource['template'];
            if (!$force && is_file($generator->getRootDirectory().'/'.$templateTarget)) {
                $io->warning(\sprintf('Skipped existing template "%s" (use --force to overwrite).', $templateTarget));
                continue;
            }
            $this->prepareOverwrite($generator, $templateTarget, $force);

            $generator->generateFile(
                $templateTarget,
                $makerDir.'/tanstack_resource.html.twig.tpl.php',
                [
                    'page_title' => $resource['page_title'],
                    'with_admin_layout' => false,
                ]
            );
            $createdTemplates[] = $templateTarget;
        }

        $generator->writeChanges();
        $this->writeSuccessMessage($io);

        $io->text([
            'Created:',
            '  • '.$controllerRelativePath,
        ]);
        foreach ($createdTemplates as $template) {
            $io->text('  • '.$template);
        }

        $io->newLine();
        $first = $resources[0];
        $io->text([
            'Next steps:',
            '  1. Ensure UI was scaffolded: <fg=yellow>php bin/console make:tstdt-data-table --with-controllers --with-icons</>',
            '  2. Adjust columns / row mapping in <fg=yellow>'.$controllerClassDetails->getShortName().'</>',
            '  3. Open <fg=yellow>'.$routePrefix.'/'.$first['route_segment'].'</>',
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
            'table_description' => \sprintf('%s table powered by TanStack Data Table.', ucfirst($singularLabel)),
            'empty_message' => \sprintf('No %s yet.', str_replace('_', ' ', $pluralSnake)),
            'singular_label' => $singularLabel,
        ];
    }

    private function guessDefaultResource(string $controllerName): string
    {
        $base = Str::removeSuffix(Str::asClassName($controllerName), 'Controller');

        return match (strtolower($base)) {
            'catalog' => 'Product',
            'sales' => 'Order',
            'admin' => 'Item',
            default => 'Item',
        };
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates a controller similar to the demo
<info>CatalogController</info> / <info>SalesController</info>: HTML page + <info>/format/json</info>
AJAX endpoint configured with <info>TanstackDataTable</info>.

  <info>php %command.full_name% Catalog</info>
  <info>php %command.full_name% Sales -r Order -r Customer</info>
  <info>php %command.full_name% Inventory -r Product --no-doctrine</info>
HELP;
    }
}
