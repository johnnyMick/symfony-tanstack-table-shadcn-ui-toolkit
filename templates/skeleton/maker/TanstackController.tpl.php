<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

<?php if ($with_doctrine): ?>
<?php foreach ($resources as $resource): ?>
use <?= $resource['entity_fqcn']; ?>;
<?php endforeach; ?>
use Doctrine\ORM\EntityManagerInterface;
<?php endif; ?>
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
<?php if (!empty($with_admin_layout)): ?>
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminBrand;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminLayout;
use SymfonyTanstackTableShadcnUiToolkit\Layout\AdminUser;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavGroup;
use SymfonyTanstackTableShadcnUiToolkit\Layout\NavLink;
<?php endif; ?>
<?php if ($with_doctrine): ?>
use SymfonyTanstackTableShadcnUiToolkit\Service\DatatableQueryApplier;
<?php endif; ?>
use SymfonyTanstackTableShadcnUiToolkit\Table\AddButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\BulkAction;
use SymfonyTanstackTableShadcnUiToolkit\Table\Column;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnBadge;
use SymfonyTanstackTableShadcnUiToolkit\Table\ColumnButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\DeleteButton;
use SymfonyTanstackTableShadcnUiToolkit\Table\HeaderAction;
use SymfonyTanstackTableShadcnUiToolkit\Table\TableQuery;
use SymfonyTanstackTableShadcnUiToolkit\Table\TanstackDataTable;
<?php if (!empty($with_admin_layout)): ?>
use SymfonyTanstackTableShadcnUiToolkit\Table\WorkspaceTabButton;
<?php endif; ?>

#[Route('<?= $route_prefix; ?>')]
final class <?= $class_name; ?> extends AbstractController
{
<?php if (!empty($with_admin_layout)): ?>
    /** Reusable edit placeholder tabs in the workspace (main list tab is separate). */
    private const WORKSPACE_TAB_COUNT = 5;

<?php endif; ?>
<?php if ($with_doctrine): ?>
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly DatatableQueryApplier $queryApplier,
    ) {
    }

<?php else: ?>
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

<?php endif; ?>
<?php foreach ($resources as $resource): ?>
    #[Route('/<?= $resource['route_segment']; ?>', name: '<?= $resource['route_name']; ?>')]
    public function <?= $resource['method']; ?>(Request $request): Response
    {
        $table = $this-><?= $resource['table_method']; ?>();
<?php if (!empty($with_admin_layout)): ?>
        if ($this->isWorkspaceTabRequest($request)) {
            return $this->render('<?= $resource['list_partial_template']; ?>', [
                'table' => $table->toArray(),
            ]);
        }

        $layout = $this->adminLayout()
            ->mainTabLabel('<?= $resource['page_title']; ?>')
            ->tabCount(self::WORKSPACE_TAB_COUNT);

        return $this->render('<?= $resource['template']; ?>', [
            'layout' => $layout->toArray(),
        ]);
<?php else: ?>

        return $this->render('<?= $resource['template']; ?>', [
            'table' => $table->toArray(),
        ]);
<?php endif; ?>
    }

    #[Route('/<?= $resource['route_segment']; ?>/format/json', name: '<?= $resource['route_name_json']; ?>', methods: ['GET'])]
    public function <?= $resource['json_method']; ?>(Request $request): JsonResponse
    {
        $table = $this-><?= $resource['table_method']; ?>();
        $query = TableQuery::fromRequest($request, $table->getPageSize());
        [$rows, $total] = $this-><?= $resource['rows_method']; ?>($query);

        return $table->json($rows, $total, query: $query);
    }

<?php if (!empty($with_admin_layout)): ?>
    #[Route('/<?= $resource['route_segment']; ?>/{id}/edit', name: '<?= $resource['route_name_edit']; ?>', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
<?php if ($with_doctrine): ?>
    public function <?= $resource['edit_method']; ?>(Request $request, <?= $resource['entity_short']; ?> $item): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('<?= $resource['csrf_id_prefix']; ?>'.$item->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            if (method_exists($item, 'setName')) {
                $item->setName(trim((string) $request->request->get('name')));
            }
            // TODO: map remaining form fields onto the entity

            $this->em->flush();
            $this->addFlash('success', sprintf('<?= ucfirst($resource['singular_label']); ?> saved.'));

            if ($this->isWorkspaceTabRequest($request)) {
                return $this->render('<?= $resource['edit_partial_template']; ?>', [
                    'item' => $item,
                ]);
            }

            return $this->redirectToRoute('<?= $resource['route_name_edit']; ?>', ['id' => $item->getId()]);
        }

        if ($this->isWorkspaceTabRequest($request)) {
            return $this->render('<?= $resource['edit_partial_template']; ?>', [
                'item' => $item,
            ]);
        }

        $layout = $this->adminLayout()
            ->mainTabLabel('<?= $resource['page_title']; ?>')
            ->tabCount(self::WORKSPACE_TAB_COUNT);

        return $this->render('<?= $resource['edit_template']; ?>', [
            'layout' => $layout->toArray(),
            'item' => $item,
        ]);
    }
<?php else: ?>
    public function <?= $resource['edit_method']; ?>(Request $request, int $id): Response
    {
        $item = [
            'id' => $id,
            'name' => 'Sample <?= $resource['singular_label']; ?> #'.$id,
            'status' => 'active',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('<?= $resource['csrf_id_prefix']; ?>'.$id, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $item['name'] = trim((string) $request->request->get('name', $item['name']));
            $this->addFlash('success', sprintf('<?= ucfirst($resource['singular_label']); ?> saved (stub — wire persistence).'));

            if ($this->isWorkspaceTabRequest($request)) {
                return $this->render('<?= $resource['edit_partial_template']; ?>', [
                    'item' => $item,
                ]);
            }

            return $this->redirectToRoute('<?= $resource['route_name_edit']; ?>', ['id' => $id]);
        }

        if ($this->isWorkspaceTabRequest($request)) {
            return $this->render('<?= $resource['edit_partial_template']; ?>', [
                'item' => $item,
            ]);
        }

        $layout = $this->adminLayout()
            ->mainTabLabel('<?= $resource['page_title']; ?>')
            ->tabCount(self::WORKSPACE_TAB_COUNT);

        return $this->render('<?= $resource['edit_template']; ?>', [
            'layout' => $layout->toArray(),
            'item' => $item,
        ]);
    }
<?php endif; ?>

<?php endif; ?>
    #[Route('/<?= $resource['route_segment']; ?>/new', name: '<?= $resource['route_name_new']; ?>', methods: ['GET', 'POST'])]
<?php if ($with_doctrine): ?>
    public function <?= $resource['new_method']; ?>(Request $request): Response
    {
        $values = ['name' => ''];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('<?= $resource['csrf_new_id']; ?>', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $values['name'] = trim((string) $request->request->get('name'));
            $item = new <?= $resource['entity_short']; ?>();
            if (method_exists($item, 'setName')) {
                $item->setName($values['name']);
            }
            // TODO: map remaining form fields onto the entity

            $this->em->persist($item);
            $this->em->flush();
            $this->addFlash('success', sprintf('<?= ucfirst($resource['singular_label']); ?> created.'));

            return $this->redirectToRoute('<?= $resource['route_name']; ?>');
        }

        return $this->render('<?= $resource['new_partial_template']; ?>', [
            'values' => $values,
        ]);
    }
<?php else: ?>
    public function <?= $resource['new_method']; ?>(Request $request): Response
    {
        $values = ['name' => ''];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('<?= $resource['csrf_new_id']; ?>', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $this->addFlash('success', '<?= ucfirst($resource['singular_label']); ?> created (stub — wire persistence).');

            return $this->redirectToRoute('<?= $resource['route_name']; ?>');
        }

        return $this->render('<?= $resource['new_partial_template']; ?>', [
            'values' => $values,
        ]);
    }
<?php endif; ?>

    #[Route('/<?= $resource['route_segment']; ?>/bulk-delete', name: '<?= $resource['route_name']; ?>_bulk_delete', methods: ['POST'])]
    public function <?= $resource['delete_method']; ?>Bulk(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('bulk_delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $ids = array_values(array_filter(
            array_map('intval', (array) $request->request->all('ids')),
            static fn (int $id): bool => $id > 0,
        ));

        return new JsonResponse(['ok' => true, 'action' => 'delete', 'ids' => $ids]);
    }

    #[Route('/<?= $resource['route_segment']; ?>/{id}/delete', name: '<?= $resource['route_name_delete']; ?>', requirements: ['id' => '\d+'], methods: ['POST'])]
<?php if ($with_doctrine): ?>
    public function <?= $resource['delete_method']; ?>(Request $request, <?= $resource['entity_short']; ?> $item): Response
    {
        if (!$this->isCsrfTokenValid('delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $label = method_exists($item, 'getName') ? (string) $item->getName() : '#'.$item->getId();
        $this->em->remove($item);
        $this->em->flush();
        $this->addFlash('success', sprintf('<?= ucfirst($resource['singular_label']); ?> “%s” deleted.', $label));

        return $this->redirectToRoute('<?= $resource['route_name']; ?>');
    }
<?php else: ?>
    public function <?= $resource['delete_method']; ?>(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->addFlash('success', sprintf('<?= ucfirst($resource['singular_label']); ?> #%d deleted (stub — wire persistence).', $id));

        return $this->redirectToRoute('<?= $resource['route_name']; ?>');
    }
<?php endif; ?>

<?php endforeach; ?>
<?php if (!empty($with_admin_layout)): ?>
    private function isWorkspaceTabRequest(Request $request): bool
    {
        return '1' === $request->headers->get('X-Workspace-Tab')
            || '1' === $request->request->get('_workspace_tab')
            || '1' === $request->query->get('_workspace_tab')
            || $request->isXmlHttpRequest();
    }

    /**
     * Shared admin shell — customize brand / nav / user here (or extract a trait).
     */
    private function adminLayout(): AdminLayout
    {
        return AdminLayout::create()
            ->brand(
                AdminBrand::create()
                    ->title('App')
                    ->subtitle('Admin console')
                    ->href('#')
            )
            ->nav([
                NavGroup::make('Resources')->items([
<?php foreach ($resources as $resource): ?>
                    NavLink::make('<?= $resource['page_title']; ?>')
                        ->href($this->generateUrl('<?= $resource['route_name']; ?>'))
                        ->icon('lucide:package')
                        ->routes(['<?= $resource['route_name']; ?>', '<?= $resource['route_name_edit']; ?>']),
<?php endforeach; ?>
                ]),
            ])
            ->user(AdminUser::create()->name('Admin'));
    }

<?php endif; ?>
<?php foreach ($resources as $resource): ?>
    private function <?= $resource['table_method']; ?>(): TanstackDataTable
    {
        return TanstackDataTable::create('<?= $resource['route_name']; ?>')
            ->title('<?= $resource['table_title']; ?>')
            ->description('<?= $resource['table_description']; ?>')
            ->url($this->generateUrl('<?= $resource['route_name_json']; ?>'))
            ->pageSize(10)
            ->emptyMessage('<?= $resource['empty_message']; ?>')
<?php if (!empty($with_admin_layout)): ?>
            ->tabTitle('{name}')
            ->opensTab()
<?php endif; ?>
            ->headerActions([
                HeaderAction::downloadCsv(),
                AddButton::create()
                    ->url($this->generateUrl('<?= $resource['route_name_new']; ?>'))
                    ->title('Add <?= $resource['singular_label']; ?>'),
            ])
            ->bulkActions([
                BulkAction::delete()
                    ->url($this->generateUrl('<?= $resource['route_name']; ?>_bulk_delete'))
                    ->csrfToken($this->csrfTokenManager->getToken('bulk_delete')->getValue()),
            ])
            ->columns([
<?php if (!empty($with_admin_layout)): ?>
                WorkspaceTabButton::create()
                    ->url(str_replace('/0/', '/{id}/', $this->generateUrl('<?= $resource['route_name_edit']; ?>', ['id' => 0]))),
<?php else: ?>
                Column::make('edit')->buttons([ColumnButton::edit()]),
<?php endif; ?>
                Column::make('id')->label('ID')->field('e.id')->class('font-mono text-xs'),
                Column::make('name')->label('Name')->field('e.name')->class('font-medium'),
                Column::make('status')->label('Status')->field('e.status')->badge([
                    ColumnBadge::make('active')->variant('default')->label('Active'),
                    ColumnBadge::make('inactive')->variant('secondary')->label('Inactive'),
                ]),
                DeleteButton::create()
                    ->url(str_replace('/0/', '/{id}/', $this->generateUrl('<?= $resource['route_name_delete']; ?>', ['id' => 0])))
                    ->confirm('Delete “{name}”?')
                    ->csrfToken($this->csrfTokenManager->getToken('delete')->getValue()),
            ]);
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function <?= $resource['rows_method']; ?>(TableQuery $query): array
    {
<?php if ($with_doctrine): ?>
        $table = $this-><?= $resource['table_method']; ?>();
<?php if (!empty($with_controller_target) && !empty($with_action_target)): ?>
        $context = $table->queryContext(
            <?= $resource['entity_short']; ?>::class,
            controller: self::class,
            action: __FUNCTION__,
        );
<?php elseif (!empty($with_controller_target)): ?>
        $context = $table->queryContext(
            <?= $resource['entity_short']; ?>::class,
            controller: self::class,
        );
<?php elseif (!empty($with_action_target)): ?>
        $context = $table->queryContext(
            <?= $resource['entity_short']; ?>::class,
            action: __FUNCTION__,
        );
<?php else: ?>
        $context = $table->queryContext(<?= $resource['entity_short']; ?>::class);
<?php endif; ?>


        return $this->queryApplier->fetchPage(
            fn () => $this->em->createQueryBuilder()->from(<?= $resource['entity_short']; ?>::class, 'e'),
            $query,
            $context,
            'e.id',
        );
<?php else: ?>
        // TODO: replace with your data source (Doctrine, API, etc.)
        $allRows = [
            ['id' => 1, 'name' => 'Sample <?= $resource['singular_label']; ?>', 'status' => 'active'],
            ['id' => 2, 'name' => 'Another <?= $resource['singular_label']; ?>', 'status' => 'inactive'],
        ];

        return $query->paginateRows($allRows);
<?php endif; ?>
    }

<?php endforeach; ?>
}

