# Change log: Resolve FarmEntity.location_id from `loc{n}_name` entity attributes

Implements [the plan](../plans/map-loc-attributes-to-location-levels.md), narrowed per decisions made 2026-07-13: match-only against existing Locations (no auto-create), matched by name **and** hierarchy position, no `loc{n}_type` check. Unit/feature test coverage is deliberately deferred to a follow-up.

## Changes

- **`App\Models\SampleFrame\LocationLevel::farmLevelChain(Team $team): Collection`** (new) — root-to-leaf chain of a team's location levels, ending at the `has_farms = true` level. Extracted from the inline parent-walk previously duplicated in `ImportLocationsAndFarmEntities.php`.
- **`ImportLocationsAndFarmEntities.php`** — its column-mapping wizard step now builds its parent/farm-level questions from `LocationLevel::farmLevelChain(Filament::getTenant())` instead of its own inline walk.
- **`App\Services\OdkFarmEntityService::resolveLocationFromAttributes(Team $team, array $data): ?int`** (new) — parses `loc{n}_name` keys from an entity's flat property data, caps at the team's configured `farmLevelChain()` length (so a `loc4_name` beyond a 3-level chain is ignored - it's farm/household data, not a Location), takes the deepest remaining position, and matches it against an existing `Location` by `owner_id` + `location_level_id` + `name`. Returns `null` (no creation) if nothing matches.
- **`OdkFarmEntityService::refreshFromCentral()`** — the adopt branch now calls `resolveLocationFromAttributes()` instead of hardcoding `location_id => null`. Also retries resolution for existing `FarmEntity` rows that still have `location_id === null` on every refresh, since this method already re-runs the whole feed on every list-page view.

## Follow-up (2026-07-13, after manual testing)

- Confirmed working end-to-end by Dan against real ODK Central data.
- Name matching in `resolveLocationFromAttributes()` changed to case-insensitive (`whereRaw('LOWER(name) = ?', [Str::lower(...)])`) to avoid unmatched locations purely from case differences between ODK data entry and the app's `Location.name` values.

## Deferred

- Unit tests for the parsing/cap logic and a feature test for `refreshFromCentral()` against a faked OData feed - per Dan, added in a follow-up.
- `loc{n}_type` sanity-check logging and auto-creating missing `Location` rows were both in the original plan but dropped for this pass (see the plan doc's "Revised decisions").
