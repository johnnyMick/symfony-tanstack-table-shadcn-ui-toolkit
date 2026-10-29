{# Workspace-tab edit partial (fetched into AdminLayout panels). #}
<div class="mx-auto w-full max-w-2xl space-y-6">
    {% for message in app.flashes('success') %}
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ message }}</div>
    {% endfor %}

    <div class="space-y-1">
        <h2 class="text-lg font-semibold tracking-tight">{{ item.name|default('<?= ucfirst($singular_label); ?> #' ~ item.id) }}</h2>
        <p class="text-sm text-muted-foreground">Edit <?= $singular_label; ?><?php if (empty($with_doctrine)): ?> (stub)<?php endif; ?></p>
    </div>

    <form
        method="post"
        action="{{ path('<?= $edit_route_name; ?>', {id: item.id}) }}"
        class="space-y-4"
        data-turbo="false"
    >
        <input type="hidden" name="_token" value="{{ csrf_token('<?= $csrf_id_prefix; ?>' ~ item.id) }}">
        <input type="hidden" name="_workspace_tab" value="1">

        <div class="space-y-1.5">
            <label for="item-name" class="text-sm font-medium">Name</label>
            <twig:tstdt:Input id="item-name" name="name" value="{{ item.name|default('') }}" required />
        </div>

        <div class="flex items-center gap-2 pt-2">
            <twig:tstdt:Button type="submit">Save</twig:tstdt:Button>
        </div>
    </form>
</div>
