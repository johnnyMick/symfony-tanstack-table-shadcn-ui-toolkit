<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lightweight demo seeder scaffold.
 *
 * Replace {@see seed()} with your entity inserts (or copy patterns from the
 * bundle demo {@code DemoSeeder}). {@see reset()} clears listed tables then reseeds.
 */
final readonly class <?= $class_name; ?>
{
    /** @var list<string> */
    private const TABLES = [
<?php foreach ($tables as $table): ?>
        '<?= $table; ?>',
<?php endforeach; ?>
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
    ) {
    }

    /**
     * @return array<string, int> table name => row count
     */
    public function reset(): array
    {
        $this->truncateTables();

        return $this->seed();
    }

    private function truncateTables(): void
    {
        // Delete in reverse dependency order when FKs exist.
        foreach (array_reverse(self::TABLES) as $table) {
            try {
                $this->connection->executeStatement(\sprintf('DELETE FROM %s', $table));
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function seed(): array
    {
        // TODO: insert sample rows for your entities, then return counts.
        // Example:
        // $this->connection->insert('product', ['name' => 'Sample', 'sku' => 'SKU-1']);

        $counts = [];
        foreach (self::TABLES as $table) {
            try {
                $counts[$table] = (int) $this->connection->fetchOne(
                    \sprintf('SELECT COUNT(*) FROM %s', $table)
                );
            } catch (\Throwable) {
                $counts[$table] = 0;
            }
        }

        $this->em->clear();

        return $counts;
    }
}
