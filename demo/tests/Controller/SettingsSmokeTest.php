<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Datatable\DemoSeeder;
use App\Tests\Controller\Concerns\InteractsWithWorkspaceTables;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SettingsSmokeTest extends WebTestCase
{
    use InteractsWithWorkspaceTables;

    public function testDemoDataPageRendersWithTableId(): void
    {
        $client = $this->bootedClient();
        $config = $this->assertWorkspaceShellAndTableConfig($client, '/settings/demo-data');

        self::assertSame('settings-demo-data', $config['id']);
        self::assertSame('/settings/demo-data/format/json', $config['url']);
        self::assertSame([], $config['bulkActions'] ?? []);
    }

    public function testDemoDataJsonEndpoint(): void
    {
        $client = $this->bootedClient();
        $client->request('GET', '/settings/demo-data/format/json', [
            'page' => 1,
            'pageSize' => 10,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertArrayHasKey('data', $payload);
        self::assertArrayHasKey('total', $payload);
        self::assertNotEmpty($payload['data']);
        self::assertArrayHasKey('table', $payload['data'][0]);
        self::assertArrayHasKey('rows', $payload['data'][0]);
    }

    public function testPreferencesPageRenders(): void
    {
        $client = $this->bootedClient();
        $client->request('GET', '/settings/preferences');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller*="tstdt--workspace-tabs"]');

        $crawler = $this->requestWorkspaceTab($client, '/settings/preferences');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Demo preferences', $crawler->text());
    }

    private function bootedClient(): KernelBrowser
    {
        $client = static::createClient();
        static::getContainer()->get(DemoSeeder::class)->reset();

        return $client;
    }
}
