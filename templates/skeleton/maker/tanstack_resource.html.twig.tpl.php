{% extends 'base.html.twig' %}

{% block title %}<?= $page_title; ?>{% endblock %}

{% block body %}
<?php if (!empty($with_admin_layout)): ?>
    {# Shell only — main tab content loads via AJAX (X-Workspace-Tab). #}
    <twig:tstdt:AdminLayout :config="layout" />
<?php else: ?>
    <twig:tstdt:TanstackDataTable :config="table" />
<?php endif; ?>
{% endblock %}
