<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Datatable\DemoSeeder;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Controller\Concerns\InteractsWithWorkspaceTables;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SalesAuthTest extends WebTestCase
{
    use InteractsWithWorkspaceTables;

    public function testAnonymousSalesRedirectsToLogin(): void
    {
        $client = static::createClient();

        foreach (['/sales/orders', '/sales/customers', '/sales/order-items'] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('/login');
        }
    }

    public function testAnonymousSalesJsonRedirectsToLogin(): void
    {
        $client = static::createClient();

        foreach ([
            '/sales/orders/format/json',
            '/sales/customers/format/json',
            '/sales/order-items/format/json',
        ] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('/login');
        }
    }

    public function testAuthenticatedUserCanOpenSalesTables(): void
    {
        $client = $this->authenticatedClient();

        $cases = [
            '/sales/orders' => 'sales-orders',
            '/sales/customers' => 'sales-customers',
            '/sales/order-items' => 'sales-order-items',
        ];

        foreach ($cases as $path => $tableId) {
            $config = $this->assertWorkspaceShellAndTableConfig($client, $path);
            self::assertSame($tableId, $config['id']);
            self::assertSame([], $config['bulkActions'] ?? []);
        }
    }

    public function testAuthenticatedSalesJsonEndpoints(): void
    {
        $client = $this->authenticatedClient();

        foreach ([
            '/sales/orders/format/json',
            '/sales/customers/format/json',
            '/sales/order-items/format/json',
        ] as $path) {
            $client->request('GET', $path, [
                'page' => 1,
                'pageSize' => 10,
            ]);

            self::assertResponseIsSuccessful();
            $payload = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, \JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertArrayHasKey('data', $payload);
            self::assertArrayHasKey('total', $payload);
            self::assertNotEmpty($payload['data']);
        }
    }

    public function testFormLoginWithDatabaseUser(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DemoSeeder::class)->reset();

        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $client->submitForm('Sign in', [
            '_username' => 'admin',
            '_password' => 'admin',
        ]);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testLoginPageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form input[name="_username"]');
        self::assertSelectorExists('form input[name="_password"]');
    }

    private function authenticatedClient(): KernelBrowser
    {
        $client = static::createClient();
        static::getContainer()->get(DemoSeeder::class)->reset();
        $this->loginAsAdmin($client);

        return $client;
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        /** @var User $user */
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['username' => 'admin']);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user);
    }
}
