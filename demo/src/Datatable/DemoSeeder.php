<?php

declare(strict_types=1);

namespace App\Datatable;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Rebuilds the demo database from scratch with deterministic data.
 *
 * Rows are written through DBAL in one transaction rather than through the ORM: ten thousand
 * orders load in well under a second, which keeps the "Reset data" button usable.
 */
final readonly class DemoSeeder
{
    public const CATEGORIES = 8;
    public const PRODUCTS = 60;
    public const CUSTOMERS = 500;
    public const ORDERS = 10_000;

    private const FAKER_SEED = 42;
    private const ORDER_BATCH = 500;
    private const NOW = '2026-01-15 12:00:00';

    private const CATEGORY_NAMES = [
        'Audio',
        'Cameras',
        'Computers',
        'Gaming',
        'Home Office',
        'Mobile',
        'Wearables',
        'Accessories',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @return array<string, int> table name => row count
     */
    public function reset(): array
    {
        $this->rebuildSchema();

        return $this->seed();
    }

    private function rebuildSchema(): void
    {
        $metadata = [
            $this->em->getClassMetadata(User::class),
            $this->em->getClassMetadata(Category::class),
            $this->em->getClassMetadata(Product::class),
            $this->em->getClassMetadata(Customer::class),
            $this->em->getClassMetadata(Order::class),
            $this->em->getClassMetadata(OrderItem::class),
        ];

        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    /**
     * @return array<string, int> table name => row count
     */
    private function seed(): array
    {
        $faker = Factory::create('en_US');
        $faker->seed(self::FAKER_SEED);

        // Build every row in memory first so Doctrine/DBAL cannot consume mt_rand() mid-sequence.
        $categories = $this->buildCategories();
        $products = $this->buildProducts($faker);
        $customers = $this->buildCustomers($faker);
        [$orders, $items] = $this->buildOrdersAndItems($faker, $products);

        $this->connection->beginTransaction();

        try {
            foreach ($categories as $row) {
                $this->connection->insert('category', $row);
            }
            foreach ($products as $row) {
                $this->connection->insert('product', $row);
            }
            foreach ($customers as $row) {
                $this->connection->insert('customer', $row);
            }
            $this->insertBatches('shop_order', ['id', 'customer_id', 'reference', 'status', 'total', 'ordered_at'], $orders);
            $this->insertBatches('order_item', ['id', 'order_id', 'product_id', 'quantity', 'unit_price'], $items);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        $this->seedAdminUser();

        return [
            'app_user' => 1,
            'category' => self::CATEGORIES,
            'product' => self::PRODUCTS,
            'customer' => self::CUSTOMERS,
            'shop_order' => self::ORDERS,
            'order_item' => count($items),
        ];
    }

    private function seedAdminUser(): void
    {
        $user = (new User())
            ->setUsername('admin')
            ->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'admin'));

        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function buildCategories(): array
    {
        $rows = [];

        foreach (self::CATEGORY_NAMES as $i => $name) {
            $rows[] = [
                'id' => $i + 1,
                'name' => $name,
                'slug' => strtolower(str_replace(' ', '-', $name)),
                'description' => sprintf('%s products for the demo catalog.', $name),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function buildProducts(Generator $faker): array
    {
        $rows = [];

        for ($i = 1; $i <= self::PRODUCTS; ++$i) {
            $rows[] = [
                'id' => $i,
                'category_id' => (($i - 1) % self::CATEGORIES) + 1,
                'name' => ucfirst($faker->unique()->words(3, true)),
                'sku' => sprintf('SKU-%04d', $i),
                'price' => number_format($faker->randomFloat(2, 9.99, 899.99), 2, '.', ''),
                'stock' => $faker->numberBetween(0, 500),
                'active' => $faker->boolean(90) ? 1 : 0,
                'created_at' => $faker->dateTimeBetween('-2 years', self::NOW, 'UTC')->format('Y-m-d H:i:s'),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function buildCustomers(Generator $faker): array
    {
        $rows = [];

        for ($i = 1; $i <= self::CUSTOMERS; ++$i) {
            $rows[] = [
                'id' => $i,
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'email' => sprintf('customer.%04d@example.test', $i),
                'country' => $faker->countryCode(),
                'city' => $faker->city(),
                'created_at' => $faker->dateTimeBetween('-3 years', self::NOW, 'UTC')->format('Y-m-d H:i:s'),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, int|string>> $products
     *
     * @return array{0: list<array<string, int|string>>, 1: list<array<string, int|string>>}
     */
    private function buildOrdersAndItems(Generator $faker, array $products): array
    {
        $prices = [];
        foreach ($products as $product) {
            $prices[(int) $product['id']] = (string) $product['price'];
        }
        $productIds = array_keys($prices);
        $statuses = Order::STATUSES;

        $orders = [];
        $items = [];
        $itemId = 0;

        for ($orderId = 1; $orderId <= self::ORDERS; ++$orderId) {
            $lineCount = $faker->numberBetween(1, 4);
            $lineProductIds = $faker->randomElements($productIds, $lineCount);
            $total = 0.0;

            foreach ($lineProductIds as $productId) {
                $quantity = $faker->numberBetween(1, 5);
                $unitPrice = $prices[$productId];
                $total += (float) $unitPrice * $quantity;

                $items[] = [
                    'id' => ++$itemId,
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ];
            }

            $orders[] = [
                'id' => $orderId,
                'customer_id' => $faker->numberBetween(1, self::CUSTOMERS),
                'reference' => sprintf('ORD-%06d', $orderId),
                'status' => $statuses[$faker->numberBetween(0, count($statuses) - 1)],
                'total' => number_format($total, 2, '.', ''),
                'ordered_at' => $faker->dateTimeBetween('-18 months', self::NOW, 'UTC')->format('Y-m-d H:i:s'),
            ];
        }

        return [$orders, $items];
    }

    /**
     * @param list<string>                    $columns
     * @param list<array<string, int|string>> $rows
     */
    private function insertBatches(string $table, array $columns, array $rows): void
    {
        foreach (array_chunk($rows, self::ORDER_BATCH) as $chunk) {
            $placeholders = [];
            $values = [];

            foreach ($chunk as $row) {
                $placeholders[] = '('.implode(', ', array_fill(0, count($columns), '?')).')';
                foreach ($columns as $column) {
                    $values[] = $row[$column];
                }
            }

            $this->connection->executeStatement(
                sprintf(
                    'INSERT INTO %s (%s) VALUES %s',
                    $table,
                    implode(', ', $columns),
                    implode(', ', $placeholders),
                ),
                $values,
            );
        }
    }
}
