<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Datatable\DemoSeeder;
use App\Tests\Controller\Concerns\InteractsWithWorkspaceTables;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocaleSmokeTest extends WebTestCase
{
    use InteractsWithWorkspaceTables;

    public function testLocaleSwitchRedirectsAndPersists(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DemoSeeder::class)->reset();

        $client->request('GET', '/catalog/products');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller*="tstdt--workspace-tabs"]');

        $client->request('GET', '/_locale/fr', server: [
            'HTTP_REFERER' => 'http://localhost/catalog/products',
        ]);
        self::assertResponseRedirects('/catalog/products');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $crawler = $this->requestWorkspaceTab($client, '/catalog/products');
        self::assertResponseIsSuccessful();
        $config = $this->tableConfig($crawler);
        self::assertSame('catalog-products', $config['id']);
        // French message catalog for table title
        self::assertSame('Produits du catalogue', $config['title']);
    }

    public function testUnsupportedLocaleIsNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_locale/es');

        self::assertResponseStatusCodeSame(404);
    }
}
