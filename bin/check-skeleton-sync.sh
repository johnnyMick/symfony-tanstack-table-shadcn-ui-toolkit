#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

fail=0

check_same() {
  local a="$1" b="$2"
  if [[ ! -f "$a" || ! -f "$b" ]]; then
    echo "MISSING: $a or $b"
    fail=1
    return
  fi
  if ! diff -q "$a" "$b" >/dev/null; then
    echo "DRIFT: $a <-> $b"
    fail=1
  else
    echo "OK: $a"
  fi
}

# Core table surface: demo ↔ skeleton ↔ stimulus kit
check_same \
  demo/assets/controllers/tstdt/tanstack_table_controller.js \
  templates/skeleton/assets/controllers/tstdt/tanstack_table_controller.js

check_same \
  demo/assets/controllers/tstdt/tanstack_table_controller.js \
  templates/skeleton/kits/stimulus/assets/controllers/tstdt/tanstack_table_controller.js

check_same \
  demo/templates/components/tstdt/TanstackDataTable.html.twig \
  templates/skeleton/templates/components/tstdt/TanstackDataTable.html.twig

# Demo entities (demo ↔ demo-entities kit)
for entity in Category Product Customer Order OrderItem User
do
  check_same \
    "demo/src/Entity/${entity}.php" \
    "templates/skeleton/kits/demo-entities/src/Entity/${entity}.php"
done

check_same \
  demo/src/Repository/UserRepository.php \
  templates/skeleton/kits/demo-entities/src/Repository/UserRepository.php

check_same \
  demo/src/EventSubscriber/Query/ProductStatusFilterSubscriber.php \
  templates/skeleton/kits/demo-entities/src/EventSubscriber/Query/ProductStatusFilterSubscriber.php

check_same \
  demo/src/EventSubscriber/Query/SalesStatusFilterSubscriber.php \
  templates/skeleton/kits/demo-entities/src/EventSubscriber/Query/SalesStatusFilterSubscriber.php

# Shared Stimulus controllers (demo ↔ stimulus kit)
for ctrl in \
  select_controller.js \
  dropdown_menu_controller.js \
  dropdown_menu_sub_controller.js \
  dialog_controller.js \
  tooltip_controller.js \
  collapsible_controller.js \
  sidebar_controller.js \
  workspace_tabs_controller.js \
  command_palette_controller.js
do
  check_same \
    "demo/assets/controllers/tstdt/${ctrl}" \
    "templates/skeleton/kits/stimulus/assets/controllers/tstdt/${ctrl}"
done

# Workspace tabs + command palette also ship with admin-layout + skeleton assets root
check_same \
  demo/assets/controllers/tstdt/workspace_tabs_controller.js \
  templates/skeleton/assets/controllers/tstdt/workspace_tabs_controller.js

check_same \
  demo/assets/controllers/tstdt/workspace_tabs_controller.js \
  templates/skeleton/kits/admin-layout/assets/controllers/tstdt/workspace_tabs_controller.js

check_same \
  demo/assets/controllers/tstdt/command_palette_controller.js \
  templates/skeleton/assets/controllers/tstdt/command_palette_controller.js

check_same \
  demo/assets/controllers/tstdt/command_palette_controller.js \
  templates/skeleton/kits/admin-layout/assets/controllers/tstdt/command_palette_controller.js

# AdminLayout shell (demo ↔ admin-layout kit)
check_same \
  demo/templates/components/tstdt/AdminLayout.html.twig \
  templates/skeleton/kits/admin-layout/templates/components/tstdt/AdminLayout.html.twig

for partial in \
  _sidebar_brand.html.twig \
  _sidebar_nav.html.twig \
  _nav_item.html.twig \
  _sidebar_account.html.twig \
  _workspace_header.html.twig \
  _workspace_tabs.html.twig \
  _workspace_panels.html.twig \
  _workspace_replace_dialog.html.twig \
  _workspace_command_palette.html.twig \
  _workspace_command_confirm.html.twig
do
  check_same \
    "demo/templates/components/tstdt/AdminLayout/${partial}" \
    "templates/skeleton/kits/admin-layout/templates/components/tstdt/AdminLayout/${partial}"
done

# Theme kit mirrors demo app.css (same tokens / imports)
check_same \
  demo/assets/styles/app.css \
  templates/skeleton/kits/theme/assets/styles/tanstack-theme.css

if [[ "$fail" -ne 0 ]]; then
  echo "Skeleton sync check failed. Copy demo → skeleton (or the reverse) and commit."
  exit 1
fi

echo "Skeleton sync check passed."
