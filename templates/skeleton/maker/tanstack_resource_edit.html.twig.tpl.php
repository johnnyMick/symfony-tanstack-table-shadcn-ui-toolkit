{% extends 'base.html.twig' %}

{% block title %}<?= $page_title; ?>{% endblock %}

{% block body %}
    {# Shell only — main tab content loads via AJAX (X-Workspace-Tab). #}
    <twig:tstdt:AdminLayout :config="layout" />
{% endblock %}
