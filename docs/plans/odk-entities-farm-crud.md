# Plan: Farm CRUD on ODK Central Entities (generic entity framework, take 1)

**Status: In Progress** — Phase 0 (foundations) is implemented and confirmed working. Phase 1's List half is implemented and **confirmed working against a real ODK Central server** (correctly shows farms created via a registration form's `entities` sheet, not just ones created through this app). Phase 1's Create half is implemented but not yet tested. Update, Delete, and both import flows are not started. Each phase is meant to be tested by Dan before the next begins.

### Ready for testing (Phase 1)

New routes, auto-discovered, nav-hidden: `app/{tenant}/location-levels/farm-entities` (list) and `.../farm-entities/create` (create). Same permissions as the existing Farms page (`view list of farms` / `maintain list of farms`).

Two assumptions in the code are flagged with `NOTE:` comments in `OdkFarmEntityService` and have not been verified against a live server - worth watching for on first test:
1. The shape of the `createOdkEntity` response - assumed `currentVersion.version` for the version number used in future updates.
2. The `__id` key for an entity's UUID in the OData `.svc/Entities` feed used by `refreshFromCentral()`.

If either is wrong, the symptom will be an obvious one (create throws, or the list page shows no property values) rather than silent wrong data.

### Discovery during testing: entities created outside this app

Testing surfaced a design gap: the initial version of `refreshFromCentral()` assumed every farm entity on Central was created through this app's Create page. In practice a team's "Farm Registration" XLSForm can define its own `entities` sheet and create entities directly - Dan's test team already had 4 farms in Central from exactly this path, created before any `FarmEntity` row existed locally.

Fixed by making the read-through refresh **adopt** entities it doesn't recognise: for any entity in Central's feed with no matching local `FarmEntity.odk_uuid`, it now creates one on the fly (`location_id` left null - there's no way to infer an app-side location for an externally-created entity), auto-registers any of its properties as local `DatasetVariable`s (needed because `entity_values.dataset_variable_name` has a real FK to `dataset_variables.name`), and self-heals the local `OdkDataset` bookkeeping row if the entity list already existed on Central but was never recorded locally. `farm_entities.location_id` is now nullable and the list shows "Unknown (created outside app)" for those rows.

This also fixed `ensureOdkDataset()` for the same reason - it would otherwise try to `POST` a dataset that already exists once a farm is created through this app's Create page for a team that already has a form-created entity list.

### Second discovery: hardcoded entity list name was wrong, and collided with existing infrastructure

The list still didn't show anything after the adopt fix. Root cause: the actual ODK Central entity list name is `Farm_Summary` (from `templates_entity_lists.list_name` for the Farm Registration template), not the hardcoded `'farms'`. Checking this also surfaced a pre-existing `Dataset` row (id 3, name "Farms", `entity_model = App\Models\SampleFrame\Farm`, with a real 14-variable schema) from unrelated prior work on the entities-upload feature - and because MySQL's default collation is case-insensitive, `ensureDataset()`'s `firstOrCreate(['name' => 'farms', ...])` had been silently resolving to that same row instead of creating a new one.

Per Dan's direction: kept the two fully separate rather than reusing Dataset id 3. Fixed by:
- Renaming this feature's local Dataset to `farm_entities` (`OdkFarmEntityService::LOCAL_DATASET_NAME`) so it can never collide with "Farms" again regardless of collation.
- Adding `resolveEntityListName(Team $team)`, which looks up the real ODK Central entity list name per team via the team's active `Xlsform` → `XlsformTemplate` → `TemplateEntityList.list_name`, rather than hardcoding a name. `createFarm()` and `refreshFromCentral()` both resolve this per-team now and fail clearly (exception / no-op respectively) if a team has no active form with an entities sheet yet.

## Context

Farms are currently stored in the app's own `farms` table (`App\Models\SampleFrame\Farm`) and only loosely connected to ODK Central (a CSV export used for `select_one_from_file` lookups in XLSForms). The goal is to make ODK Central Entities the system of record for farm data, while keeping all existing Farm functionality working during the transition, and to build this in a way that generalises to future ODK-entity-backed data types (the `Dataset`/`DatasetVariable`/`Entity`/`EntityValue` models in `filament-odk-link` already anticipate this — see `Dataset.php`'s own code comment about farm-groups/farms/locations).

This plan was arrived at over two rounds of design discussion. Key decisions taken (see the discussion, not reproduced in full here):

1. **Property schema**: teams keep the existing free-form KeyValue UX for farm identifiers/properties. New keys are auto-reconciled into the ODK Central dataset's property schema (`DatasetVariable` rows + `POST .../datasets/{name}/properties`) rather than requiring a fixed schema up front.
2. **Read model**: no long-lived, potentially-stale local cache of farm data. Instead, a **read-through** pattern — on each view, the current entity data is fetched live from Central's OData `.svc` feed and used to refresh local `EntityValue` rows before rendering. This keeps the existing `Entity`/`EntityValue` model machinery usable (it currently assumes persisted values) while guaranteeing what's displayed reflects Central, not a background-synced copy.
3. **Implementation base**: build concretely for Farms first, but keep ODK-facing code (API calls, sync/property-reconciliation logic) in its own classes so it can be lifted into a generic package-level framework later, once a second entity type exists to prove what's actually reusable. Do **not** build generic Filament Resource/Table/Form base classes yet — that's premature with only one consumer.
4. **Structural link model**: optional per entity type. An entity type only gets a dedicated local Eloquent model (and migration) if it needs extra locally-joinable columns. Farms need one (`location_id`, `team_code`); a simpler future entity type could use the generic `Entity` row alone.
5. **New page location**: a new resource is added *alongside* the existing `FarmResource`, not replacing it, so both can run side by side during development/testing. `App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource`, slug `farm-entities`, nav-hidden — same visibility pattern as the current `FarmResource`. It is deliberately **not** wired into `FarmSurveyData` or any other existing Farm-referencing code yet; that wiring is a cutover-time concern (Phase 6), not part of building/testing the new CRUD mechanics.

### A bug found during implementation (flagged, not fixed here)

`Entity::booted()` ([Entity.php](../../packages/filament-odk-link/src/Models/OdkLink/Entity.php#L21-L34)) auto-generates a UUID and saves it to `$entity->uuid` when `dataset->primary_key === 'uuid'`. There is **no `uuid` column on the `entities` table**, in the package's migration or the app's copy — this code would throw a SQL error the first time it actually ran. It has presumably never fired because no `Dataset` in this app currently sets `primary_key = 'uuid'`. This plan avoids relying on that mechanism: the ODK Central entity UUID is stored on the new `farm_entities.odk_uuid` column instead, which is intentional application code rather than this latent package bug. Worth flagging upstream separately.

## Architecture

### New package code (`packages/filament-odk-link`)

- `src/Services/OdkLinkServices/OdkDatasetService.php` — new trait, composed into `OdkLinkService` alongside the existing `OdkProjectService`/`OdkFormService`/etc. Wraps the ODK Central Entities API: `createOdkDataset`, `getOdkDataset`, `addOdkDatasetProperty`, `createOdkEntity`, `updateOdkEntity`, `deleteOdkEntity`, `bulkCreateOdkEntities`, `getOdkDatasetEntitiesFeed` (the OData `.svc/Entities` read-through source). This is dataset-agnostic — it takes an `OdkProject` and a dataset name, nothing Farm-specific — so it's usable as-is by a future entity type without changes.

### New app code

- `app/Models/SampleFrame/FarmEntity.php` + migration — the structural link model: `owner_id`, `location_id`, `team_code`, GPS columns (kept local-only for now, not synced to Central — see Known Gaps), `odk_uuid`, `odk_version`, soft deletes. Linked to the generic `Entity` row via `Entity`'s existing `model()` morph (`FarmEntity::entity(): MorphOne`).
- `app/Services/OdkFarmEntityService.php` — app-level orchestration that is intentionally *not* generic yet: ensures the shared `farms` `Dataset` and each team's `OdkDataset`/Central entity list exist, reconciles new identifier/property keys into `DatasetVariable`s + Central properties, and drives create/update/delete/read-through against `OdkDatasetService`.
- `app/Filament/App/Clusters/LocationLevels/Resources/FarmEntityResource.php` (+ pages) — the new, nav-hidden resource.
- `app/Policies/FarmEntityPolicy.php` — mirrors `FarmPolicy`'s gate names (`view list of farms` / `maintain list of farms`) so the same permissions govern both pages during coexistence.

## Phases (each is a testable checkpoint)

- **Phase 0 — Foundations.** `OdkDatasetService`, `FarmEntity` model/migration, `OdkFarmEntityService` dataset/property provisioning. Not independently testable via UI.
- **Phase 1 — Create + List.** These have to land together — List has nothing to show without Create. New page shows farms created through it, with identifier/property columns and location, matching the old page's table shape.
- **Phase 2 — Update.**
- **Phase 3 — Delete.**
- **Phase 4 — Import farms only** (adapt the `FarmSheetImport` dedup logic to push via `bulkCreateOdkEntities`).
- **Phase 5 — Import locations + farms combined wizard.**
- **Phase 6 — Cutover.** Backfill existing `farms` rows into Central, wire `FarmEntity` into whatever `FarmSurveyData`/submission-processing needs, retire the old `FarmResource`/`Farm` model, surface ODK Central conflict state in the UI, and revisit extracting the generic package-level Filament base classes once a second entity type is on the roadmap.

## Known gaps / deliberate deferrals

- GPS coordinates are stored locally only; not yet synced to Central's entity geometry property.
- No conflict-resolution UI — conflicts are left to Central's own web UI for now (per earlier discussion).
- No backfill of existing farms yet — Phase 1 testing is against freshly-created `FarmEntity` records, not historical data.
- No bulk-update endpoint exists on ODK Central; `updateEntity` will need `baseVersion` optimistic concurrency (Phase 2).

## Verification

- `./vendor/bin/phpstan analyse` and `./vendor/bin/pint` after each phase.
- Each phase's functionality is manually tested by Dan against a real ODK Central project before moving to the next phase.
