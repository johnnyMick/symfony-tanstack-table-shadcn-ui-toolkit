<?php

declare(strict_types=1);

namespace App\Tests\Datatable;

use App\Datatable\DemoSeeder;
use App\Entity\Order;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DemoSeederTest extends KernelTestCase
{
    private DemoSeeder $seeder;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->seeder = $container->get(DemoSeeder::class);
        $this->connection = $container->get(Connection::class);
    }

    public function testResetSeedsExpectedRowCounts(): void
    {
        $counts = $this->seeder->reset();

        self::assertSame([
            'app_user' => 1,
            'category' => DemoSeeder::CATEGORIES,
            'product' => DemoSeeder::PRODUCTS,
            'customer' => DemoSeeder::CUSTOMERS,
            'shop_order' => DemoSeeder::ORDERS,
            'order_item' => $counts['order_item'],
        ], $counts);

        self::assertGreaterThan(DemoSeeder::ORDERS, $counts['order_item']);

        foreach ($counts as $table => $expected) {
            self::assertSame(
                $expected,
                (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table)),
                sprintf('Unexpected row count for table "%s"', $table),
            );
        }
    }

    public function testResetIsDeterministic(): void
    {
        $first = $this->seeder->reset();
        $firstSample = $this->sampleFingerprint();

        $second = $this->seeder->reset();
        $secondSample = $this->sampleFingerprint();

        self::assertSame($first, $second);
        self::assertSame($firstSample, $secondSample);
    }

    public function testResetProducesValidRelationsAndOrderStatuses(): void
    {
        $this->seeder->reset();

        self::assertSame(
            0,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM product p LEFT JOIN category c ON c.id = p.category_id WHERE c.id IS NULL',
            ),
        );
        self::assertSame(
            0,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM shop_order o LEFT JOIN customer c ON c.id = o.customer_id WHERE c.id IS NULL',
            ),
        );
        self::assertSame(
            0,
            (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM order_item i
                 LEFT JOIN shop_order o ON o.id = i.order_id
                 LEFT JOIN product p ON p.id = i.product_id
                 WHERE o.id IS NULL OR p.id IS NULL',
            ),
        );

        $statuses = $this->connection->fetchFirstColumn('SELECT DISTINCT status FROM shop_order ORDER BY status');
        sort($statuses);

        $expected = Order::STATUSES;
        sort($expected);

        self::assertSame($expected, $statuses);
    }

    /**
     * @return array{product: mixed, customer: mixed, order: mixed}
     */
    private function sampleFingerprint(): array
    {
        return [
            'product' => $this->connection->fetchAssociative(
                'SELECT id, category_id, name, sku, price, stock, active FROM product WHERE id = 1',
            ),
            'customer' => $this->connection->fetchAssociative(
                'SELECT id, first_name, last_name, email, country, city FROM customer WHERE id = 1',
            ),
            'order' => $this->connection->fetchAssociative(
                'SELECT id, customer_id, reference, status, total FROM shop_order WHERE id = 1',
            ),
        ];
    }
}
