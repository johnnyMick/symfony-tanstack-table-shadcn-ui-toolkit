<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Datatable\DemoSeeder;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CatalogSmokeTest extends WebTestCase
{
    public function testProductsPageRenders(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DemoSeeder::class)->reset();

        $client->request('GET', '/catalog/products');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="tanstack-table"]');
    }

    public function testProductsJsonEndpoint(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DemoSeeder::class)->reset();

        $client->request('GET', '/catalog/products/format/json', [
            'page' => 1,
            'pageSize' => 10,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('data', $payload);
        self::assertArrayHasKey('total', $payload);
        self::assertArrayHasKey('page', $payload);
        self::assertNotEmpty($payload['data']);
    }
}
