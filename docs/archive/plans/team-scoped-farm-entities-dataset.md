# Plan: Team-scoped `farm_entities` Dataset bookkeeping

**Status: Completed**

See [change log](../../change-logs/team-scoped-farm-entities-dataset.md).

## Context

Importing a farm entities Excel file failed with entries landing in `failed_jobs`:

```
Illuminate\Http\Client\RequestException: HTTP request returned status code 400:
{"message":"The entity is invalid. You specified the dataset property [participant_sex] which does not exist.", ...}
```

Root cause: `OdkFarmEntityService::ensureDataset()` returned **one `Dataset` row shared by every team** (`owner_id = null`). `reconcileProperties()` uses that Dataset's `DatasetVariable`s to decide whether a property has already been pushed to a team's Central entity list:

```php
$existing = $dataset->variables()->pluck('name', 'label')->all();
if (isset($existing[$key])) {
    $map[$key] = $existing[$key];
    continue; // never calls addOdkDatasetProperty() for THIS team
}
```

Because the bookkeeping was global but each team's Central entity list is a separate schema, the first team to use a raw property key (e.g. `participant_sex`) "claimed" it in the shared table. Every other team that later used the same key skipped the push entirely, and Central rejected any entity referencing that property.

This also caused a related, quieter bug: `FarmEntityResource::table()` builds its dynamic property columns from the same shared `$dataset->variables()`, so every team's farm list showed columns for every other team's properties too.

## Decisions (agreed with Dan)

Evaluated three options Dan proposed: (1) always push properties to Central regardless of local "known" status (cheap, relies on Central's existing 409-idempotent `addOdkDatasetProperty()`, but leaves the underlying bookkeeping mismatch in place); (2) catch the 400 and retry (fragile - relies on parsing Central's error message, and `bulkCreateOdkEntities()` is all-or-nothing so a whole import batch would need retrying); (3) make the Dataset itself team-owned, so bookkeeping is 1:1 with each team's actual Central schema.

**Chose option 3** - it fixes the root cause (not just the symptom), matches how everything else in this app is already scoped (`Location`, `LocationLevel`, `Xlsform`, `OdkDataset` are all per-team), and incidentally fixes the column-leakage bug in `FarmEntityResource` for free.

## Implementation

- `ensureDataset(Team $team): Dataset` - now requires a `Team` and scopes the row to it.
- **Discovered during implementation**: the package's own `Dataset` migration defines a composite `unique([name, owner_id])`, but this app's actual `datasets` table (`database/migrations/03_xlsform_management/2024_03_10_03_101232_1_create_datasets_table.php`) instead enforces a single-column `unique(name)` - a deliberate deviation documented in `2026_06_24_000009_add_owner_id_to_datasets_table.php`'s own comment ("the app keeps its existing unique('name') index... Revisit if per-owner datasets are introduced"). Relying on `owner_id` alone to disambiguate would collide on `name` for every team after the first. Rather than loosen that constraint app-wide (which would also weaken uniqueness guarantees for the app's other global, owner_id-null Datasets - `Farm Survey Data`, `Products`, etc.), the `farm_entities` Dataset's `name` itself now carries the team id suffix (`farm_entities_{$team->id}`), scoped to just this one feature. No schema migration needed.
- All 6 call sites (`createFarm()`, `bulkCreateFarms()`, `getEntityData()`, `updateFarm()`, `refreshFromCentral()`, and `FarmEntityResource::table()`) updated to pass `$team`/`$farmEntity->owner` through.
- The pre-existing shared Dataset row (`owner_id = null`, `name = 'farm_entities'`) is left in place, per Dan - harmless dead data once nothing reads it, no cleanup needed. Every team self-heals on its next `createFarm()`/`updateFarm()`/`bulkCreateFarms()`/`refreshFromCentral()` call: a fresh per-team Dataset is created empty, and `reconcileProperties()` re-pushes each property - which is a no-op against Central for properties already provisioned there (`addOdkDatasetProperty()` swallows 409).

## Verification

- New regression tests in `tests/Feature/Services/OdkFarmEntityServiceDatasetTest.php` (`Http::fake()`, no Mockery - matches this app's existing OdkFarmEntityService test style): confirmed failing against the pre-fix code (2 of 3 red), passing against the fix.
- Full suite (110 tests unrelated to this change + 3 new), `phpstan`, `pint` all pass on the touched files.
