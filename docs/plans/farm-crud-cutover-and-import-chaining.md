# Plan: Finish the ODK-Entities farm CRUD cutover (remove legacy Farm + HOLPA survey-data models) + chain the combined import

**Status: Not Started**

Wraps up [odk-entities-farm-crud.md](odk-entities-farm-crud.md) — its Phase 6 (cutover) and the one known open finding from [2026-07-19-map-location-to-entity-list-attribute.md](../code-reviews/2026-07-19-map-location-to-entity-list-attribute.md) (M13). Two independent parts; either can land first, but Part A deletes `FarmResource\Pages\ImportLocationsAndFarms`, so doing Part A first avoids fixing the concurrency bug in a file that is about to be deleted.

## Decisions taken before planning

- The `farms` table holds **no** data in any environment (no production instance of this app exists). No backfill of legacy farms into ODK Central is needed, and no care is required around existing rows. The original Phase 6 note about backfilling is void.
- The farm **completion-tracking** features are dead and get deleted rather than repointed at `FarmEntity`. Nothing has written `farms.household_form_completed` / `fieldwork_form_completed` since the HOLPA submission processing was removed in `4a93d71`, and nothing writes `submissions.primary_data_subject_*` anywhere in the tree, so every one of these surfaces currently renders zeros/false.
- The whole `App\Models\SurveyData` namespace (`FarmSurveyData`, `Crop`, `Livestock`) is HOLPA leftover and is **purged**, along with the `RepeatModel` interface that exists only to describe it and the three tables behind it. Nothing has written any of them since `4a93d71` either.

---

# Part A — Remove `FarmResource`, the `Farm` model, and the HOLPA survey-data models

## What the audit found

`Farm` is referenced from 13 files, but almost every reference is already dormant. Live vs. dead breakdown:

**Genuinely live (needs a replacement or a deliberate removal):**
- `Team::pilotProgress` / `Team::dataCollectionProgress` — both read `$this->farms`, and both feed `resources/views/filament/app/pages/survey-dashboard.blade.php` (`pilot_progress`, `data_collection_progress`). Their `in_progress` branches are the only place a farm-derived value reaches a user-visible surface with any chance of being non-default.
- `Location::farms()` — still used by the three completion-count attributes (`farmsHouseholdCompleteCount`, `farmsFieldworkCompleteCount`, `farmsAllCompleteCount`); `farmsAllCount` already switched to `farmEntities`.
- `FarmResource` route `app/{tenant}/location-levels/farms` — unreachable from any in-app link (nav was retargeted to `FarmEntityResource`) but still routable, and still hit by `tests/Feature/Smoke/AppPanelTest.php` and `tests/Feature/Crud/AppPanelCrudTest.php`.
- `ImportFarmsAction` — shared by the old and new list pages; hardcodes `model_type => Farm::class` on the `Import` audit record (the "cosmetic-only gap" recorded in the existing plan).
- `resources/views/.../farm-resource/pages/import-locations-and-farms.blade.php` and `FarmResource\Widgets\FarmListHeaderWidget` — both live inside the `FarmResource` tree but are **reused by the new `FarmEntityResource` pages**. These must move, not be deleted.

**Dead on arrival (delete outright, no replacement):**
- `app/Livewire/DataCollection/DataCollectionByFarm.php` + `resources/views/livewire/data-collection/data-collection-by-farm.blade.php` — zero references anywhere (no page, no blade, no tab includes it).
- `HelperService::findFarmLocationDetails()` — zero callers.
- `app/Events/FarmImportCompleted.php` — dispatched nowhere; only listened to by `ListFarms`.
- `Farm::getCsvContentsForOdk()` — no caller. Dataset-backed CSV media attachments are generated from generic `Entity` rows by `DatasetAsMediaAttachmentExport`, not from `entity_model`.
- `Farm::updateCompletionStatus()` — only reachable via `Submission::primaryDataSubject`, which is never populated.
- `Dataset` "Farms" row (`entity_model => Farm::class`) in `database/seeders/Prep/DatasetSeeder.php` — `FarmSurveyDataExport` explicitly filters "Farms" out of its child-dataset list, and `app/Models/Dataset.php`'s `entity_model`-driven attributes (`globalEntries`, `teamEntries`, `databaseTable`, …) have no consumers at all.
- `app/Imports/FarmImport.php`, `app/Policies/FarmPolicy.php`.

**No FK problem:** `farm_survey_data.farm_id` is a plain `unsignedBigInteger` with no foreign key, and nothing writes `farm_survey_data` at all any more. Nothing else in `database/migrations/` references `farms`.

## What the audit found — the HOLPA survey-data models

`FarmSurveyData`, `Crop` and `Livestock` are the entire `App\Models\SurveyData` namespace, and they are wholly self-referential: `Crop` and `Livestock` each `belongsTo(FarmSurveyData)`, `FarmSurveyData` `belongsTo(Submission)` and `hasMany` the other two, and `Farm::farmSurveyData()` (deleted anyway) closes the loop. Outside that cluster the only references are `database/seeders/Prep/DatasetSeeder.php` (`entity_model` labels) and two code comments. Nothing constructs, queries or writes any of them.

- `app/Models/Interfaces/RepeatModel.php` is implemented **only** by `Crop` and `Livestock`, and is the only file in that directory — so the interface and the directory both go.
- `crops.farm_survey_data_id`, `livestocks.farm_survey_data_id`, `crops.submission_id`, `livestocks.submission_id` and `farm_survey_data.submission_id` are all `foreignId(...)->nullable()` **without** `constrained()` — no actual FK constraints exist, so the three tables can be dropped in any order.
- **Do not delete anything just because its name matches.** `app/Exports/DataExport/FarmSurveyDataExport.php`, `FarmSurveyDatasetExport.php` and `DatasetExport.php` **stay**: they are driven entirely by `Dataset` rows looked up *by name* (`'Farm Survey Data'`, and children filtered by `'Crops'`/`'Livestock'`/…) plus generic `Entity`/`EntityValue` rows written by `OdkSubmissionService`. They never touch the deleted model classes. `FarmSurveyDataExport` is reachable from `XlsformsTableView`'s download button and `ExportDataAction`, and it `abort(500)`s if the `'Farm Survey Data'` `Dataset` row is missing.
- Consequently the `DatasetSeeder` **rows must be kept** — only their `entity_model` values are removed. `entity_model` is a dead label in this app: the only readers are `app/Models/Dataset.php`'s `globalEntries`/`teamEntries`/`databaseTable` attributes, which have no consumers anywhere.
- `data_dictionary_entries` (the fourth table in `07_survey_data/`) is unrelated — it backs the data-dictionary exports. Leave it.

## Steps

### A1. Move the two shared assets out of the `FarmResource` tree

Do this first so the later deletions are pure removals.

1. `app/Filament/App/Clusters/LocationLevels/Resources/FarmResource/Widgets/FarmListHeaderWidget.php` → `.../FarmEntityResource/Widgets/FarmListHeaderWidget.php` (update namespace; the widget has no `Farm`-specific logic — it renders a translation-driven instructions panel). Update the `use` + `getHeaderWidgets()` in `FarmEntityResource/Pages/ListFarmEntities.php`.
2. `resources/views/filament/app/clusters/location-levels/resources/farm-resource/pages/import-locations-and-farms.blade.php` → `.../farm-entity-resource/pages/import-locations-and-farm-entities.blade.php`. Update `protected string $view` in `ImportLocationsAndFarmEntities` and delete the now-empty `farm-resource/` view directory.

### A2. Delete the legacy Filament resource

- `app/Filament/App/Clusters/LocationLevels/Resources/FarmResource.php`
- `app/Filament/App/Clusters/LocationLevels/Resources/FarmResource/Pages/ListFarms.php`
- `app/Filament/App/Clusters/LocationLevels/Resources/FarmResource/Pages/ImportLocationsAndFarms.php`
- the whole `FarmResource/` directory once A1 has moved the widget out

### A3. Delete the model and its dead satellites

- `app/Models/SampleFrame/Farm.php`
- `app/Imports/FarmImport.php`
- `app/Events/FarmImportCompleted.php`
- `app/Policies/FarmPolicy.php`
- `app/Livewire/DataCollection/DataCollectionByFarm.php` + `resources/views/livewire/data-collection/data-collection-by-farm.blade.php`
- `HelperService::findFarmLocationDetails()` (method only; keep `getCurrentOwner()`), and its now-unused `Farm` / `Str` imports — check whether `Str` is used elsewhere in the file before removing it.

### A4. Purge the HOLPA survey-data models

Delete outright:

- `app/Models/SurveyData/FarmSurveyData.php`
- `app/Models/SurveyData/Crop.php`
- `app/Models/SurveyData/Livestock.php`
- the now-empty `app/Models/SurveyData/` directory
- `app/Models/Interfaces/RepeatModel.php` and the now-empty `app/Models/Interfaces/` directory

Then fix the two stale comments that name the deleted classes:

- `app/Services/OdkFarmEntityService.php:829-831` — `deleteFarm()`'s docblock justifies keeping the soft-deleted row "for any future FK references, e.g. from `FarmSurveyData` once this is wired up at cutover". Reword to reference submission data generically; the justification still holds, the example no longer exists.
- `database/migrations/2026_06_24_000001_create_dataset_parents_table.php:9` — mentions `App\Exports\DataExport\FarmSurveyDataExport`, which **survives**. No change needed; listed here only so it isn't "fixed" by mistake.

`CLAUDE.md:70-71` describes both `Models/SampleFrame/` (lists `Farm`) and `Models/SurveyData/` (lists `FarmSurveyData, Crop, Product`). Update line 70 to drop `Farm` and add `FarmEntity`, and delete line 71 entirely.

### A5. Edit the remaining references

| File | Change |
| --- | --- |
| `app/Providers/AppServiceProvider.php:78` | remove `Gate::policy(Farm::class, FarmPolicy::class)` + import (keep the `FarmEntity` line below it) |
| `app/Models/Team.php:150` | remove `farms(): HasMany` |
| `app/Models/Team.php` `pilotProgress` | replace `$this->farms->some(fn (Farm $farm) => $farm->submissions()->count() > 0)` with `$this->xlsforms()->whereHas('xlsformVersions.submissions')->exists()` |
| `app/Models/Team.php` `dataCollectionProgress` | replace the `household_form_completed \|\| fieldwork_form_completed` check with `$this->xlsforms()->whereHas('xlsformVersions.submissions', fn ($query) => $query->where('test_data', false))->exists()` |
| `app/Models/SampleFrame/Location.php` | remove `farms()` and the three completion-count attributes (`farmsHouseholdCompleteCount`, `farmsFieldworkCompleteCount`, `farmsAllCompleteCount`); keep `farmEntities()` and `farmsAllCount` |
| `app/Livewire/DataCollection/DataCollectionByLocation.php:72-77` | keep the `farms_all_count` "Total" column, drop the three "…Complete" columns (and the `ColumnGroup` if it ends up holding only "Total" — collapse to a plain column) |
| `app/Filament/Admin/Widgets/DataCollectedWidget.php` | remove the "Farms surveyed" stat and the `Farm` import |
| `app/Exports/DataExport/DatasetExport.php:42` | remove the `Farm` import and the `/** @var Farm $farm */` docblock; leave the null-safe runtime behaviour untouched |
| `app/Filament/Tables/Actions/ImportFarmsAction.php:178` | `model_type => FarmEntity::class` (fixes the recorded cosmetic gap), and drop the now-stale `NOTE:` comment block in `ListFarmEntities::getHeaderActions()` that documents it |
| `database/seeders/Prep/DatasetSeeder.php` | delete the `Farms` row (`entity_model => Farm::class`) entirely; **keep** the `Farm Survey Data`, `Crops` and `Livestock` rows but drop their `entity_model` keys, since the exports look those rows up by name. Remove the `Farm` / `FarmSurveyData` / `Crop` / `Livestock` imports; leave the `Location` row and import as they are |

`Team::pilotProgress`'s replacement is a **behaviour change worth calling out**: it previously meant "a farm has at least one submission", now it means "the team has at least one submission". Given nothing links submissions to farms any more, the old expression could only ever return `false`, so the new one is strictly closer to the intent of the dashboard tile.

### A6. Schema

- Delete the four `create` migrations so fresh installs never build these tables (a missing file whose row exists in `migrations` is harmless to Laravel):
  - `database/migrations/06_locations/2024_06_11_06_104524_create_farms_table.php`
  - `database/migrations/07_survey_data/2024_08_12_11_121716_create_farm_survey_data_table.php`
  - `database/migrations/07_survey_data/2024_08_12_12_111301_create_crops_table.php`
  - `database/migrations/07_survey_data/2024_08_12_12_111310_create_livestocks_table.php`
- Add one `database/migrations/2026_XX_XX_XXXXXX_drop_legacy_farm_tables.php` that `Schema::dropIfExists()`es `farms`, `crops`, `livestocks` and `farm_survey_data` in `up()` (no `down()`, per the project's migration convention), so existing dev databases converge. On a fresh install it is a no-op. No FK constraints exist between them, so order doesn't matter — but drop the children (`crops`, `livestocks`) before `farm_survey_data` anyway for readability.
- No column-level surgery is needed anywhere: `farm_survey_data.farm_id` disappears with its table, and nothing outside these four tables points at them.
- Existing dev databases will still contain seeded `Dataset` rows whose `entity_model` names the deleted classes. Nothing reads `entity_model`, so no data migration is required — note it in the change log so those strings aren't mistaken for live pointers later. Tests reseed from scratch and are unaffected.

### A7. Tests

- `tests/Feature/Crud/AppPanelCrudTest.php` — delete the `describe('App panel CRUD — Farm')` block (its "farm list page loads" + "can bulk delete farm" tests both target the removed resource). `FarmEntityResource` already has coverage in `tests/Feature/Crud/FarmEntityResourceColumnsTest.php`; add a `FarmEntity` bulk/single-delete CRUD test there only if the existing suite doesn't already assert the delete path.
- `tests/Feature/Smoke/AppPanelTest.php:114` — repoint "farms list loads" from `/location-levels/farms` to the new route. `ListFarmEntities::mount()` calls `refreshFromCentral()`, so the test needs the `Http::fake()` treatment already used in `FarmEntityResourceColumnsTest`; a team with no active xlsform makes `resolveEntityListName()` a no-op, which is the cheapest way to keep the smoke test a smoke test.
- Check `tests/Feature/Models/` and `tests/Feature/Services/` for any assertion on the removed `Location`/`Team` attributes before running the suite. (A grep for `FarmSurveyData`/`Crop`/`Livestock` across `tests/` currently returns nothing.)
- Add a smoke assertion that the submissions export still builds — it is the one live feature adjacent to the purged models, and the plan's claim that it is name-driven rather than model-driven should be pinned by a test: seed, then assert `Excel::download(new FarmSurveyDataExport($team))` doesn't throw for a team with no submissions.

### A8. Optional (recommended): reclaim the `farms` slug

With the old resource gone, `FarmEntityResource`'s `protected static ?string $slug = 'farm-entities'` can become `'farms'` for clean URLs (`app/{tenant}/location-levels/farms`), keeping every class name as-is. If taken, update the import route reference in `ListFarmEntities`, the smoke test, and `resources/views/filament/app/pages/survey-locations/survey-locations-index.blade.php` (it already resolves the URL via `FarmEntityResource::getUrl()`, so it needs no change). Do this as a separate commit so it is easy to revert independently of the deletions.

---

# Part B — Chain `FarmEntityImport` after `LocationImport` (finding M13)

## Root cause, confirmed against vendor source

`ImportLocationsAndFarmEntities::save()` calls `Excel::import()` twice and discards both return values. For a `ShouldQueue` + `WithChunkReading` importer, `Maatwebsite\Excel\ChunkReader::read()` ([ChunkReader.php:83-101](../../vendor/maatwebsite/excel/src/ChunkReader.php)) builds `(new QueueImport($import))->chain([...ReadChunk jobs, AfterImportJob])` and hands it back inside a `PendingDispatch`, which dispatches on destruction. So the page produces **two independent chains on the same queue**.

Ordering between them is therefore whatever the worker pool decides. With more than one Horizon worker, farm `ReadChunk` jobs can run before the location `ReadChunk` jobs have committed, and `FarmEntityImport::rules()` validates the location column with `Rule::exists('locations', 'code')` → the whole farm import fails with per-row "location does not exist" errors. It is only safe today because a single FIFO worker happens to preserve dispatch order.

Worth noting the second-order effect: `FarmEntityImport::collection()` also silently `filter()`s out rows whose `Location` lookup returns `null`, so if that validation rule were ever relaxed the same race would produce a *silently partial* import instead of a loud failure.

## Why the obvious fix doesn't work

`Bus::chain([$queueLocationImport, $queueFarmImport])` does **not** solve this. A chained job that itself calls `Excel::import()` returns as soon as the chunk jobs are *queued*, so the next link in the outer chain starts immediately — the race is unchanged. The dependency has to be expressed inside the chain that `ChunkReader` already built, after its trailing `AfterImportJob`.

`Illuminate\Bus\Queueable::appendToChain()` does exactly that, and `PendingDispatch::__call()` forwards to the underlying job — so `appendToChain()` on the value returned by `Excel::import()` appends to the location chain. (Do **not** use `->chain()`: `Queueable::chain()` *replaces* `$chained`, which would throw away every `ReadChunk` job.)

## Steps

### B1. New wrapper job

`app/Jobs/QueueFarmEntityImport.php` — a plain `ShouldQueue` job whose `handle()` starts the farm import once locations are in:

```php
public function __construct(public array $data, public int $importId) {}

public function handle(): void
{
    $import = Import::findOrFail($this->importId);

    Excel::import(new FarmEntityImport($this->data), $import->getFirstMediaPath());
}
```

Resolve the media path inside `handle()` from the `Import` id rather than serializing an absolute path built at request time.

Add a `failed(Throwable $exception)` method writing the message onto the `Import` record in the same shape `FarmEntityImport`'s own `ImportFailed` handler uses, so a failure to even *start* the farm import is visible on the imports table rather than silent.

### B2. Chain it from the wizard

In `ImportLocationsAndFarmEntities::save()`:

```php
$pendingLocationImport = Excel::import(new LocationImport($data), $locationImport->getFirstMediaPath());

// Farm rows validate against locations this import is still creating
// (FarmEntityImport::rules() → Rule::exists('locations', 'code')), so the farm import must
// not run concurrently. Appending to the location import's own chain — rather than
// dispatching a second chain — is what actually orders them: a sibling Bus::chain link
// would fire as soon as Excel::import() had *queued* the location chunks.
if ($pendingLocationImport instanceof PendingDispatch) {
    $pendingLocationImport->appendToChain(new QueueFarmEntityImport($farmData, $farmImport->id));
}
```

Notes for whoever implements it:

- The return value must be held in a variable — `PendingDispatch::__destruct()` dispatches, so `appendToChain()` has to happen before it goes out of scope.
- The `instanceof` guard is for `Excel::import()`'s `Reader|PendingDispatch` return type (phpstan level 5), not a real branch: `LocationImport` always implements `ShouldQueue`.
- Build `$farmData` as a copy of `$data` with `$data['level']` unset — that key holds a whole `LocationLevel` **model**, `SerializesModels` does not reduce models nested inside array properties, and `FarmEntityImport` never reads it. Keep `import_id` pointing at the farm `Import` row, exactly as the current code does.
- On `QUEUE_CONNECTION=sync` the chain runs inline in order, so local/test behaviour is unchanged apart from the ordering guarantee.

### B3. Propagate a failed location import to the farm `Import` record

If the location import fails, the chain aborts and `QueueFarmEntityImport` never runs — leaving the farm `Import` row with no farms and no errors, which reads as "nothing happened" rather than "skipped".

Pass the farm import id into the location import's data (e.g. `$data['dependent_import_id']`) and, in `LocationImport::registerEvents()`'s `ImportFailed` handler, also write an explanatory error onto that record when the key is present. `LocationImport` is used standalone from `LocationLevelResource\Pages\ViewLocationLevel`, so the key must be treated as optional (`$this->data['dependent_import_id'] ?? null`).

### B4. Tests

- `tests/Feature/Imports/` — a feature test that fills the wizard form via `livewire(ImportLocationsAndFarmEntities::class)` under `Queue::fake()` and asserts **one** `Maatwebsite\Excel\Jobs\QueueImport` was pushed (not two), whose `chained` tail is a serialized `QueueFarmEntityImport`. This is the regression test for M13 — a second pushed `QueueImport` is exactly the bug.
- A test for `QueueFarmEntityImport::handle()` under `Excel::fake()` asserting the farm file is imported with a `FarmEntityImport` instance.
- A test that `LocationImport`'s failure path writes to the dependent farm `Import` record when `dependent_import_id` is set, and doesn't break when it isn't.

## Explicitly out of scope

- **M14** (`owner_id` / `user_id` arriving via client-mutable hidden Livewire fields, then being trusted as the tenancy anchor in both importers). Real, pre-existing, and orthogonal to ordering — it needs its own plan.
- The queued-job-resolves-team-via-`$data['owner_id']` limitation carried over from `FarmSheetImport`.
- Any change to `ImportFarmsAction`'s standalone farms-only import: it has no location dependency and no race.

---

## Verification

1. `./vendor/bin/pint` and `./vendor/bin/phpstan analyse` after each part.
2. `./vendor/bin/pest` — full suite green.
3. `grep -rn "SampleFrame.Farm\b\|FarmResource\|FarmImport\b\|FarmPolicy\|SurveyData\|RepeatModel" app packages database tests resources routes` returns nothing after Part A **except** the surviving `App\Exports\DataExport\FarmSurveyDataExport` / `FarmSurveyDatasetExport` class names and their two call sites (`XlsformsTableView`, `ExportDataAction`), plus `FarmEntity*` / `FarmSample*` names.
4. Manual, Part A: app-panel survey dashboard renders (both progress tiles), Survey Locations index → "List of farms" reaches `FarmEntityResource`, the location-levels location table still shows "# of Farms", admin dashboard renders without the "Farms surveyed" stat, `/app/{tenant}/location-levels/farms` 404s (or serves the new resource, if A8 is taken), and the submissions export downloads from the xlsforms table without a 500.
5. Manual, Part B, against a real ODK Central project **with at least two queue workers running** (`php artisan queue:work` twice, or Horizon with `processes > 1`) — this is the configuration that exposes M13. Upload a combined locations+farms spreadsheet whose farms reference locations that do not yet exist locally, and confirm: locations import first, farms import afterwards with zero "location does not exist" errors, both `Import` records end clean, and both completion notifications arrive in order.
6. Manual, Part B negative case: upload a file whose location half fails validation, and confirm the farm `Import` record shows the "skipped because the location import failed" error rather than sitting empty.

## Follow-ups this plan does not close

Carried forward from [odk-entities-farm-crud.md](odk-entities-farm-crud.md), still open after this work:

- Ordinary location-deletion behaviour after the `nullOnDelete` FK change was verified at schema level but never manually retested.
- Submission → farm linking (`submissions.primary_data_subject_*`) is unimplemented, so nothing connects a submission to the farm it describes. Part A deletes the dead *readers* of that link (and, with the `SurveyData` models, the abandoned HOLPA-era destination tables); designing and building the replacement writer is separate work. Whatever replaces it should be built on `Dataset`/`Entity`/`EntityValue` — the mechanism `OdkSubmissionService` already writes to and the exports already read — rather than a new set of bespoke per-repeat-group tables.
- Create/Edit form fields still don't match the Farm Registration XLSForm's fixed fields (deferred UX feedback).
- Entities deleted directly on Central are not mirrored locally (needs OData pagination confirmed first).
- A cleared entity property reappears in the Edit form as a blank row rather than looking removed.
