{% extends 'base.html.twig' %}

{% block title %}Preferences{% endblock %}

{% block body %}
<?php if (!empty($with_admin_layout)): ?>
    <twig:tstdt_AdminLayout>
        <twig:block name="title">Preferences</twig:block>

        <twig:tstdt_Card>
            <twig:tstdt_Card:Header>
                <twig:tstdt_Card:Title>Preferences</twig:tstdt_Card:Title>
                <twig:tstdt_Card:Description>Placeholder settings page for the admin shell.</twig:tstdt_Card:Description>
            </twig:tstdt_Card:Header>
            <twig:tstdt_Card:Content class="space-y-3 text-sm text-muted-foreground">
                <p>Theme colors live in <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">assets/styles/app.css</code>.</p>
                <p>Install the theme kit with <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">php bin/console make:tstdt-theme</code>.</p>
                <p>Sidebar collapse shortcut: <kbd class="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs">Ctrl</kbd>/<kbd class="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs">⌘</kbd>+<kbd class="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs">B</kbd>.</p>
            </twig:tstdt_Card:Content>
        </twig:tstdt_Card>
    </twig:tstdt_AdminLayout>
<?php else: ?>
    <div class="space-y-4 p-4">
        <h1 class="text-lg font-semibold">Preferences</h1>
        <p class="text-sm text-muted-foreground">Theme colors live in <code>assets/styles/app.css</code>.</p>
    </div>
<?php endif; ?>
{% endblock %}
