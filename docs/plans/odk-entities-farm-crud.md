# Plan: Farm CRUD on ODK Central Entities (generic entity framework, take 1)

**Status: In Progress** — Phase 0 through Phase 4 (List, Create, Update, Delete, Import farms only) are implemented and **confirmed working against a real ODK Central server**, feature-parity confirmed against the old page for both import flows. Phase 5 (combined locations+farms import wizard) is implemented, passing `phpstan`/`pint`/the test suite, not yet tested.

### Phase 3 extended: soft-delete/restore UI, synced with ODK Central's own soft-delete

The original `FarmResource` (database-backed) never supported soft-delete. `FarmEntityResource`'s Delete action already soft-deleted both locally and on Central (`FarmEntity` uses `SoftDeletes`; `deleteFarm()` already called Central's `DELETE` entity endpoint, which is itself a soft-delete), but the table had no way to view trashed records or restore them. Added:
- `OdkDatasetService::restoreOdkEntity()` (package) - `POST .../entities/{uuid}/restore`, Central's dedicated restore endpoint.
- `OdkFarmEntityService::restoreFarm()` - restores on Central first, then locally, mirroring `deleteFarm()`'s Central-then-local ordering (so a failed Central call doesn't leave local/Central out of sync).
- `FarmEntityResource::table()` - added `Filament\Tables\Filters\TrashedFilter` (handles the with/without/only-trashed toggle and its own soft-delete scope removal, nothing else needed) and `Filament\Actions\RestoreAction`, wired to `restoreFarm()`. Both `DeleteAction`/`RestoreAction` already auto-hide/auto-show based on `$record->trashed()` out of the box - no extra visibility logic needed.

Force-delete was deliberately not added - Central's Entities API has no permanent-delete endpoint to mirror, and it wasn't asked for.

Confirmed working (cross-checked in ODK Central directly) - both soft-delete and restore correctly sync. Follow-up: `EditAction` has no built-in trashed-awareness (unlike Delete/Restore), so a soft-deleted farm could still be edited - fixed with an explicit `->hidden(fn ($record) => $record->trashed())`.

### Revisited: can farm_entities be removed? (still no, as of Parts A/B/C)

Raised a second time after Parts A/B/C landed - table is now down to `id`, `owner_id`, `location_id`, `team_code`, `odk_uuid`, `odk_version`, soft-delete. Dan confirmed there's no new consideration prompting this beyond wanting to check whether it'd get written down; conclusion from `docs/prompts/why-local-table-for-externally-stored-data.md` still stands unchanged: Filament's Create/Edit/Delete/route-binding lifecycle is Eloquent-typed at its core (verified against vendor source, not just docs), a future `FarmSurveyData` database-level FK needs a real local row regardless of Filament, and live-API import dedup would be both slower and less correct than the current indexed local query. Every remaining column has one of those concrete justifications - nothing left to cut without hitting one of those three walls.

### Location "# of Farms" count switched to farm_entities

While investigating a reported farm-count bug on the "Clusters"/"Groups" location-level pages (root cause: unrelated stale-cache issue in `Location::farmsAllCount()`'s `Cache::remember()` invalidation - not fixed, kept as-is per Dan's direction), Dan asked for the "# of Farms" column itself to count `farm_entities` instead of the legacy `farms` table, since that's now the actively-used farm CRUD. Added `Location::farmEntities(): HasMany` and switched `farmsAllCount()`'s base count from `$this->farms->count()` to `$this->farmEntities->count()`. The three sibling completion-count attributes (`farmsHouseholdCompleteCount`/`farmsFieldworkCompleteCount`/`farmsAllCompleteCount`) were **not** changed - they depend on `household_form_completed`/`fieldwork_form_completed`, columns that don't exist on `FarmEntity` (that submission-completion-tracking logic hasn't been ported over - a Phase 6 cutover concern, not done here). Cleared the app cache once so stale farms-table-based counts don't linger during testing (the cache mechanism itself is unchanged).

### Layout parity with the old page

After comparing the two pages side by side, Dan asked for `ListFarmEntities` to match `ListFarms`'s layout: breadcrumbs (Survey Dashboard > Survey locations > Farms), page heading ("Survey locations", not "Farms (ODK Entities - preview)"), and the "Instructions" panel in the top-right, which was missing entirely. Fixed by copying `getBreadcrumbs()`/`getHeading()` from `ListFarms` and reusing `FarmResource\Widgets\FarmListHeaderWidget` as-is via `getHeaderWidgets()` - that widget has no `Farm`-model-specific logic, it just renders a translation-file-driven instructions panel.

`FarmEntityResource::getPluralModelLabel()` also returned `"Farms (ODK Entities)"`, which - since neither the resource nor `ImportLocationsAndFarmEntities` override breadcrumbs - is what Filament's default breadcrumb auto-generation (cluster label + resource plural label + page title) was pulling from, producing "Location Levels > Farms (ODK Entities) > Import Locations and Farm List" instead of the desired "Location Levels > Farms > Import Locations and Farm List". Fixed by changing it to plain "Farms", matching `FarmResource`. Confirmed (via `grep`) it was the only occurrence of that string in the new resource's files.

### Navigation retargeted to the new page

Per Dan's direction, the original `FarmResource` stays in place (routes, pages, everything) for future comparison, but is no longer linked to from anywhere in the UI. Two call sites found (only two - confirmed by search) and retargeted to `FarmEntityResource`:
- `LocationLevelResource::getNavigationItems()` - the "Farms" sidebar nav item under the LocationLevels cluster.
- `resources/views/filament/app/pages/survey-locations/survey-locations-index.blade.php` - the "List of farms" card on the Survey Locations index page.

`FarmEntityResource` itself is still `shouldRegisterNavigation = false` - these are hand-built links, not Filament's automatic nav registration, so that flag doesn't need to change. The old `FarmResource` route (`location-levels/farms`) still works if visited directly by URL; it's just unreachable via any in-app link now.

### Phase 5 (combined Import Locations and Farm List wizard) implementation notes

New `ImportLocationsAndFarmEntities` page mirrors `FarmResource\Pages\ImportLocationsAndFarms` almost exactly - the 3-step wizard (upload, map to location hierarchy, map to farm columns) is entirely about spreadsheet/location parsing, independent of storage backend, so it's copied with only two changes: the farm half of `save()` dispatches `FarmEntityImport` instead of `FarmImport`, and it redirects to `FarmEntityResource::getUrl('index')`. Locations stay fully local either way - unchanged. Reuses the original page's Blade view directly (it's generic form+actions boilerplate, not worth duplicating). New route registered as `farm-entities/import`; header button added to `ListFarmEntities` mirroring `ListFarms`'s equivalent, pointed at the new route via `FarmEntityResource::getUrl('import')` rather than the old page's hardcoded relative URL string.

### Phase 4 (Import farms only) implementation notes

Reuses the existing `ImportFarmsAction` modal (column-mapping UI) unchanged - it's purely about parsing an uploaded spreadsheet and is independent of storage backend. Only swapped which import class it dispatches (`->use(FarmEntityImport::class)` instead of `FarmImport::class`).

New `FarmEntityImport`/`FarmEntitySheetImport` mirror `FarmImport`/`FarmSheetImport`'s structure (same validation rules, same queued/chunked/notification pattern) but hand valid rows to a new `OdkFarmEntityService::bulkCreateFarms()` instead of inserting into a local table directly. That method:
- Skips rows whose `team_code` already exists for the team (same dedup rule as before), and also dedupes repeats of the same `team_code` *within* the uploaded file itself (the old row-by-row insert loop got this for free by checking against already-inserted rows; a batch approach doesn't unless done explicitly).
- Reconciles every identifier/property key used across the whole batch once, rather than once per row.
- Generates each entity's UUID client-side before creating it locally, then includes that UUID in Central's bulk-create payload - avoids needing to match the bulk response back to rows by position/order (which wasn't confirmed reliable).
- Wraps the batch in a transaction so a failed bulk API call doesn't leave locally-created rows Central never actually received.

**Known gap carried over from the old code, not addressed here:** the queued job resolves the team via `Team::findOrFail($this->data['owner_id'])` (captured at form-submission time) rather than `HelperService::getCurrentOwner()`, since a queued job runs with no Filament tenancy context - this matches how `FarmSheetImport` already worked, not a new limitation.

**Cosmetic-only gap:** the `Import` audit record `ImportFarmsAction::importData()` creates is hardcoded to `model_type => Farm::class` regardless of which import class is actually used - harmless for behaviour, just slightly misleading if someone reviews that record for an entity-backed import.

### Bug found during Import testing: bulk-create requires `source`, unlike single-entity create

The import ran without a visible error to the end user, but created nothing locally or on Central - the `imports` table's `errors` column had `HTTP 400: Required parameter source missing`. Cause: `OdkDatasetService::bulkCreateOdkEntities()` treated `source` as optional and used `array_filter()` to drop it when no name was given - but Central's bulk-create endpoint requires it (unlike single-entity create, which doesn't). Fixed by always sending `source.name`, defaulting to `'Bulk import'` when the caller doesn't supply one; `bulkCreateFarms()`/`FarmEntitySheetImport` now thread the uploaded file's name through as that source name. Confirmed the failed attempt left no orphaned local rows, since the whole batch is wrapped in a DB transaction that rolled back with the API call.

### Pre-existing bug found while testing (unrelated to this feature, fixed anyway since both import flows share the action)

While testing the *old* Farm import for an apples-to-apples comparison, Dan hit a `TypeError` in `ImportFarmsAction`'s file-upload `afterStateUpdated` closure - it declared `?TemporaryUploadedFile $state` but was invoked with a plain string. Cause: `$state` is only a `TemporaryUploadedFile` on the initial upload event; a later Livewire re-render of the field (e.g. after a validation error elsewhere in the form) passes back the already-stored path as a string instead, and the closure's strict type didn't allow for that. Not introduced by this branch's work, but shared by both the old and new import actions, so fixed in place: the closure now accepts an untyped `$state` and returns early unless it's actually a `TemporaryUploadedFile`.

The same bug existed a second time in `FarmResource\Pages\ImportLocationsAndFarms` (the combined wizard has its own separate `FileUpload` field, not routed through `ImportFarmsAction`) - hit when Dan tested the *original* combined-import button for comparison. Fixed identically. `ImportLocationsAndFarmEntities` (this branch's new combined-wizard page) already had the fix from the start, since it was written after the first occurrence was found.

### Phase 3 (Delete) implementation notes

`OdkFarmEntityService::deleteFarm()` soft-deletes the entity on Central via `deleteOdkEntity()`, then soft-deletes the local `FarmEntity` row (kept, not hard-deleted, for future FK references once this is wired into `FarmSurveyData` at cutover). Wired into the table's `DeleteAction` via `->action()`, overriding Filament's default plain-Eloquent-delete behaviour while keeping its confirmation modal and notification.

### Bug found during Delete testing: restoring on Central collided with the local soft-delete

Restoring a deleted entity directly in Central's UI made the list page throw a duplicate-key error on `farm_entities.odk_uuid`. Cause: `refreshFromCentral()`'s lookup query didn't include trashed `FarmEntity` rows (Eloquent's default `SoftDeletes` scope excludes them), so it treated the now-active-again uuid as brand new and tried to `INSERT` a fresh row with the same `odk_uuid` the trashed row already held.

Fixed by looking up `withTrashed()` and calling `->restore()` on a matched trashed row before continuing.

**Flagged, not fixed (out of scope for this bug):** the reverse case - an entity deleted directly on Central (bypassing this app's Delete action) - isn't mirrored locally; the local `FarmEntity` stays active since it's simply absent from the feed and nothing currently reacts to that. A full fix needs to also confirm the OData feed isn't paginated first (soft-deleting every local farm not seen in a partial page of results would be a worse bug than the one it fixes).

### Phase 2 (Update) implementation notes

`OdkFarmEntityService::updateFarm()` uses `odk_version` as `baseVersion` for optimistic concurrency against Central, and removes any identifier/property key the user deleted from the KeyValue fields (`entity->values()->whereNotIn(...)->delete()` before re-upserting).

The identifiers/properties split doesn't survive a round trip through Central on its own - Central only stores flat property data, no PII/non-PII distinction. Fixed by tagging each `DatasetVariable` with `description` = `'identifier'` or `'property'` when it's first reconciled (`reconcileProperties()` now takes `[rawKey => type]` instead of a plain key list), and `getEntityData()` uses that tag to reconstruct the two KeyValue fields when editing. Properties discovered from entities created outside this app (the adopt path) default to `'property'` since there's no way to know their PII status.

### Bug found during Update testing: OData's `label` field leaking into entity data

Editing *any* farm (including one created through this app's own Create page) failed with `400: You specified the dataset property [label] which does not exist`. Cause: `refreshFromCentral()`'s system-field filter only excluded `__`-prefixed keys, but Central's OData feed returns each entity's `label` field unprefixed. It got auto-registered as a fake local property (via the adopt path's `ensurePropertyRegistered()`), pulled into the Properties KeyValue field on Edit, and then included in the `data` object on save - which Central rejects since `label` is a reserved top-level field, not a data property.

Fixed by excluding a `RESERVED_ODATA_KEYS` set (`label`, `geometry`) in addition to the `__` prefix. This was local-only contamination - `ensurePropertyRegistered()` never pushes to Central, so nothing needed fixing server-side - but the bogus local `DatasetVariable` + its `EntityValue` rows (7, across all 4 discovered farms + the app-created one) were manually cleaned up in Dan's dev DB via tinker.

### Second bug found during Update testing: deleting a KeyValue row didn't clear it on Central

Removing rows from the Properties KeyValue field and saving made them disappear locally, but they were still present in Central's entity, and reappeared after `refreshFromCentral()` ran again on the next Edit visit. Root cause, confirmed against ODK's own docs: Central's entity `PATCH` **merges** the given `data` with the entity's existing data - omitted properties keep their old value. Central's own guidance: *"To unset the value of any property, you can set it to empty string."*

Fixed `updateFarm()` to compute which property names were previously set **on this specific entity** (not the whole shared dataset - it's shared across teams, so blanking every dataset property would wrongly touch other teams' farms too) and explicitly send `""` for any that are no longer submitted. That part stays.

The accompanying `getEntityData()` change (hiding empty-string values so a cleared key disappears from the KeyValue UI) was reverted per Dan's direction - all properties should show, including empty ones. Deferred: once cleared, a key currently reappears in the Edit form with a blank value rather than looking fully removed. Revisit later (options include: an explicit "empty vs. never-set" indicator, or accepting that ODK Central's model has no true "property absent" state, only "empty value").

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

## Deferred UX feedback from testing

- **Create form fields don't match the Farm Registration form's fields.** Dan noted after testing Create that the free-form identifiers/properties KeyValue UI doesn't match the fixed fields (household_id, participant_name, GPS, loc1/loc2, etc.) that the real Farm Registration XLSForm collects. Deferred by Dan's own call - relevant once cutover (Phase 6) is closer, likely resolved by driving the Create form from the resolved entity list's actual `DatasetVariable`s (the same ones a registration-form-submitted entity already carries) rather than a free-form KeyValue widget.

## Known gaps / deliberate deferrals

- GPS coordinates are stored locally only; not yet synced to Central's entity geometry property.
- No conflict-resolution UI — conflicts are left to Central's own web UI for now (per earlier discussion).
- No backfill of existing farms yet — Phase 1 testing is against freshly-created `FarmEntity` records, not historical data.
- No bulk-update endpoint exists on ODK Central; `updateEntity` will need `baseVersion` optimistic concurrency (Phase 2).

## Verification

- `./vendor/bin/phpstan analyse` and `./vendor/bin/pint` after each phase.
- Each phase's functionality is manually tested by Dan against a real ODK Central project before moving to the next phase.
