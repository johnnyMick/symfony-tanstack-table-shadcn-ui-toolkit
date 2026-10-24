<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class <?= $class_name; ?> extends AbstractController
{
    /**
     * DB table name => dashboard card meta.
     * Adjust routes after scaffolding your resource controllers.
     *
     * @var array<string, array{label: string, route: string, icon: string}>
     */
    private const CARDS = [
<?php foreach ($cards as $table => $card): ?>
        '<?= $table; ?>' => [
            'label' => '<?= $card['label']; ?>',
            'route' => '<?= $card['route']; ?>',
            'icon' => '<?= $card['icon']; ?>',
        ],
<?php endforeach; ?>
    ];

    #[Route('<?= $route_path; ?>', name: '<?= $route_name; ?>')]
    public function index(Connection $connection): Response
    {
        return $this->render('<?= $template; ?>', [
            'cards' => $this->buildCards($connection),
        ]);
    }

    /**
     * @return list<array{label: string, route: string, icon: string, count: int}>
     */
    private function buildCards(Connection $connection): array
    {
        $cards = [];

        foreach (self::CARDS as $table => $meta) {
            try {
                $count = (int) $connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', $table));
            } catch (\Throwable) {
                $count = 0;
            }

            $cards[] = [
                'label' => $meta['label'],
                'route' => $meta['route'],
                'icon' => $meta['icon'],
                'count' => $count,
            ];
        }

        return $cards;
    }
}
