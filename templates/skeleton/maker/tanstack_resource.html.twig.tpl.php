{% extends 'base.html.twig' %}

{% block title %}<?= $page_title; ?>{% endblock %}

{% block body %}
<?php if (!empty($with_admin_layout)): ?>
    <twig:tstdt_AdminLayout>
        <twig:block name="title"><?= $page_title; ?></twig:block>

        <twig:tstdt_TanstackDataTable :config="table" />
    </twig:tstdt_AdminLayout>
<?php else: ?>
    <twig:tstdt_TanstackDataTable :config="table" />
<?php endif; ?>
{% endblock %}
