<?php

declare(strict_types=1);

namespace App\Tests\Controller\Concerns;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Helpers for AdminLayout pages whose main tab content loads via AJAX
 * ({@code X-Workspace-Tab: 1}).
 */
trait InteractsWithWorkspaceTables
{
    /**
     * Server headers that match the workspace-tabs Stimulus fetch.
     *
     * @return array<string, string>
     */
    private function workspaceTabServer(): array
    {
        return [
            'HTTP_X_WORKSPACE_TAB' => '1',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ACCEPT' => 'text/html',
        ];
    }

    private function requestWorkspaceTab(KernelBrowser $client, string $path): Crawler
    {
        return $client->request('GET', $path, server: $this->workspaceTabServer());
    }

    /**
     * @return array<string, mixed>
     */
    private function tableConfig(Crawler $crawler): array
    {
        $node = $crawler->filter('[data-controller="tstdt--tanstack-table"]');
        self::assertGreaterThan(0, $node->count(), 'Expected a TanStack table in the response.');

        $attr = $node->attr('data-config');
        self::assertNotNull($attr);
        self::assertNotSame('', $attr);

        $config = json_decode(html_entity_decode($attr, \ENT_QUOTES | \ENT_HTML5), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($config);

        return $config;
    }

    /**
     * Full page shell (empty main panel) + AJAX main-tab fragment with the table.
     *
     * @return array<string, mixed>
     */
    private function assertWorkspaceShellAndTableConfig(KernelBrowser $client, string $path): array
    {
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller*="tstdt--workspace-tabs"]');
        self::assertSelectorExists('[data-slot="workspace-tab"][data-tab-id="main"][data-open-url]');

        $crawler = $this->requestWorkspaceTab($client, $path);
        self::assertResponseIsSuccessful();

        return $this->tableConfig($crawler);
    }
}
