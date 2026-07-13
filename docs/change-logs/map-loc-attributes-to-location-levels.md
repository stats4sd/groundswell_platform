# Change log: Resolve FarmEntity.location_id from `loc{n}_name` entity attributes

Implements [the plan](../plans/map-loc-attributes-to-location-levels.md), narrowed per decisions made 2026-07-13: match-only against existing Locations (no auto-create), matched by name **and** hierarchy position, no `loc{n}_type` check.

## Changes

- **`App\Models\SampleFrame\LocationLevel::farmLevelChain(Team $team): Collection`** (new) — root-to-leaf chain of a team's location levels, ending at the `has_farms = true` level. Extracted from the inline parent-walk previously duplicated in `ImportLocationsAndFarmEntities.php`.
- **`ImportLocationsAndFarmEntities.php`** — its column-mapping wizard step now builds its parent/farm-level questions from `LocationLevel::farmLevelChain(Filament::getTenant())` instead of its own inline walk.
- **`App\Services\OdkFarmEntityService::resolveLocationFromAttributes(Team $team, array $data): ?int`** (new) — parses `loc{n}_name` keys from an entity's flat property data, caps at the team's configured `farmLevelChain()` length (so a `loc4_name` beyond a 3-level chain is ignored - it's farm/household data, not a Location), takes the deepest remaining position, and matches it against an existing `Location` by `owner_id` + `location_level_id` + `name`. Returns `null` (no creation) if nothing matches.
- **`OdkFarmEntityService::refreshFromCentral()`** — the adopt branch now calls `resolveLocationFromAttributes()` instead of hardcoding `location_id => null`. Also retries resolution for existing `FarmEntity` rows that still have `location_id === null` on every refresh, since this method already re-runs the whole feed on every list-page view.

## Follow-up (2026-07-13, after manual testing)

- Confirmed working end-to-end by Dan against real ODK Central data.
- Name matching in `resolveLocationFromAttributes()` changed to case-insensitive (`whereRaw('LOWER(name) = ?', [Str::lower(...)])`) to avoid unmatched locations purely from case differences between ODK data entry and the app's `Location.name` values.

## Reverse direction (2026-07-13): writing `loc{n}` attributes on app-side create/update

Farms created through the app (not Enketo) already had a correct `location_id`, but nothing pushed `loc{n}`/`loc{n}_name`/`loc{n}_type` to Central for them - so Central-side cascading selects/filtering by cluster/group never saw those farms in the right place, even though the app itself had them filed correctly.

- **`OdkFarmEntityService::buildLocationAttributes(int $locationId): array`** (new) — the reverse of `resolveLocationFromAttributes()`. Walks a `Location`'s `parent` chain to the root; each level's `loc{n}` position comes from `LocationLevel->pos`, so it's not hardcoded to any fixed depth - works for however many levels a team has configured. Produces `loc{n}` (fixed placeholder `"1"` - see revision below), `loc{n}_name` (its `name`), `loc{n}_type` (fixed placeholder `"Loc{n} name"`, matching the deployed template's own non-meaningful placeholder).
- Wired into `createFarm()`, `bulkCreateFarms()` (computed once per distinct `location_id` in the batch), and `updateFarm()` - merged into the same identifiers/properties/GPS reconciliation pipeline each already had.
- **Backfill**: the two farms already imported before this change need one more edit-and-save (or any `updateFarm()` call) to pick up the new properties on Central - this only affects future writes, not what's already there.

## Debugging + owner scoping fix (2026-07-13)

Diagnosing the two test farms above (imported via "Import Farm list", ended up with no `location_id` and empty `loc{n}*` on Central):

- **`loc{n}` (bare, not `_name`) revised to a hardcoded `"1"`** — per Dan, not `Location.code`; only its presence signals the level has data, the value isn't consumed downstream.
- **Real bug found and fixed**: `FarmEntityImport.php`'s location lookup had no `owner_id` scope (`Location` has no automatic tenant global scope, unlike `LocationLevel`) - could match/miss against a different team's `Location` row on a shared `code`. Fixed by resolving `$team` before the row-mapping loop and adding `->where('owner_id', $team->id)`.
- **Temporary `ray()` calls added then removed** (per this repo's established debugging convention - see prior `ray()` removal precedent in this same service's git history) in `FarmEntityImport::collection()` (per-row location match attempt + final `$preparedRows`) and `OdkFarmEntityService::bulkCreateFarms()` (prepared entity `data` payload right before the Central API call) - queued-job-safe, unlike `dd()`. Used to confirm the owner-scoping fix above, then removed once confirmed.

## Tests (2026-07-13)

Added `tests/Feature/Services/OdkFarmEntityServiceLocationTest.php`, covering both directions with no Central API calls involved (pure local-DB logic):

- **`resolveLocationFromAttributes()`**: resolves the deepest `loc{n}_name` to the matching `Location`; caps at the team's configured chain length (a `loc3_name` beyond a 2-level chain is ignored); case-insensitive name match; position-scoped match (doesn't false-match a name reused at a different level, e.g. a Cluster and a Group both named "Central"); scoped to the given team (doesn't match another team's `Location` on a shared code/name); returns `null` when there's no usable `loc{n}_name`, no match, or the team has no `has_farms` level configured yet.
- **`buildLocationAttributes()`**: walks an arbitrary-depth chain (tested with 3 levels) producing `loc1`..`loc3` correctly rather than anything hardcoded to 2; returns `[]` for a non-existent location id; round-trips through `resolveLocationFromAttributes()` back to the same location id.
- Full suite (`./vendor/bin/pest`) still passes at 99 tests / 171 assertions; `phpstan`/`pint` clean.

## Deferred

- A feature test for `refreshFromCentral()` itself against a faked OData feed (exercising the full adopt-from-Central path, not just the pure resolution/build methods) remains out of scope for this pass.
- `loc{n}_type` sanity-check logging and auto-creating missing `Location` rows were both in the original plan but dropped for this pass (see the plan doc's "Revised decisions").
