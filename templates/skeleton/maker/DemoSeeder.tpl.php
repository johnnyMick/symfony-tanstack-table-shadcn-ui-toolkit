<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace; ?>;

<?php if (!empty($with_user)): ?>
use <?= $user_fqcn; ?>;
<?php endif; ?>
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
<?php if (!empty($with_user)): ?>
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
<?php endif; ?>

/**
 * Lightweight demo seeder scaffold (synced with the bundle demo).
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

<?php if (!empty($with_user)): ?>
    /** @var list<class-string> */
    private const ENTITY_CLASSES = [
        <?= $user_short; ?>::class,
<?php foreach ($entity_fqcns as $fqcn): ?>
        <?= var_export($fqcn, true); ?>,
<?php endforeach; ?>
    ];

    private const ADMIN_USERNAME = 'admin';
    private const ADMIN_PASSWORD = 'admin';

<?php endif; ?>
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
<?php if (!empty($with_user)): ?>
        private UserPasswordHasherInterface $passwordHasher,
<?php endif; ?>
    ) {
    }

    /**
     * @return array<string, int> table name => row count
     */
    public function reset(): array
    {
<?php if (!empty($with_user)): ?>
        $this->rebuildSchema();
<?php else: ?>
        $this->truncateTables();
<?php endif; ?>

        return $this->seed();
    }

<?php if (!empty($with_user)): ?>
    private function rebuildSchema(): void
    {
        $metadata = [];
        foreach (self::ENTITY_CLASSES as $class) {
            $metadata[] = $this->em->getClassMetadata($class);
        }

        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

<?php else: ?>
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

<?php endif; ?>
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
<?php if (!empty($with_user)): ?>
            if ('app_user' === $table) {
                continue;
            }
<?php endif; ?>
            try {
                $counts[$table] = (int) $this->connection->fetchOne(
                    \sprintf('SELECT COUNT(*) FROM %s', $table)
                );
            } catch (\Throwable) {
                $counts[$table] = 0;
            }
        }

<?php if (!empty($with_user)): ?>
        $this->seedAdminUser();
        $counts = ['app_user' => 1] + $counts;
<?php else: ?>
        $this->em->clear();
<?php endif; ?>

        return $counts;
    }
<?php if (!empty($with_user)): ?>

    private function seedAdminUser(): void
    {
        $user = (new <?= $user_short; ?>())
            ->setUsername(self::ADMIN_USERNAME)
            ->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::ADMIN_PASSWORD));

        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();
    }
<?php endif; ?>
}
