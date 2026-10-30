# Changelog

All notable changes to `black-paradise/laravel-admin` are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Breaking entries cite the id in `UPGRADE.md`.

## [3.1.0] - 2026-10-30

### Added
- `php artisan bpadmin:doctor` (`--json`; exit 0 clean, 1 on violations) listing every definition violation.
- `bpadmin:cache` validates definitions and refuses to write the manifest on violations (exit 1).
- Field persister and query-strategy registries; `FieldTypeRegistry::register(type, fieldClass, ?persister, ?query)`.
- `ExecuteActionUseCase` wiring, `EntityActionRequest`, `ContainerActionResolver`.
- `AtomicSoftDeletes` model trait (atomic `forceDelete()` with owned cascade).
- `{field}__clear` support for file fields; per-definition `repository()` / `mutator()`.
- `DashboardServiceProvider` split into Persistence / FieldType / EntityDiscovery / Routing / Console providers.

### Changed
- **BREAKING (U-01)** Requires `black-paradise/core-admin ^1.1`.
- **BREAKING (U-02..U-04)** Contract changes for definitions and actions (see core changelog).
- **BREAKING (U-05)** `EntityPresenterInterface::actionResult(ActionResult, EntityDefinitionContract)`; JSON row-action results drop `id`; action-input 422 keys are `input.<field>`.
- **BREAKING (U-07, U-08)** `RelationWriter`, `RelationKind::isSideEffect()` use and the `SIDE_EFFECT_RELATION_TYPES` / `NON_COLUMN_TYPES` / `PROJECTION_UNSAFE_TYPES` lists removed.
- **BREAKING (U-09)** Controller delegates to graph use cases; double authorization for nested graph saves.
- **BREAKING (U-11)** Owned embedded children are deleted by BPAdmin; soft-delete keeps children, cascade on force-delete only.
- **BREAKING (U-12)** Events after commit; download flash shows on the next request.
- **BREAKING (U-13)** Boot validations throw `InvalidDefinitionException` (model-less writable/relation target/embedded, non-queryable searchable/sortable/filterable, unregistered embedded). Web boot only: console boot never throws (`bpadmin:doctor` reports, exit 1) and a `bpadmin:cache` manifest skips boot validation.
- **BREAKING (U-10)** Bulk destroy denied by a policy returns 200 with the key in `failed_ids` (same shape as a partial failure).
- **BREAKING (U-17)** Translatable search/sort: case-insensitive, per-locale, bound JSON path; PostgreSQL 16+ required.
- **BREAKING (U-19)** Default `searchFields()` derived from `->searchable()` fields; search parameter `q`.

### Fixed
- Embed-only definitions (`$creatable = false`) can be created as embedded children of a host again (previously 404 via `CapabilityDeniedException`); standalone create stays denied. Same for embedded-child updates.
- Translatable search no longer matches every row for `uk` / `en` / `:` (U-17).
- Updating a definition with a `requiredOnCreate()` file field without re-upload no longer returns 422 (U-16).
- Unknown action class raises `InvalidDefinitionException` instead of a 500 binding error (U-04).
- Plain search escapes `%`, `_` and the escape char `!` (`ESCAPE '!'`).
- Embedded hasMany/morphMany child whose definition has a `belongsToMany` field no longer fails with HTTP 500 on save; the pivot is synced after the child row is saved.
- `TranslatableQueryStrategy` sort resolves the locale provider once per call.
- `Psr4ClassLocator` caches the merged Composer PSR-4 map per instance.

Known limits: see `UPGRADE.md`.
