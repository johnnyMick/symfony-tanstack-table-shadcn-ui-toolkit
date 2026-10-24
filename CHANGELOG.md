# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Maker suite under `make:tstdt-*` (data table, admin layout, CRUD resource, demo dashboard/settings/seeder, icons, stimulus, theme, kit)
- `TableColors` / `Column::buttons()` / `ColumnButton` row actions
- Bundle Twig namespace `@tstdt` for skeleton components (include/embed)
- `Column::rawHtml()` for trusted HTML; `Column::html()` is escaped by default
- CI workflow, skeleton sync check, Table API / TableQuery unit tests
- Demo `CatalogSmokeTest`

### Changed
- Composer runtime `require` slimmed; AssetMapper / Stimulus / Maker / Doctrine moved to suggest or require-dev
- Default UI labels switched to English (HeaderAction, BulkAction, ColumnButton, table Twig/Stimulus)
- Twig components namespaced with `tstdt_` prefix
- Maker commands renamed to `make:tstdt-*`
- Maker `--force` deletes targets before Generator write (MakerBundle exists-check)
- Demo HTTP reset gated with `env: '!prod'`
- Removed debug `console.log` from shipped Stimulus table controller

### Packaging
- `.gitattributes` `export-ignore` for `demo/`, `tests/`, CI, tooling (Packagist dist hygiene)


## [1.0.0] - TBD

First tagged release candidate after CI is green on Packagist publish.
