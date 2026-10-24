# Symfony TanStack Table shadcn/ui Toolkit

Symfony demo for TanStack Table and shadcn/ui toolkit bundle.

## Requirements
- PHP 8.4 or newer (matches `composer.lock` / Doctrine UX / PHPUnit 13)

## Install
```shell
composer install
```

## Usage
```shell
php bin/console app:demo:reset
# symfony need to be installed
# wget https://get.symfony.com/cli/installer -O - | bash
symfony local:server:start --dir=public
```

**Local demo only.** Prefer `app:demo:reset` (CLI). The HTTP `POST /demo/reset` route is disabled in `prod` (`env: '!prod'`); do not expose this app as a public demo without auth.

## Change the shadcn theme

The demo uses [Symfony UX shadcn](https://ux.symfony.com/toolkit/kits/shadcn) components. Colors come from CSS variables in `assets/styles/app.css` (currently **Ocean Breeze** from [tweakcn](https://tweakcn.com)).

1. Open `assets/styles/app.css`.
2. Replace the values inside `:root { ... }` (light) and `.dark { ... }` (dark) with another theme’s tokens — keep the same variable names (`--primary`, `--sidebar`, `--background`, etc.).
3. Leave the imports at the top and the `@theme inline { ... }` block as-is; they map those variables to Tailwind utilities.
4. Rebuild CSS:
   ```shell
   php bin/console tailwind:build
   # or watch while developing:
   php bin/console tailwind:build --watch
   ```
5. Hard-refresh the browser.

### Where to get themes

- [tweakcn.com](https://tweakcn.com) — visual editor; export CSS variables
- [ui.shadcn.com/themes](https://ui.shadcn.com/themes) — official base/accent presets
- Ready-made packs: [csscreme.com/themes](https://csscreme.com/themes), [designrevision.com/shadcn-themes](https://designrevision.com/shadcn-themes)

Do **not** run `npx shadcn add …` against this Symfony app. Copy the theme’s `:root` / `.dark` CSS variables into `app.css` instead.

If a theme uses a custom font, load it in `templates/base.html.twig` (see the current DM Sans / IBM Plex Mono `<link>` tags) and set `--font-sans` / `--font-mono` in the theme blocks.

## Tests
```shell
php bin/phpunit
```
