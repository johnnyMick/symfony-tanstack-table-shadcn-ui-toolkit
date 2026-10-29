<div class="mx-auto w-full max-w-2xl space-y-6">
    <div class="space-y-1">
        <h2 class="text-lg font-semibold tracking-tight">New <?= $singular_label; ?></h2>
        <p class="text-sm text-muted-foreground">Create a new record.</p>
    </div>

    <form
        method="post"
        action="{{ path('<?= $new_route_name; ?>') }}"
        class="space-y-4"
        data-turbo="false"
    >
        <input type="hidden" name="_token" value="{{ csrf_token('<?= $csrf_token_id; ?>') }}">

        <div class="space-y-1.5">
            <label for="item-name" class="text-sm font-medium">Name</label>
            <twig:tstdt:Input id="item-name" name="name" value="{{ values.name|default('') }}" required autofocus />
        </div>

        <div class="flex items-center gap-2 pt-2">
            <twig:tstdt:Button type="submit">Create</twig:tstdt:Button>
        </div>
    </form>
</div>
