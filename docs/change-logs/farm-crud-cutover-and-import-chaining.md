# Change log: Farm CRUD cutover + combined-import chaining

Implements [docs/plans/farm-crud-cutover-and-import-chaining.md](../plans/farm-crud-cutover-and-import-chaining.md), which closes Phase 6 of [odk-entities-farm-crud.md](../plans/odk-entities-farm-crud.md) and finding M13 of [2026-07-19-map-location-to-entity-list-attribute.md](../code-reviews/2026-07-19-map-location-to-entity-list-attribute.md).

Landed as three commits on `remove-extra-holpa-items`:

| Commit | Scope |
| --- | --- |
| `e6bd0a7` | Part A — remove the legacy Farm model, `FarmResource` and the HOLPA survey-data models |
| `d249bff` | Part A8 — reclaim the `farms` URL slug for `FarmEntityResource` |
| `573a267` | Part B — chain `FarmEntityImport` after `LocationImport` |

---

## Part A — legacy farm and HOLPA survey-data removal

### Moved (shared assets, not legacy)

- `FarmResource/Widgets/FarmListHeaderWidget.php` → `FarmEntityResource/Widgets/FarmListHeaderWidget.php` (namespace updated; `ListFarmEntities` import updated).
- Its Blade view moved too, `filament/app/resources/farm-resource/widgets/` → `.../farm-entity-resource/widgets/`, so the widget's `$view` no longer points into a `farm-resource` tree that has no resource behind it.
- `.../farm-resource/pages/import-locations-and-farms.blade.php` → `.../farm-entity-resource/pages/import-locations-and-farm-entities.blade.php`; `ImportLocationsAndFarmEntities::$view` updated and both empty `farm-resource/` view directories removed.

### Deleted

- `FarmResource.php`, `FarmResource/Pages/ListFarms.php`, `FarmResource/Pages/ImportLocationsAndFarms.php` and the whole `FarmResource/` directory.
- `app/Models/SampleFrame/Farm.php`, `app/Imports/FarmImport.php`, `app/Events/FarmImportCompleted.php`, `app/Policies/FarmPolicy.php`.
- `app/Livewire/DataCollection/DataCollectionByFarm.php` + its Blade view (zero references anywhere).
- `HelperService::findFarmLocationDetails()` (no callers) and its now-unused `Farm` / `Str` imports.
- The whole `app/Models/SurveyData/` namespace — `FarmSurveyData`, `Crop`, `Livestock` — plus `app/Models/Interfaces/RepeatModel.php` and both now-empty directories.

### Edited

| File | Change |
| --- | --- |
| `app/Providers/AppServiceProvider.php` | dropped `Gate::policy(Farm::class, FarmPolicy::class)` and both imports |
| `app/Models/Team.php` | removed `farms()`; `pilotProgress` now `$this->xlsforms()->whereHas('xlsformVersions.submissions')->exists()`; `dataCollectionProgress` the same with `->where('test_data', false)` |
| `app/Models/SampleFrame/Location.php` | removed `farms()` and the three farm-completion attributes; `farmEntities()` and `farms_all_count` kept |
| `app/Livewire/DataCollection/DataCollectionByLocation.php` | the "Farm Counts" `ColumnGroup` collapsed to a single `farms_all_count` "Farms" column |
| `app/Filament/Admin/Widgets/DataCollectedWidget.php` | removed the "Farms surveyed" stat |
| `app/Exports/DataExport/DatasetExport.php` | removed the `Farm` import and `@var` docblock; runtime behaviour untouched |
| `app/Filament/Tables/Actions/ImportFarmsAction.php` | `model_type => FarmEntity::class` (was `Farm::class`) — closes the cosmetic gap recorded in the earlier plan |
| `database/seeders/Prep/DatasetSeeder.php` | the `Farms` row deleted; `Farm Survey Data` / `Crops` / `Livestock` rows kept but their `entity_model` keys dropped |
| `CLAUDE.md` | `Models/SampleFrame/` now lists `FarmEntity`; the `Models/SurveyData/` line removed |

Comments across `FarmEntityResource`, `ListFarmEntities`, `ImportLocationsAndFarmEntities`, `FarmEntityImport`, `FarmEntityPolicy`, `LocationLevelResource`, `OdkFarmEntityService` and `survey-locations-index.blade.php` that described coexistence with the legacy resource, or that named the deleted classes, were rewritten or dropped.

### Behaviour change worth knowing

`Team::pilotProgress` previously meant "a farm has at least one submission"; it now means "the team has at least one submission". Nothing has linked submissions to farms since HOLPA submission processing was removed in `4a93d71`, so the old expression could only ever return `false` — the new one is strictly closer to what the dashboard tile is trying to say. The same reasoning applies to `dataCollectionProgress`, which read `farms.household_form_completed` / `fieldwork_form_completed`, columns nothing has written for just as long.

### Schema

- Deleted the four `create` migrations (`create_farms_table`, `create_farm_survey_data_table`, `create_crops_table`, `create_livestocks_table`) so fresh installs never build the tables.
- Added `2026_07_31_000001_drop_legacy_farm_tables.php`, which `dropIfExists()`es `crops`, `livestocks`, `farm_survey_data` and `farms` so existing dev databases converge. A no-op on a fresh install. No FK constraints existed between any of them.
- No column-level surgery was needed: `farm_survey_data.farm_id` disappeared with its table, and nothing outside the four tables pointed at them.
- **Note for later:** existing dev databases still hold seeded `Dataset` rows whose `entity_model` column names the now-deleted classes. Nothing reads `entity_model` (the only readers — `Dataset::globalEntries` / `teamEntries` / `databaseTable` — have no consumers), so no data migration was written. Those strings are stale labels, not live pointers.

### What deliberately survived

`App\Exports\DataExport\FarmSurveyDataExport`, `FarmSurveyDatasetExport` and `DatasetExport` share names with the deleted models but are driven entirely by `Dataset` rows looked up **by name** plus generic `Entity`/`EntityValue` rows written by `OdkSubmissionService`. They never referenced the model classes. That is why the `Farm Survey Data`, `Crops` and `Livestock` seeder rows had to stay — `FarmSurveyDataExport` `abort(500)`s without the parent row.

### A8 — the `farms` slug

With the old resource gone, `FarmEntityResource::$slug` went from `farm-entities` back to `farms`, restoring `app/{tenant}/location-levels/farms`. Class names unchanged. `survey-locations-index.blade.php` already resolves the URL through `FarmEntityResource::getUrl()` and needed no edit; only the two tests that hit the route by path moved. Kept as its own commit so it can be reverted independently of the deletions.

---

## Part B — chaining the combined import

### Root cause

`ImportLocationsAndFarmEntities::save()` called `Excel::import()` twice and discarded both return values. For a `ShouldQueue` + `WithChunkReading` importer, `ChunkReader::read()` builds `(new QueueImport($import))->chain([...ReadChunk jobs, AfterImportJob])` and returns it inside a `PendingDispatch` that dispatches on destruction — so the page produced **two independent chains on one queue**. Ordering was then whatever the worker pool decided, and `FarmEntityImport::rules()` validates its location column with `Rule::exists('locations', 'code')`, so with more than one worker the farm import could fail wholesale with per-row "location does not exist" errors. It only worked because a single FIFO worker happened to preserve dispatch order.

`Bus::chain([$locationImport, $farmImport])` would not have fixed it: a chained job that itself calls `Excel::import()` returns as soon as its chunks are *queued*, so the next outer link starts immediately.

### `app/Jobs/QueueFarmEntityImport.php` (new)

A plain `ShouldQueue` job holding `array $data` and `int $importId`. `handle()` resolves the `Import` record and runs `Excel::import(new FarmEntityImport($this->data), $import->getFirstMediaPath())` — the media path is resolved at run time rather than serialised as an absolute path built at request time. `failed()` writes the exception onto the `Import` record in the same shape `FarmEntityImport`'s own `ImportFailed` handler uses, so a failure to even *start* the farm import shows on the imports table instead of being silent.

### `ImportLocationsAndFarmEntities::save()`

```php
Excel::queueImport(new LocationImport($data), $locationImport->getFirstMediaPath())
    ->appendToChain(new QueueFarmEntityImport($farmData, $farmImport->id));
```

`Queueable::appendToChain()` adds to the chain `ChunkReader` already built, after its trailing `AfterImportJob` — `Queueable::chain()` would have *replaced* `$chained` and thrown away every `ReadChunk` job. `PendingDispatch::__call()` forwards the call to the underlying `QueueImport`.

The plan called for `Excel::import()` behind an `instanceof PendingDispatch` guard to satisfy phpstan. `Excel::queueImport()` was used instead: it is the same call narrowed to the `ShouldQueue` case and is annotated `PendingDispatch` on the facade, so the guard — which was never a real branch — is gone. One `@phpstan-ignore-next-line` remains on `appendToChain()`, which phpstan cannot see through `PendingDispatch::__call()`.

`$farmData` is a copy of `$data` with `$data['level']` unset: that key holds a whole `LocationLevel` **model**, and `SerializesModels` does not reduce models nested inside array properties. `FarmEntityImport` never reads it.

On `QUEUE_CONNECTION=sync` the chain runs inline in order, so local and test behaviour is unchanged apart from the ordering guarantee.

### `LocationImport` failure propagation

If the location import fails the chain aborts and `QueueFarmEntityImport` never runs, leaving the farm `Import` row empty — reading as "nothing happened" rather than "skipped". `save()` now also passes `$data['dependent_import_id']`, and `LocationImport`'s `ImportFailed` handler calls a new `failDependentImport()` that writes an explanatory error onto that record. The key is optional: `LocationImport` is still used standalone from `LocationLevelResource\Pages\ViewLocationLevel`.

---

## Tests

| File | Change |
| --- | --- |
| `tests/Feature/Crud/AppPanelCrudTest.php` | the `App panel CRUD — Farm` block replaced by `App panel CRUD — FarmEntity`: the list page loads, and `DeleteAction` soft-deletes the row *and* issues the `DELETE .../datasets/Farm_Summary/entities/{uuid}` call to Central. The delete path had no coverage before. |
| `tests/Feature/Smoke/AppPanelTest.php` | "farms list loads" repointed at the new resource |
| `tests/Feature/Exports/FarmSurveyDataExportTest.php` (new) | pins the plan's claim that the submissions export is name-driven, not model-driven: it must still build after the `SurveyData` models and their `entity_model` pointers were removed |
| `tests/Feature/Imports/ImportLocationsAndFarmEntitiesChainingTest.php` (new) | the M13 regression test — submitting the wizard under `Queue::fake()` pushes exactly **one** `QueueImport` whose `chained` tail deserialises to a `QueueFarmEntityImport` carrying the farm `Import` id and no `level` key. Plus `QueueFarmEntityImport::handle()`/`failed()`, and `LocationImport`'s failure path with and without `dependent_import_id`. |

The smoke test needed no `Http::fake()` treatment beyond what was already there: a team with no `OdkProject` makes `refreshFromCentral()` short-circuit before reaching Central. (The plan predicted this would come from `resolveEntityListName()` returning `null`; that method is now hardcoded to `'Farm_Summary'`, so the `findOdkDataset()` null-project branch is what does it.)

## Verification

- `./vendor/bin/pint` — clean.
- `./vendor/bin/phpstan analyse` — 164 errors, down from a 346-error baseline on the branch point. Nothing new in the touched application files; the 10 in the new test file are the usual Pest `$this->` property noise this suite already carries.
- `./vendor/bin/pest` — 167 passed, 377 assertions.
- The plan's verification grep returns only the intended survivors: the three `FarmSurveyData*Export` classes, their two call sites (`XlsformsTableView`, `ExportDataAction`), the seeder's `Dataset` names and the new export test.

## Still open

Manual verification from the plan has **not** been done and is left for whoever runs the branch:

1. Part A — app-panel survey dashboard (both progress tiles), Survey Locations → "List of farms", the location-levels "# of Farms" column, the admin dashboard without the "Farms surveyed" stat, and the submissions export download from the xlsforms table.
2. Part B — a real ODK Central project with **at least two queue workers**, importing a combined spreadsheet whose farms reference locations that do not yet exist locally; then the negative case, where the location half fails validation and the farm `Import` record should show the "skipped" error.

Follow-ups carried forward from the plan are unchanged: location-deletion behaviour after the `nullOnDelete` change was never manually retested; submission → farm linking is still unimplemented (and should be built on `Dataset`/`Entity`/`EntityValue`, not new per-repeat-group tables); Create/Edit form fields still don't match the Farm Registration XLSForm; entities deleted directly on Central are not mirrored locally; a cleared entity property reappears in the Edit form as a blank row.
