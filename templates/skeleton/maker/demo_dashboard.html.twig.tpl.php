{% extends 'base.html.twig' %}

{% block title %}Dashboard{% endblock %}

{% block body %}
<?php if (!empty($with_admin_layout)): ?>
    <twig:tstdt:AdminLayout :config="layout">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {% for card in cards %}
                <a href="{{ path(card.route) }}" class="block transition-opacity hover:opacity-90">
                    <twig:tstdt:Card>
                        <twig:tstdt:Card:Header>
                            <twig:tstdt:Card:Title class="flex items-center gap-2">
                                <twig:ux:icon name="{{ card.icon }}" class="size-4 text-muted-foreground" aria-hidden="true" />
                                {{ card.label }}
                            </twig:tstdt:Card:Title>
                            <twig:tstdt:Card:Description>Open the {{ card.label|lower }} table</twig:tstdt:Card:Description>
                        </twig:tstdt:Card:Header>
                        <twig:tstdt:Card:Content>
                            <p class="text-3xl font-semibold tracking-tight">{{ card.count|number_format }}</p>
                        </twig:tstdt:Card:Content>
                    </twig:tstdt:Card>
                </a>
            {% endfor %}
        </div>

        <twig:tstdt:Card>
            <twig:tstdt:Card:Header>
                <twig:tstdt:Card:Title>Getting started</twig:tstdt:Card:Title>
                <twig:tstdt:Card:Description>
                    Use the sidebar to browse tables. Reset seed data under Settings when available.
                </twig:tstdt:Card:Description>
            </twig:tstdt:Card:Header>
        </twig:tstdt:Card>
    </twig:tstdt:AdminLayout>
<?php else: ?>
    <div class="space-y-4 p-4">
        <h1 class="text-lg font-semibold">Dashboard</h1>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {% for card in cards %}
                <a href="{{ path(card.route) }}" class="block transition-opacity hover:opacity-90">
                    <twig:tstdt:Card>
                        <twig:tstdt:Card:Header>
                            <twig:tstdt:Card:Title class="flex items-center gap-2">
                                <twig:ux:icon name="{{ card.icon }}" class="size-4 text-muted-foreground" aria-hidden="true" />
                                {{ card.label }}
                            </twig:tstdt:Card:Title>
                            <twig:tstdt:Card:Description>Open the {{ card.label|lower }} table</twig:tstdt:Card:Description>
                        </twig:tstdt:Card:Header>
                        <twig:tstdt:Card:Content>
                            <p class="text-3xl font-semibold tracking-tight">{{ card.count|number_format }}</p>
                        </twig:tstdt:Card:Content>
                    </twig:tstdt:Card>
                </a>
            {% endfor %}
        </div>
    </div>
<?php endif; ?>
{% endblock %}
