# Change log: Team-scoped `farm_entities` Dataset bookkeeping

Implements [the plan](../archive/plans/team-scoped-farm-entities-dataset.md).

## Problem

Importing a farm entities Excel file for a second team failed with `failed_jobs` entries:

```
HTTP request returned status code 400: "You specified the dataset property [participant_sex] which does not exist."
```

`OdkFarmEntityService::ensureDataset()` returned one `Dataset` row shared across every team, so `reconcileProperties()` treated a raw property key as "already known" the moment any team had used it, silently skipping the push to Central (`addOdkDatasetProperty()`) for every subsequent team that used the same key. Each team's Central entity list is a separate schema, so those teams' entities then referenced a property Central had never been told about.

## Changes

- **`App\Services\OdkFarmEntityService::ensureDataset(Team $team): Dataset`** - now requires a `Team` and scopes the row via `owner_id = $team->id`. The `name` also carries a `_{$team->id}` suffix, because this app's `datasets` table enforces a single-column `unique(name)` (not the filament-odk-link package's own composite `unique([name, owner_id])` - a pre-existing, deliberate app-level deviation). Relying on `owner_id` alone would have collided on `name` for every team after the first.
- Updated all 6 call sites to pass the team through: `createFarm()`, `bulkCreateFarms()`, `getEntityData()`, `updateFarm()`, `refreshFromCentral()` (all in `OdkFarmEntityService`), and `FarmEntityResource::table()`.
- **Side effect fixed for free**: `FarmEntityResource`'s farm list previously showed dynamic property columns for every team's properties (since it read the same shared Dataset). Now scoped to the viewing team's own properties only.
- **No data migration**: the old shared Dataset row (`owner_id = null`, `name = 'farm_entities'`) is left as harmless dead data (per Dan). Every team self-heals on its next write/refresh - a fresh per-team Dataset starts empty, and `reconcileProperties()` re-pushes each property, which is a no-op against Central for anything already provisioned there.

## Options considered

Dan proposed three fixes: (1) always push properties to Central regardless of local "known" status - cheap but leaves the bookkeeping/reality mismatch in place; (2) catch Central's 400 and retry - ruled out as more complex and less reliable (`bulkCreateOdkEntities()` is all-or-nothing, and parsing Central's error message to find the missing property is fragile); (3) team-scope the Dataset - chosen, since it fixes the actual root cause and matches how `Location`/`LocationLevel`/`Xlsform`/`OdkDataset` are already scoped in this app.

## Tests

Added `tests/Feature/Services/OdkFarmEntityServiceDatasetTest.php` (`Http::fake()`, matching this app's existing `OdkFarmEntityService` test style - no mocking framework, since introducing one tripped PHPStan on this app's Mockery-less test suite):

- `ensureDataset()` returns a distinct Dataset row per team.
- `reconcileProperties()` pushes a new property to Central separately for each team, even when they use the same raw key - the exact regression, confirmed failing (2 of 3 red) against the pre-fix code and passing against the fix.
- `reconcileProperties()` does not re-push a property already known for the same team.

Full suite (110 pre-existing tests + 3 new), `phpstan`, `pint` all pass on the touched files. Note: the full-repo `./vendor/bin/phpstan analyse` (no path args) currently reports ~200+ pre-existing errors unrelated to this change (confirmed present on a clean checkout via `git stash`) - out of scope here.

## Known follow-up, not addressed

The user-added temporary `ray()` debug calls in `buildLocationAttributes()` (unrelated to this change) currently fail the repo's `ArchTest` (`ray`/`dd`/`dump` banned in `app/`) - will need removing before this branch is otherwise ready to ship.
