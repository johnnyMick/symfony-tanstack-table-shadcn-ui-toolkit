{% extends 'base.html.twig' %}

{% block title %}Preferences{% endblock %}

{% block body %}
<?php if (!empty($with_admin_layout)): ?>
    <twig:tstdt:AdminLayout :config="layout">
        <twig:tstdt:Card>
            <twig:tstdt:Card:Header>
                <twig:tstdt:Card:Title>Preferences</twig:tstdt:Card:Title>
                <twig:tstdt:Card:Description>Placeholder settings page for the admin shell.</twig:tstdt:Card:Description>
            </twig:tstdt:Card:Header>
            <twig:tstdt:Card:Content class="space-y-3 text-sm text-muted-foreground">
                <p>Theme colors live in <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">assets/styles/app.css</code>.</p>
                <p>Install the theme kit with <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">php bin/console make:tstdt-theme</code>.</p>
                <p>Sidebar collapse shortcut: <kbd class="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs">Ctrl</kbd>/<kbd class="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs">⌘</kbd>+<kbd class="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs">B</kbd>.</p>
            </twig:tstdt:Card:Content>
        </twig:tstdt:Card>
    </twig:tstdt:AdminLayout>
<?php else: ?>
    <div class="space-y-4 p-4">
        <h1 class="text-lg font-semibold">Preferences</h1>
        <p class="text-sm text-muted-foreground">Theme colors live in <code>assets/styles/app.css</code>.</p>
    </div>
<?php endif; ?>
{% endblock %}
