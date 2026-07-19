# Code Review: map-location-to-entity-list-attribute

**Date**: 2026-07-19
**Branch**: `map-location-to-entity-list-attribute`
**Reviewer**: Claude (automated multi-angle review)
**Scope**: 36 files, 3,133 insertions / 468 deletions vs `dev` — farm-entity → ODK Central entity-list location attribute mapping (`loc{n}` triplets keyed by `locations.id`), GPS unification into a single `geometry` property, team-scoped `farm_entities` datasets, split `LocationsModuleBuilder`/`FarmInfoModuleBuilder` replacing `LocationSectionBuilder`, import-flow GPS support and tenancy scoping, plus a `filament-odk-link` bump (d474cfc → d201575) adding `can_be_replaced` module swapping.

## Verification results

- **Tests**: full app suite passes (125 tests, 225 assertions). The three new `OdkFarmEntityService*` test files and the two builder test files all pass.
- **Package tests**: `XlsformSyncWithTemplateTest` — the headline `can_be_replaced` test is failing *as committed* (see F1). Confirmed via the committed `.phpunit.cache/test-results` at d201575 (status 7 defect) and by code trace.
- **PHPStan**: no new real errors except one `argument.type` in `LocationsModuleBuilder:117`. The reported `Xlsform::localiseModules()` undefined-method error is a stale result-cache false positive — a clean run on that file passes.
- **Pint**: fails on 3 changed files — `XlsformsRelationManager.php`, `Team.php`, `FarmInfoModuleBuilder.php`.

## Major findings

### F1. Package: the `can_be_replaced` swap test fails as committed
`packages/filament-odk-link/tests/Unit/Models/XlsformSyncWithTemplateTest.php:73` creates a `can_be_replaced` module and expects `syncWithTemplate()` alone to leave only "Local Locations" attached. But `syncWithTemplate()` only `firstOrCreate`s a local version in the `can_be_extended` branch; `localiseModules()` bails (`if (! $localModuleVersion) return;`) because nothing created "Local Locations". The global version stays attached and the assertion fails. Either the test must pre-create the local version (documenting that the swap depends on the host app's builders), or the `can_be_replaced` path should also `firstOrCreate` the local version.

### F2. Package: every template re-sync re-attaches the global version and resets custom module order
Builder-created local versions have `xlsform_module_id = NULL` (`firstOrCreate` on `owner_id` + `name` only), so `syncWithTemplate()`'s "already linked" guard (`doesntContain('xlsform_module_id', $module->id)`) never matches a swapped module. Each re-sync re-attaches the global default at `default_order`, and `localiseModules()` re-swaps it back — clobbering any custom pivot ordering, which is exactly what that guard's comment says it exists to preserve. Fix: set `xlsform_module_id` on the local version when creating/swapping it.

### F3. Farm-info calculate rows can collide with Locations-module field names → deploy failure
`refreshFromCentral()` registers every unknown Central property via `ensurePropertyRegistered()` with `description: 'property'` — including `loc1`, `loc1_name`, `loc1_type`, `team_code` for entities registered directly through the Farm Registration form (only app-created farms get these tagged `'loc'` via `reconcileProperties()`). `FarmInfoModuleBuilder::variables($dataset, 'property')` then emits `calculate` rows named `loc1`/`loc1_name` that duplicate the Locations module's fields in the same survey sheet; pyxform rejects duplicate names, so `deployDraft` fails. Path: team adopts Enketo-registered farms → any farm-list refresh → next deploy produces an invalid XLSForm. The old `farm_` prefix (dropped per docs/issues/farm-info-module-builder-test-drift.md) previously made this collision impossible — evidence the prefix was load-bearing.

### F4. Legacy-farm GPS data loss via empty-string clobber in `getEntityData()`
`updateFarm()`'s clearing loop (OdkFarmEntityService.php:763-767) writes `''` to the four legacy GPS properties on a legacy farm's first edit. On a later `getEntityData()`, the legacy-field branch (line 680-684) assigns `$gps[$name] = $value` with no emptiness check *after* `geometry` has been parsed — key iteration order of Central's JSON decides whether `''` overwrites the parsed values (with Central's Postgres jsonb ordering, `latitude`/`longitude` sort after `geometry`, so it does). Edit form then shows blank lat/long; re-saving clears `geometry` on Central. Scenario: legacy farm → edit once (GPS migrated) → open edit again → blank GPS → save → GPS gone. Fix: skip empty legacy values, or prefer `geometry` when present.

### F5. `'loc'`-tagged variables leak into the user-editable properties UI and farm-list columns
`getEntityData()` only excludes `team_code`, `geometry`, and the legacy GPS fields; `loc1`/`loc1_name`/`loc1_type` fall through into `properties` and appear as editable rows in EditFarmEntity's KeyValue and as extra farm-list columns (FarmEntityResource.php:115-117), duplicating the dedicated Location column/select. Worse: they are resubmitted as `'property'`-typed keys on every save, and if the level hierarchy is later shortened, a stale deepest `loc{n}` survives the clearing loop and `resolveLocationFromAttributes()` resolves to the old location. `FarmInfoModuleBuilder::variables()` already filters by the `'loc'` description — `getEntityData()` and the table columns should too.

### F6. `deployDraftForms()` early return blocks farm-driven schema changes
The new `if ($xlsformsToUpdate->count() === 0) return;` means dataset-schema changes that don't touch locations never reach forms: importing farms with a new identifier column creates the DatasetVariable (needing a new `calculate` row in "Local farm info"), but nothing farm-side sets `draft_needs_update`, so deploy is a no-op until an unrelated location edit flips a flag. On `dev`, `localiseXlsforms()` ran unconditionally here. Consider having `FarmEntity`/DatasetVariable changes also flag `draft_needs_update`, or not gating `localiseXlsforms()` behind the early return.

### F7. Stringly-typed builder ↔ module-name contract with silent no-op
The swap looks up `'Local ' . $xlsformModule->name`; the builders hardcode `'Local locations'` / `'Local farm info'`. Modules are admin-created (not seeded), the admin form hints at snake_case names, and the branch's own reference CSV uses `farm_info`. A mismatch (`Locations`, `farm_info`) makes `localiseModules()` return silently — team keeps the global module, no error, no test crossing the app/package boundary to catch it. The lower-case-alignment commit fixed only the locations side by convention. Recommend: seed/pin the module names or match case-insensitively, and add a cross-boundary test.

### F8. Deleted locations persist in choice lists
`LocationsModuleBuilder::buildChoiceLists()` only `updateOrCreate`s entries for current locations; `deleteStaleChoiceLists()` drops whole lists above `maxPos` but never removes entries for deleted `Location` rows. Enumerators can select a location that no longer exists, producing dangling ids in submissions. Tests pin stale-*level* cleanup but not stale-*entry* cleanup.

### F9. Import: GPS columns silently lose or fabricate data
- No numeric/range validation on auto-detected GPS columns (`FarmEntityImport.php:85-107`): `(float)` casts non-numeric cells ("unknown", formula-returned `''`) to `0.0`, pushing geometry `"0.0 …"` to Central; latitude 100 imports fine while the manual form enforces −90..90. `rules()` has what it needs to validate these columns.
- GPS-named columns are stripped from identifiers/properties even when no geometry can be built (`FarmEntityImport.php:73-83`): a Latitude column ticked without a Longitude column vanishes entirely and the import reports success (before this branch those values were kept as identifiers).

## Minor findings

- **M1** `matchLocationById()` digits-only guard (OdkFarmEntityService.php:137-155): a purely numeric legacy location *code* (e.g. `"12"`) passes `ctype_digit`, is treated as a primary key, and can silently match the wrong location; the name fallback never runs because the id match succeeded.
- **M2** `loc0` crashes resolution (OdkFarmEntityService.php:152-191): the pos filter is `<= $chain->count()` with no lower bound; a Central property named `loc0` gives `max() = 0`, `$chain->values()->getOn(-1)` → null → fatal inside `refreshFromCentral()`, breaking the farm-list page mount.
- **M3** `matchLocationByName()` ignores ancestor names: duplicate leaf names under different parents (two villages "Santa Cruz") resolve to an arbitrary `first()`.
- **M4** N+1s: `refreshFromCentral()` re-runs `farmLevelChain()` per unresolved entity and fires a Team `UPDATE` per adopted/changed entity via the new `saved` hook (hundreds of queries per farm-list mount); `LocationsModuleBuilder::labelProperties()` queries locales per location (thousands of identical queries per populate) — same code is duplicated verbatim in both builders.
- **M5** Concurrency: `refreshFromCentral()` unguarded — two simultaneous list mounts race on `FarmEntity::create` for the same `odk_uuid` (unhandled QueryException for the loser); `ensureDataset()`'s `firstOrCreate` has the same window.
- **M6** `team_code` label sync: no uniqueness guard (no unique index on owner_id+team_code) — two Central entities sharing a label produce duplicate local team_codes, breaking `bulkCreateFarms()` dedup; and a Central label of `''` is adopted as-is (`$label ?? $uuid` only catches null).
- **M7** Per-team dataset switch (no data migration, per Dan): two unhandled local consequences — collision-suffixed property names (`x_2`) from the old shared dataset regenerate as `x` in the fresh dataset (new writes target a different Central property than old data), and identifier/property tags are lost on re-adoption (previously-identifier PII fields come back as `'property'`, changing form bucketing and what FarmInfoModuleBuilder treats as identifiers).
- **M8** `LocationLevel::farmLevelChain()` vs tenant global scope: called with a `$team` different from the current Filament tenant, the scope ANDs `owner_id = tenant` with `owner_id = $team->id` → empty chain → silent null resolution. Also `first()` on multiple `has_farms` levels is arbitrary; `while ($level->parent)` has no cycle guard. Both builders also assume a single chain: `orderedLevels()` keys by pos (two same-depth levels silently overwrite), and `FarmInfoModuleBuilder` derives depth from `count()` instead of max pos — a non-chain hierarchy yields a `choice_filter` referencing a field the Locations module never created (pyxform "unknown reference").
- **M9** `FarmInfoModuleBuilder` hardcoded `participant_sex/name/age` backfill: creates `'identifier'` DatasetVariables that surface on every team's farm CRUD form even if the team never defined them, are never pushed to Central, and read a CSV column that may not exist; `create()` omits `type` where every other path sets `'string'`; the note renders raw `participant_sex:` as user-facing text.
- **M10** Vacuous test: FarmInfoModuleBuilderTest.php:101 asserts `firstWhere('name', 'farm_certificate_no')` is null — a name that never exists post-prefix-removal — so it passes even if `deleteStaleCalculateRows()` is deleted. The drift issue (docs/issues/farm-info-module-builder-test-drift.md) is only partially resolved; its open question (was dropping the prefix a regression?) is answered by F3: yes, partly.
- **M11** Test gap: id-first matching — the branch's headline behavior (commit 4c09c14) — is effectively unpinned. No test feeds a `loc{n}` id that disagrees with the name, covers the `ctype_digit` guard, or rejects another team's id; the round-trip test passes even if `matchLocationById()` always returns null. Also `Http::preventStrayRequests()` is missing in the HTTP-faked tests, and OdkFarmEntityServiceLocationTest hardcodes location ids `'1'/'2'/'3'` against the auto-seeded DB.
- **M12** Import altitude `(int)` cast truncates decimals (matches the service signature, but silent precision loss at the boundary).
- **M13** (pre-existing, adjacent) `ImportLocationsAndFarmEntities::save()` queues `LocationImport` and `FarmEntityImport` concurrently; with multiple Horizon workers on one queue, farm chunks can validate `Rule::exists('locations','code')` before the location chunks commit → spurious whole-import failures. Only safe with a single FIFO worker.
- **M14** (pre-existing, adjacent) `owner_id`/`user_id` ride client-mutable hidden Livewire fields into the imports; the branch's tenancy scoping anchors to this tamperable value instead of re-resolving server-side.

## Nits

- Pint fails on `XlsformsRelationManager.php`, `Team.php` (`if($xlsformsToUpdate…` missing space; `isEmpty()` is the idiomatic check), `FarmInfoModuleBuilder.php` (argument spacing/indentation). PHPStan: `LocationsModuleBuilder.php:117` puts `int|null` into a `Collection<string,string>`.
- `app/Console/Commands/test.php` remains a committed scratch command hardcoding `Team::find(3)` — `populate(null)` TypeErrors on any environment without team 3.
- `owner->update()` in the new saved/deleted hooks fatals if the owning Team is soft-deleted; bulk/query-builder deletes bypass model events so the flag won't set.
- Stale comment: `createFarm()`'s "a plain 'property' tag is fine here" vs the code tagging `'loc'` (OdkFarmEntityService.php:486-488).
- `$altitude ?? 0` skips `formatGpsFloat()` — altitude alone is written without the decimal every other component carries.
- `notIn(['na'])` on the column-mapping selects makes a genuine spreadsheet column headed "na" unselectable.

## Clean areas

ODK row generation syntax (cascading `select_one` + `choice_filter` + `jr:choice-name`) is correct and lines up with `buildLocationAttributes()`'s `loc{n}` values; traversal ordering and zero-level/zero-variable cases are handled and tested. No remaining references to the deleted `LocationSectionBuilder`. Mass assignment is clean throughout (fixed key sets everywhere despite unguarded models). Import header matching is consistent and genuinely case-insensitive; the added owner+level scoping in both importers fixes real cross-team code-collision bugs. Local-row/Central write atomicity in `createFarm`/`updateFarm` is correct (Central call inside the transaction). `Team::localiseXlsforms()` flag handling survives a mid-populate exception. The GPS unit tests are strong (decimal-format regression, defaults, null-guards, round-trip).

## Suggested priority

1. F1 + F2 (package contract is broken as shipped; F2 silently corrupts form ordering on every template update)
2. F3 + F5 (same root cause: the `'loc'` tag isn't enforced on the read/registration path — fixes both the deploy failure and the UI leak)
3. F4 (GPS data loss for legacy farms)
4. F6 (deploys silently skipping schema changes)
5. F9 + F8 (import data quality; stale choices)
6. Everything else as follow-up.
