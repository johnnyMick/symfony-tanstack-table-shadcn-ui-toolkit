<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Datatable\DemoSeeder;
use App\Entity\Category;
use App\Entity\Product;
use App\Tests\Controller\Concerns\InteractsWithWorkspaceTables;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CatalogSmokeTest extends WebTestCase
{
    use InteractsWithWorkspaceTables;

    public function testProductsPageRendersWithTableIdAndBulkActions(): void
    {
        $client = $this->bootedClient();
        $config = $this->assertWorkspaceShellAndTableConfig($client, '/catalog/products');

        self::assertSame('catalog-products', $config['id']);
        self::assertSame('/catalog/products/format/json', $config['url']);
        self::assertTrue($config['opensTab']);
        self::assertCount(4, $config['bulkActions']);
        self::assertSame(['delete', 'update', 'freeze', 'unfreeze'], array_column($config['bulkActions'], 'id'));
        self::assertSame('/catalog/products/bulk-delete', $config['bulkActions'][0]['url']);
        self::assertNotEmpty($config['bulkActions'][0]['csrfToken']);
        self::assertSame('_token', $config['bulkActions'][0]['csrfField']);
        self::assertSame('tanstack-table:bulk-delete', $config['bulkActions'][0]['event']);
    }

    public function testCategoriesPageRendersWithTableIdAndBulkDelete(): void
    {
        $client = $this->bootedClient();
        $config = $this->assertWorkspaceShellAndTableConfig($client, '/catalog/categories');

        self::assertSame('catalog-categories', $config['id']);
        self::assertSame('/catalog/categories/format/json', $config['url']);
        self::assertCount(1, $config['bulkActions']);
        self::assertSame('delete', $config['bulkActions'][0]['id']);
        self::assertSame('/catalog/categories/bulk-delete', $config['bulkActions'][0]['url']);
        self::assertNotEmpty($config['bulkActions'][0]['csrfToken']);
    }

    public function testProductsJsonEndpoint(): void
    {
        $client = $this->bootedClient();
        $client->request('GET', '/catalog/products/format/json', [
            'page' => 1,
            'pageSize' => 10,
        ]);

        self::assertResponseIsSuccessful();
        $payload = $this->jsonPayload($client);

        self::assertArrayHasKey('data', $payload);
        self::assertArrayHasKey('total', $payload);
        self::assertArrayHasKey('page', $payload);
        self::assertNotEmpty($payload['data']);
        self::assertArrayHasKey('id', $payload['data'][0]);
        self::assertArrayHasKey('sku', $payload['data'][0]);
    }

    public function testCategoriesJsonEndpoint(): void
    {
        $client = $this->bootedClient();
        $client->request('GET', '/catalog/categories/format/json', [
            'page' => 1,
            'pageSize' => 10,
        ]);

        self::assertResponseIsSuccessful();
        $payload = $this->jsonPayload($client);

        self::assertArrayHasKey('data', $payload);
        self::assertNotEmpty($payload['data']);
        self::assertArrayHasKey('name', $payload['data'][0]);
        self::assertArrayHasKey('productCount', $payload['data'][0]);
    }

    public function testProductAndCategoryNewFormsRender(): void
    {
        $client = $this->bootedClient();

        $client->request('GET', '/catalog/products/new');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/catalog/categories/new');
        self::assertResponseIsSuccessful();
    }

    public function testProductAndCategoryEditPagesRender(): void
    {
        $client = $this->bootedClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var Product $product */
        $product = $em->getRepository(Product::class)->findOneBy([]);
        self::assertInstanceOf(Product::class, $product);

        /** @var Category $category */
        $category = $em->getRepository(Category::class)->findOneBy([]);
        self::assertInstanceOf(Category::class, $category);

        $client->request('GET', '/catalog/products/'.$product->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller*="tstdt--workspace-tabs"]');

        $editCrawler = $this->requestWorkspaceTab($client, '/catalog/products/'.$product->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $editCrawler->filter('form')->count());

        $client->request('GET', '/catalog/categories/'.$category->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller*="tstdt--workspace-tabs"]');

        $categoryEdit = $this->requestWorkspaceTab($client, '/catalog/categories/'.$category->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $categoryEdit->filter('form')->count());
    }

    public function testProductsBulkDeleteAcceptsValidCsrf(): void
    {
        $client = $this->bootedClient();
        $config = $this->assertWorkspaceShellAndTableConfig($client, '/catalog/products');
        $token = $this->bulkActionCsrf($config, 'delete');

        $client->request('POST', '/catalog/products/bulk-delete', [
            '_token' => $token,
            'ids' => [1, 2, 3],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $this->jsonPayload($client);
        self::assertTrue($payload['ok']);
        self::assertSame('delete', $payload['action']);
        self::assertSame([1, 2, 3], $payload['ids']);
    }

    public function testProductsBulkUpdateFreezeUnfreezeAcceptValidCsrf(): void
    {
        $client = $this->bootedClient();
        $config = $this->assertWorkspaceShellAndTableConfig($client, '/catalog/products');

        foreach ([
            ['/catalog/products/bulk-update', 'update'],
            ['/catalog/products/bulk-freeze', 'freeze'],
            ['/catalog/products/bulk-unfreeze', 'unfreeze'],
        ] as [$path, $actionId]) {
            $client->request('POST', $path, [
                '_token' => $this->bulkActionCsrf($config, $actionId),
                'ids' => [9],
            ]);

            self::assertResponseIsSuccessful();
            $payload = $this->jsonPayload($client);
            self::assertSame($actionId, $payload['action']);
            self::assertSame([9], $payload['ids']);
        }
    }

    public function testCategoriesBulkDeleteAcceptsValidCsrf(): void
    {
        $client = $this->bootedClient();
        $config = $this->assertWorkspaceShellAndTableConfig($client, '/catalog/categories');

        $client->request('POST', '/catalog/categories/bulk-delete', [
            '_token' => $this->bulkActionCsrf($config, 'delete'),
            'ids' => [4, 5],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $this->jsonPayload($client);
        self::assertTrue($payload['ok']);
        self::assertSame('delete', $payload['action']);
        self::assertSame([4, 5], $payload['ids']);
    }

    public function testBulkActionRejectsInvalidCsrf(): void
    {
        $client = $this->bootedClient();
        // Start a session so CSRF validation runs inside the controller.
        $client->request('GET', '/catalog/products');
        self::assertResponseIsSuccessful();

        $client->request('POST', '/catalog/products/bulk-delete', [
            '_token' => 'invalid',
            'ids' => [1],
        ]);

        // AccessDeniedException for anonymous users is converted to a login redirect.
        self::assertResponseRedirects('/login');
    }

    private function bootedClient(): KernelBrowser
    {
        $client = static::createClient();
        static::getContainer()->get(DemoSeeder::class)->reset();

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(KernelBrowser $client): array
    {
        $payload = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function bulkActionCsrf(array $config, string $actionId): string
    {
        foreach ($config['bulkActions'] ?? [] as $action) {
            if (($action['id'] ?? null) === $actionId) {
                $token = (string) ($action['csrfToken'] ?? '');
                self::assertNotSame('', $token);

                return $token;
            }
        }

        self::fail(sprintf('Bulk action "%s" not found in table config.', $actionId));
    }
}
