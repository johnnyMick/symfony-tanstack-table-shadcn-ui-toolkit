{% extends 'base.html.twig' %}

{% block title %}Demo data{% endblock %}

{% block body %}
<?php if (!empty($with_admin_layout)): ?>
    <twig:tstdt_AdminLayout>
        <twig:block name="title">Demo data</twig:block>

        <twig:block name="actions">
<?php if (!empty($with_reset)): ?>
            <form method="post" action="{{ path('app_demo_reset') }}" onsubmit="return confirm('Rebuild the demo database with fresh sample data?');">
                <input type="hidden" name="_token" value="{{ csrf_token('demo_reset') }}">
                <twig:tstdt_Button type="submit" variant="outline" size="sm">
                    <twig:ux:icon name="lucide:refresh-cw" aria-hidden="true" />
                    Reset data
                </twig:tstdt_Button>
            </form>
<?php endif; ?>
        </twig:block>

        <twig:tstdt_TanstackDataTable :config="table" />
    </twig:tstdt_AdminLayout>
<?php else: ?>
    <div class="space-y-4 p-4">
        <h1 class="text-lg font-semibold">Demo data</h1>
<?php if (!empty($with_reset)): ?>
        <form method="post" action="{{ path('app_demo_reset') }}" onsubmit="return confirm('Rebuild the demo database with fresh sample data?');">
            <input type="hidden" name="_token" value="{{ csrf_token('demo_reset') }}">
            <twig:tstdt_Button type="submit" variant="outline" size="sm">Reset data</twig:tstdt_Button>
        </form>
<?php endif; ?>
        <twig:tstdt_TanstackDataTable :config="table" />
    </div>
<?php endif; ?>
{% endblock %}
