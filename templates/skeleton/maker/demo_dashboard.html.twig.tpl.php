{% extends 'base.html.twig' %}

{% block title %}Dashboard{% endblock %}

{% block body %}
<?php if (!empty($with_admin_layout)): ?>
    <twig:tstdt_AdminLayout>
        <twig:block name="title">Dashboard</twig:block>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {% for card in cards %}
                <a href="{{ path(card.route) }}" class="block transition-opacity hover:opacity-90">
                    <twig:tstdt_Card>
                        <twig:tstdt_Card:Header>
                            <twig:tstdt_Card:Title class="flex items-center gap-2">
                                <twig:ux:icon name="{{ card.icon }}" class="size-4 text-muted-foreground" aria-hidden="true" />
                                {{ card.label }}
                            </twig:tstdt_Card:Title>
                            <twig:tstdt_Card:Description>Open the {{ card.label|lower }} table</twig:tstdt_Card:Description>
                        </twig:tstdt_Card:Header>
                        <twig:tstdt_Card:Content>
                            <p class="text-3xl font-semibold tracking-tight">{{ card.count|number_format }}</p>
                        </twig:tstdt_Card:Content>
                    </twig:tstdt_Card>
                </a>
            {% endfor %}
        </div>

        <twig:tstdt_Card>
            <twig:tstdt_Card:Header>
                <twig:tstdt_Card:Title>Getting started</twig:tstdt_Card:Title>
                <twig:tstdt_Card:Description>
                    Use the sidebar to browse tables. Reset seed data under Settings when available.
                </twig:tstdt_Card:Description>
            </twig:tstdt_Card:Header>
        </twig:tstdt_Card>
    </twig:tstdt_AdminLayout>
<?php else: ?>
    <div class="space-y-4 p-4">
        <h1 class="text-lg font-semibold">Dashboard</h1>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {% for card in cards %}
                <a href="{{ path(card.route) }}" class="block transition-opacity hover:opacity-90">
                    <twig:tstdt_Card>
                        <twig:tstdt_Card:Header>
                            <twig:tstdt_Card:Title class="flex items-center gap-2">
                                <twig:ux:icon name="{{ card.icon }}" class="size-4 text-muted-foreground" aria-hidden="true" />
                                {{ card.label }}
                            </twig:tstdt_Card:Title>
                            <twig:tstdt_Card:Description>Open the {{ card.label|lower }} table</twig:tstdt_Card:Description>
                        </twig:tstdt_Card:Header>
                        <twig:tstdt_Card:Content>
                            <p class="text-3xl font-semibold tracking-tight">{{ card.count|number_format }}</p>
                        </twig:tstdt_Card:Content>
                    </twig:tstdt_Card>
                </a>
            {% endfor %}
        </div>
    </div>
<?php endif; ?>
{% endblock %}
