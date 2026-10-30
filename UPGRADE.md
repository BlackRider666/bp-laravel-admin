# Upgrade guide

## 3.0 → 3.1 (core 1.1, blade-ui 3.1)

This is one coordinated release of three packages:

| Package | From | To |
|---------|------|----|
| `black-paradise/core-admin` | 1.0.x | 1.1.0 |
| `black-paradise/laravel-admin` | 3.0.x | 3.1.0 |
| `black-paradise/admin-blade-ui` | 3.0.x | 3.1.0 |

It is breaking in substance but released as a minor. **Pin your application to the old line first**
(`3.0.*` / `1.0.*` / `3.0.*`), then follow the steps below on a branch. Each step has the form
`Applies if` (a grep that tells you whether you are affected) → `Before` → `After` → `Verify` → `Ref`.
`php artisan bpadmin:doctor` is the common verification tool (see U-13).

The other packages have their own, shorter guides: `bp-admin-core/UPGRADE.md` and
`bp-laravel-admin-blade-ui/UPGRADE.md`. They link back here; this file is canonical.

### Checklist

| Id | Change | Applies if (grep) |
|----|--------|-------------------|
| U-01 | Composer floors raised: `core-admin ^1.1`, `laravel-admin ^3.1`, `admin-blade-ui ^3.1` | `black-paradise/` in `composer.json` |
| U-02 | `EntityDefinitionContract` gains `capabilities()`, `resolveName()`, `filters()`, `repository()`, `mutator()`; `modelClass(): ?string` | `implements EntityDefinitionContract` |
| U-03 | `ActionContract`: `scope()` → `scopes()`; `handle()` and `fields()` required; new `permission()` | `implements ActionContract` |
| U-04 | `actions()` returns class-strings resolved via `ActionResolverContract`; constructors must be cheap; unknown class → `InvalidDefinitionException` | `function actions(` |
| U-05 | `EntityPresenterInterface::actionResult(ActionResult, EntityDefinitionContract)`; JSON row-action result has no `id`; JSON 422 keys `input.<field>` | `implements EntityPresenterInterface`, `actionResult(` |
| U-06 | `isCreatable()` kept as sugar; other capabilities by overriding `capabilities()` | `$creatable`, `isCreatable(` |
| U-07 | `RelationKind::isSideEffect()`, `RelationWriter::SIDE_EFFECT_RELATION_TYPES`, `NON_COLUMN_TYPES`, `PROJECTION_UNSAFE_TYPES` removed; custom types use `FieldTypeRegistry::register` | those identifiers; custom `type()` |
| U-08 | `RelationWriter` removed (split into field persisters); HasMany `getStrategy()` on the field contract | `RelationWriter`, `getStrategy` |
| U-09 | Controller no longer orchestrates; customise graph saves by decorating `SaveEntityGraphUseCaseContract`; double authorization on graph saves | subclasses of the entity controller |
| U-10 | `DeleteRecordUseCase` no longer pre-checks `exists()`; mutator `delete()` must return `false` for a missing record | `implements EntityMutatorInterface` |
| U-11 | Owned hasMany/morphMany embedded children are now deleted by BPAdmin; soft-delete keeps children, cascade only on force-delete; `AtomicSoftDeletes` | `->owns()` on HasMany/MorphMany/HasOne/BelongsTo |
| U-12 | Domain events dispatched after commit inside a write scope; download flash shows on the next request | listeners on BPAdmin events |
| U-13 | Boot validations now throw; new `bpadmin:doctor` | all definitions |
| U-14 | Views read `$ui` instead of `$definition->isCreatable()`; republish views; republish lang; `@js` escaping fix | `resources/views/vendor/bpadmin`, `lang/vendor/bpadmin` |
| U-15 | Assets must be republished | `public/vendor/bpadmin` |
| U-16 | Required file fields that must stay optional on edit → `requiredOnCreate()` | `File.*->required()` |
| U-17 | Translatable search/sort semantics changed; PostgreSQL 16+ required for translatable queries | `TranslatableField` + `searchable()` |
| U-18 | A definition-specific `mutator()` is not decorated by `app->extend(EntityMutatorInterface::class)` | `app->extend(EntityMutatorInterface` |
| U-19 | Default `searchFields()` is derived from fields marked `->searchable()`; search query parameter is `q` | `->searchable()`, `function searchFields(` |

### U-01 — Pin and bump composer floors

**Applies if:** `grep -n 'black-paradise/' composer.json`. Always.

**Before:**
```json
"black-paradise/core-admin": "1.0.*",
"black-paradise/laravel-admin": "3.0.*",
"black-paradise/admin-blade-ui": "3.0.*"
```

**After:** the new floors are `laravel-admin` → `core-admin ^1.1`; `admin-blade-ui` → `laravel-admin ^3.1` and
`core-admin ^1.1`. In the application:
```json
"black-paradise/core-admin": "^1.1",
"black-paradise/laravel-admin": "^3.1",
"black-paradise/admin-blade-ui": "^3.1"
```
`composer update 'black-paradise/*' --with-all-dependencies`. If you develop against local path repositories,
they must declare versions `1.1.0` / `3.1.0` (a path repository pinned to `1.0.x` can no longer satisfy the floor).

**Verify:** `composer show 'black-paradise/*'` lists 1.1.x / 3.1.x / 3.1.x; `composer why-not black-paradise/core-admin 1.0.6`
reports the new floor as the blocker.

**Ref:** CHANGELOG (all three packages).

### U-02 — `EntityDefinitionContract` gained methods; `modelClass(): ?string`

**Applies if:** `grep -rn 'implements EntityDefinitionContract' app/`. Definitions that `extends BlackParadise\LaravelAdmin\EntityDefinition`
are not affected (the base class implements everything).

**Before:** a hand-written implementation of the contract.

**After:** add `capabilities(): EntityCapabilities`, `resolveName(): string` (an **instance** method, not static),
`filters(): array`, `repository(): ?string`, `mutator(): ?string`. `modelClass()` now returns `?string`
(`null` = no Eloquent model; such a definition is forced read-only unless it also provides a mutator).
Models-less definitions cannot be Eloquent relation targets or embedded children.
Simplest path: extend `BlackParadise\LaravelAdmin\EntityDefinition`.

**Verify:** `php artisan bpadmin:doctor`; `composer dump-autoload` then `php artisan about` boots without a fatal.

**Ref:** CHANGELOG core 1.1.0 / laravel-admin 3.1.0.

### U-03 — `ActionContract` is now executable

**Applies if:** `grep -rn 'implements ActionContract' app/`.

**Before:** metadata-only stub with `scope(): string`.

**After:**
- `scope(): string` → `scopes(): list<ActionScope>` (`ActionScope::Row|Bulk|Entity`).
- `handle(ActionContext $context): ActionResult` is **required** (`ActionContext` carries definition, scope, `keys`, validated `input`).
- `fields(): array` is **required** (return `[]` when the action takes no input).
- `permission(): ?string` (default convention `action.<name>`), `confirm(): bool`, `meta(): array`.
- Results: `ActionResult::message()`, `redirect()`, `download()`; optional `failedKeys`.

**Verify:** `php artisan bpadmin:doctor`; run the action through the UI or `POST {entity}/{id}/actions/{action}`.

**Ref:** U-04, U-05.

### U-04 — `actions()` returns class-strings

**Applies if:** `grep -rn 'function actions(' app/BPAdmin app/`.

**Before:** `return [new ExportAction(), new ArchiveAction()];`

**After:** `return [ExportAction::class, ArchiveAction::class];` (`list<class-string<ActionContract>>`). Classes are resolved
through the container via `ActionResolverContract` **on every render and execution**, so constructors must be cheap
(no I/O, no heavy queries — inject lazily). An unknown or non-`ActionContract` class now raises
`InvalidDefinitionException` (on index/show render and on execute) instead of a `BindingResolutionException` 500, and
`bpadmin:doctor` reports it.

**Verify:** `php artisan bpadmin:doctor`; open the index and show pages of the definition.

**Ref:** U-03.

### U-05 — Presenter signature and JSON action contract

**Applies if:** `grep -rn 'implements EntityPresenterInterface' app/` or `grep -rn 'actionResult(' app/`. Third-party presenters
fatal at boot otherwise.

**Before:** `actionResult(string $message, ?string $id): Response`.

**After:** `actionResult(ActionResult $result, EntityDefinitionContract $definition): Response`.
Behavioural changes in the shipped presenters:
- JSON row-action results no longer include `id` (they are `{outcome, message, redirect?, failedKeys}`); put the id in the message text if needed.
- JSON 422 validation errors for action input are keyed `input.<field>` (not `<field>`).
- The Blade presenter flashes the download-path message on the **next** request (the response itself is the file).

**Verify:** a request that triggers a row action returns the new JSON shape; `vendor/bin/pest` (your presenter tests).

**Ref:** U-03.

### U-06 — Capabilities

**Applies if:** `grep -rn '\$creatable\|isCreatable(' app/`.

**Before/After:** `$creatable` / `isCreatable()` keep working (default `capabilities()` derives from them).
For anything else (read-only, no-delete) override `capabilities()`:
`return EntityCapabilities::all()->without(Capability::Delete);` or `EntityCapabilities::readOnly()`.
Authorization still runs first; a denied capability surfaces as `CapabilityDeniedException` → 404 in presenters.
Capabilities (incl. `$creatable = false`) govern standalone surfaces only: embedded children (create and update via a host) are governed by authorization.

**Verify:** the corresponding create/edit/delete controls disappear and the routes respond 404.

**Ref:** U-14.

### U-07 — Side-effect / non-column constants removed

**Applies if:** `grep -rn 'isSideEffect\|SIDE_EFFECT_RELATION_TYPES\|NON_COLUMN_TYPES\|PROJECTION_UNSAFE_TYPES' app/ packages/`
or you register custom field types.

**Before:** subclass/branch on those lists.

**After:** use `FieldContract::isSideEffect()`. Custom types are registered with
`FieldTypeRegistry::register(string $type, string $fieldClass, ?FieldPersisterContract $persister = null, ?FieldQueryStrategyContract $query = null)`.
The fallback strategies are the column persister and the column query strategy.

**Verify:** `php artisan bpadmin:doctor`; persistence tests for the custom type.

**Ref:** U-08.

### U-08 — `RelationWriter` removed

**Applies if:** `grep -rn 'RelationWriter\|getStrategy' app/`.

**After:** per-type `FieldPersisterContract` strategies replace it (registry `FieldPersisterRegistry`, hooks `toAttributes`,
`afterSave`, `beforeDelete`). HasMany fields expose `getStrategy()` on their contract (no `method_exists`).
The seams WRP-style hosts decorate (`EntityMutatorInterface`, `FileStorageProviderContract`, `EntityRepositoryInterface`) are unchanged.

**Verify:** `vendor/bin/pest`.

### U-09 — Thin controller; graph-level customisation; double authorization

**Applies if:** `grep -rn 'extends .*EntityController\|extends AbstractController' app/`.

**After:** the controller maps request → command → use case → presenter and no longer runs the file scope, transaction or
embedded pipeline. To customise a whole graph save/delete, decorate `SaveEntityGraphUseCaseContract` in the
container (`$this->app->extend(...)`); deletes go through `DeleteEntityGraphUseCase` (no contract; use your mutator or a rebinding). Authorization is performed for the host
**and** for each child entity touched by a nested graph save (least-privilege by design): users without the child entity
permissions now get 403 on graph saves that touch it.

**Verify:** save a host with embedded children as a user holding only the host permission (expect 403) and as a full admin (expect success).

**Ref:** U-11, U-12.

### U-10 — `DeleteRecordUseCase` no longer calls `exists()`

**Applies if:** `grep -rn 'implements EntityMutatorInterface' app/`.

**After:** `delete()` must return `false` for a missing record; the use case maps that to `EntityNotFoundException`.
A mutator that returned `true` for a missing row will now report success.

**Verify:** delete a non-existent key through your mutator: expect 404.

**Bulk destroy:** each key is its own write scope (partial success). A key denied by the policy (`UnauthorizedException`) or failing
otherwise is reported in `failed_ids` and the response is still **200** (JSON: `deleted`, `failed_ids`, not-found ids); it looks
the same as a partial failure. Clients must read `failed_ids` rather than rely on the status code.

### U-11 — Owned children are deleted by BPAdmin; soft-delete semantics

**Applies if:** `grep -rn '\->owns()' app/BPAdmin` (HasMany/MorphMany are the cascaded kinds; BelongsTo/HasOne owned
records are collected the same way).

**Before:** owned children were removed only by DB foreign-key cascades; model events, file cleanup and pivot rows were skipped.

**After:**
- Deleting a non-soft-deletable host, or **force**-deleting a soft-deletable one, explicitly deletes owned hasMany/morphMany children
  (authorization per child, `beforeDelete`, deferred file removal after commit, Eloquent events fire). The FK stays as a backstop.
- **Soft**-deleting a soft-deletable host soft-deletes the host only; children, their files and pivot rows are untouched, so restore is lossless.
- belongsToMany pivot rows of cascaded children are detached on cascade. Pivots **without** a foreign key (e.g. created with
  `foreignIdFor()` and no `->constrained()`) are cleaned only by this detach; there is no DB-level fallback for them.
- Use the opt-in trait `BlackParadise\LaravelAdmin\Infrastructure\Persistence\Concerns\AtomicSoftDeletes` **instead of** `SoftDeletes` on hosts
  with an owned cascade to make `forceDelete()` atomic (one transaction on the model's connection; cancelled/failed deletes roll the cascade
  back). Note `forceDeleted` fires before commit. A host that defines its own `forceDelete()` loses atomicity.

**Audit hint (WRP-style apps):** search all `->owns()` usages: BelongsTo (e.g. `publication_id` embedded subtype records),
HasMany (`merge` strategy lists), HasOne (`contactInfo`). Each host-delete now triggers deletes on those records; check child
`deleting`/`deleted` listeners and that no unrelated rows are reachable.

**Verify:** delete a host with children in a test: children rows, their files and pivots are gone; soft-delete leaves them;
`bpadmin:doctor`.

**Ref:** CHANGELOG laravel-admin 3.1.0.

### U-12 — Events after commit; flash on next request

**Applies if:** `grep -rn 'Listener\|Event::listen' app/` for BPAdmin domain events.

**After:** events are recorded in the write scope, dispatched after commit, and discarded on rollback; outside a scope they
dispatch immediately. A listener that relied on seeing uncommitted data inside the same transaction must be updated.
Flash messages for responses that stream a download appear on the next page request.

**Verify:** a failing save emits no event; a successful one emits after commit.

### U-13 — Boot validations throw; `bpadmin:doctor`

**Applies if:** always (all definitions).

**After:** booting now throws `InvalidDefinitionException` for: a model-less definition that is writable, a relation target or
an embedded child; a searchable/sortable/filterable field whose type cannot be queried; an unregistered embedded definition
class. Apps that booted before may now fail to boot **on web requests**, and the failure is app-wide (frontend, API, queue
workers served over HTTP, not only `/admin`). Console commands keep working: in console the boot does NOT throw, so
`migrate`, `config:cache` and `bpadmin:doctor` stay reachable. Run `php artisan bpadmin:doctor` (add `--json` for machine output):
it lists every violation (definition · field/class · rule · message) and exits **0** when clean, **1** otherwise.

Production: when a `bpadmin:cache` manifest exists, boot validation is skipped entirely (no per-request walk). `bpadmin:cache`
itself runs the validator and refuses to write the manifest on violations (lists them, exit **1**), so deploy `bpadmin:cache`
after `bpadmin:doctor` is clean. Without a manifest (dev) web boot validates as before.

After every definition change, re-run `php artisan bpadmin:cache` (a stale manifest skips boot validation, so new violations
stay hidden) and `php artisan bpadmin:doctor`.

**Verify:** `php artisan bpadmin:doctor` exits 0.

### U-14 — Views read `$ui`; republish views and lang

**Applies if:** `ls resources/views/vendor/bpadmin lang/vendor/bpadmin resources/lang/vendor/bpadmin` (the lang path depends on your Laravel skeleton) (any published copy shadows the package).

**After:**
- Published blade views reading `$definition->isCreatable()` or showing unconditional edit/delete/bulk controls are stale.
  Republish: `php artisan vendor:publish --tag=bpadmin-views --force` (re-apply your customisations afterwards via diff).
  The `@js` escaping fixes in `theme-toggle` and `translatable` are only effective after republishing.
- Language files: the package lang path is `bp-laravel-admin/resources/lang/{en,uk}`; a published copy lives in
  `lang/vendor/bpadmin/{en,uk}` or `resources/lang/vendor/bpadmin/{en,uk}` and shadows it. New keys are missing from stale copies:
  files `actions.php`, `ui.php`, `validation.php` and additions to `auth.php`, `common.php`.
  **Do NOT run `vendor:publish --tag=bpadmin-lang --force` on an app with customised translations**: the package also ships
  `entities.php` (and the app may have `menu.php`), and `--force` would overwrite your entity labels (e.g. a 150-key `uk/entities.php`).
  Safe procedure: back up your lang directory, then copy only the new/changed files from
  `vendor/black-paradise/laravel-admin/resources/lang/{en,uk}/` (`actions.php`, `ui.php`, `validation.php`) and merge the missing keys of
  `auth.php` / `common.php` by hand (use the `diff -r` from **Verify** below). Never overwrite `entities.php` or `menu.php`.

**Verify:** `diff -r vendor/black-paradise/laravel-admin/resources/lang lang/vendor/bpadmin` (and the same against `resources/lang/vendor/bpadmin`) shows no missing keys; remaining differences are only your own entity/menu labels. The index page shows no raw
`bpadmin::...` keys.

**Ref:** U-06, U-15.

### U-15 — Republish assets

**Applies if:** `ls public/vendor/bpadmin` (the shipped bundle is published there from `public/bpadmin` of the package).

**After:** `php artisan vendor:publish --tag=bpadmin-assets --force` (and `--tag=bpadmin-views --force`, see U-14).
A stale bundle shadows the fixes (repeater focus after `$nextTick`, remove-file checkbox, actions UI).

**Verify:** `cmp public/vendor/bpadmin/bpadmin.js vendor/black-paradise/admin-blade-ui/public/bpadmin/bpadmin.js` (and `bpadmin.css`) are identical.

### U-16 — `requiredOnCreate()`

**Applies if:** `grep -rn "File.*required()\|Image.*required()" app/BPAdmin`.

**Before:** `ImageField::make('cover')->required()` made every update without a re-upload fail with 422.

**After:** use `->requiredOnCreate()` (rule `required` on create, `nullable` on update). `{field}__clear=1` (Remove-file checkbox) clears an
optional file field; it is rejected with 422 on `required()` / `requiredOnCreate()` fields; a new file plus `__clear` keeps the new file.

**Verify:** update without a file: 200; create without a file: 422.

### U-17 — Translatable search / sort; PostgreSQL 16+

**Applies if:** `grep -rn 'TranslatableField' app/BPAdmin` with `->searchable()` or sorting.

**After:** search is case-insensitive, per configured locale (`config('bpadmin.locales')` or discovery), the JSON path is a bound
parameter, `%`, `_` and the escape char `!` match literally (`ESCAPE '!'`), applied to plain search too, and legacy non-JSON values fall back to plain
case-insensitive LIKE. Zero locales → no rows. **PostgreSQL < 16 is not supported for translatable queries** (`pg_input_is_valid`
fails every row). MySQL, SQLite and PG16+ are supported.

**Verify:** `?q=whitney` and `?q=WHITNEY` return the same row; `?q=uk` does not match every row.

### U-18 — Definition-specific `mutator()` is not decorated

**Applies if:** `grep -rn 'app->extend(EntityMutatorInterface\|extend(EntityMutatorInterface' app/ packages/`.

**After:** `app->extend(EntityMutatorInterface::class, ...)` decorates the **default** mutator only. A definition that returns its own
`mutator()` class-string is resolved directly and bypasses the decorator. Apply the cross-cutting behaviour inside that mutator or
decorate the use cases.

**Verify:** a test asserting the decorator runs for your definitions.

### U-19 — Default `searchFields()` and the `q` parameter

**Applies if:** `grep -rn '\->searchable()' app/BPAdmin` and definitions without a `searchFields()` override.

**Before:** `->searchable()` only enabled filtering; `searchFields()` defaulted to `[]` so search was off unless overridden.

**After:** default `searchFields()` is derived from `AbstractField`s marked `->searchable()` (in field order); an explicit override still
wins. **Behaviour change:** definitions with searchable fields and no override now have search ON. Only `AbstractField` subclasses can
be searchable (`isSearchable()` is not on `FieldContract`); a custom `FieldContract` implementation outside `AbstractField` must be
listed in an explicit `searchFields()`. The query parameter is `q` (`EntityIndexRequest`).
`->searchable()` still also calls `filterable(true)` for BC.

**Verify:** `GET /admin/{entity}?q=term` filters; remove `->searchable()` from fields you do not want searched.

### Known limits

- Core still references model class-strings through `FieldContract::pointsBackTo` and `RelationFieldContract::target()`.
- Model-less definitions cannot be relation targets or embedded children.
- PostgreSQL < 16 has no non-JSON guard for translatable queries (U-17).
- Definition-specific mutators bypass `app->extend()` decoration (U-18).
- `CriteriaApplier` does not serve custom repositories; they implement `list(Criteria)/find/exists` themselves.
- (blade-ui) `bp_locale()` keeps the resolved `LocaleProviderContract` in a function-level `static`: under Octane / long-lived
  workers the provider instance (and anything it captured) is not re-resolved per request.
- `AtomicSoftDeletes` only makes `forceDelete()` atomic; `forceDeleted` fires before commit.
