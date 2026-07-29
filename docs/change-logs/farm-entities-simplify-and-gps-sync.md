# Change log: Simplify entity-list resolution, drop entity/entity_values persistence, close the GPS gap

Implements [the plan](../archive/plans/farm-entities-simplify-and-gps-sync.md), a follow-up to [odk-entities-farm-crud.md](../plans/odk-entities-farm-crud.md) (the main Farm-on-ODK-Central-Entities feature). Three simplifications identified after that feature shipped, all confirmed working end-to-end against a real ODK Central server.

## Part A — hardcoded `resolveEntityListName()`

`App\Services\OdkFarmEntityService::resolveEntityListName()` now returns `'Farm_Summary'` directly instead of resolving it per-team via `Xlsform` → `XlsformTemplate` → `TemplateEntityList.list_name` (every template checked used the same name; the `Team $team` parameter is kept, unused, so no call site needed to change). Removed the now-unused `Xlsform` import.

## Part B — stopped persisting farm content into `entities`/`entity_values`

- **New package method:** `Stats4sd\FilamentOdkLink\Services\OdkLinkServices\OdkDatasetService::getOdkEntity()` — a single-entity `GET` wrapper, mirroring the existing `createOdkEntity`/`updateOdkEntity` methods.
- **`OdkFarmEntityService`:** `createFarm()`, `bulkCreateFarms()`, `updateFarm()`, `getEntityData()`, `refreshFromCentral()` no longer read or write `Entity`/`EntityValue`. `getEntityData()` and `updateFarm()`'s "what changed" diff now call `getOdkEntity()` directly instead of relying on a local mirror; `refreshFromCentral()`'s signature changed from `void` to `array`, returning the fetched Central feed for the caller to use directly. `Dataset`/`DatasetVariable` (schema-level bookkeeping) are unaffected.
- **`FarmEntity` model:** removed the now-unused `entity(): MorphOne` relation.
- **`FarmEntityResource`/`ListFarmEntities`/`EditFarmEntity`:** the list table's dynamic property columns read from a `$livewire->liveFarmData` array (populated once per page load in `ListFarmEntities::mount()`) via Filament's `$livewire` closure injection, instead of `$record->entity->values`. `EditFarmEntity::mutateFormDataBeforeFill()` no longer does a whole-team refresh, since `getEntityData()` does its own single-entity live fetch.
- **Cleanup:** deleted 23 `Entity` rows (798 `EntityValue` rows) this feature had already created before the change - the tables themselves are untouched (shared with unrelated package functionality), just no longer written to by this feature.

## Part C — GPS migrated from local columns to Central-synced properties

- `OdkFarmEntityService::GPS_FIELDS` constant added (`['latitude', 'longitude', 'altitude', 'accuracy']`).
- `createFarm()`/`updateFarm()` fold any provided GPS values into the same `data` payload as `team_code` (optional - only sent if provided).
- `getEntityData()` extracts GPS into dedicated `latitude`/`longitude`/`altitude`/`accuracy` return keys instead of the identifiers/properties KeyValue split.
- `FarmEntity`: removed the GPS columns' `$casts` entries; new migration (`2026_07_06_155643_remove_gps_columns_from_farm_entities_table.php`) drops `latitude`/`longitude`/`altitude`/`accuracy` from `farm_entities` (confirmed no existing non-null GPS data first).
- `FarmEntityResource::table()` excludes GPS from the dynamic property columns, matching the old `FarmResource` (never showed GPS as list columns either).
- **Bug found and fixed during testing:** GPS initially showed up in the "Personally Identifiable information" section instead of "GPS". Cause: GPS detection relied on the `DatasetVariable.description` tag, but `reconcileProperties()` reuses an existing `DatasetVariable` match by label without correcting its tag - `latitude`/`longitude`/`altitude`/`accuracy` already existed tagged `'identifier'` from testing that pre-dated Part C. Fixed by detecting GPS by property **name** instead (matching how `team_code` was already detected), which was unaffected by the stale tag - no data migration needed. GPS properties are now just tagged plain `'property'`, like `team_code`.

## Unrelated fixes made along the way

- **`FarmEntitySheetImport`/`bulkCreateFarms()`:** `OdkDatasetService::bulkCreateOdkEntities()` was missing the `source` parameter Central's bulk-create endpoint requires (unlike single-entity create) - fixed to always send `source.name`.
- **`ImportFarmsAction` (old Farm CRUD) and `FarmResource\Pages\ImportLocationsAndFarms`:** both had a `TypeError` in their file-upload `afterStateUpdated` closures (declared `?TemporaryUploadedFile $state`, but Livewire can re-invoke with a plain string on a later re-render) - fixed to guard with `instanceof` instead.
- **Database notifications:** the `notifications` table never existed anywhere in the app or its packages, so every `Notification::make()->sendToDatabase(...)` call (the Farm/FarmEntity import completion notifications) was silently failing - added the standard migration. (`->databaseNotifications()` panel config was also tried, then reverted per Dan's direction - the toast notification he wanted comes from `->broadcast()`, not the notifications bell/modal.)
- **`Location::farmsAllCount()`** (the "# of Farms" column on the Clusters/Groups location-level pages) now counts `farm_entities` instead of the legacy `farms` table, via a new `Location::farmEntities()` relation. The three completion-count sibling attributes were left untouched (they depend on fields that don't exist on `FarmEntity` yet). A separately-investigated, pre-existing stale-cache bug in the same method (`Cache::remember()` keyed on `Location.updated_at`, never invalidated on farm changes) was intentionally left as-is per Dan's direction.

## Verification

- `./vendor/bin/pint`, `./vendor/bin/phpstan analyse`, `./vendor/bin/pest` (full suite, 88 passed) after every change in this log.
- Manually tested against a real ODK Central server: list, create, edit (including clearing a property and GPS fields), delete, both import flows (farms-only and combined locations+farms) - all confirmed working by Dan.
