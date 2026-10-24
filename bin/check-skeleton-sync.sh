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
  demo/assets/controllers/tanstack_table_controller.js \
  templates/skeleton/assets/controllers/tanstack_table_controller.js

check_same \
  demo/assets/controllers/tanstack_table_controller.js \
  templates/skeleton/kits/stimulus/assets/controllers/tanstack_table_controller.js

check_same \
  demo/templates/components/tstdt_TanstackDataTable.html.twig \
  templates/skeleton/templates/components/tstdt_TanstackDataTable.html.twig

# Shared Stimulus controllers (demo ↔ stimulus kit)
for ctrl in \
  select_controller.js \
  dropdown_menu_controller.js \
  dropdown_menu_sub_controller.js \
  dialog_controller.js \
  tooltip_controller.js \
  collapsible_controller.js \
  sidebar_controller.js
do
  check_same \
    "demo/assets/controllers/${ctrl}" \
    "templates/skeleton/kits/stimulus/assets/controllers/${ctrl}"
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
