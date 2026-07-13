# Plan: Auto-map `loc{n}`/`loc{n}_name`/`loc{n}_type` entity attributes to Location Levels

**Status: In Progress**

Implemented the core resolution + wiring described below, narrowed by decisions made
2026-07-13 (see "Revised decisions" below): match-only against existing Locations (no
auto-create), matched by name **and** hierarchy position (`location_level_id`), and no
`loc{n}_type` sanity-check. Unit/feature test coverage (this plan's own "Verification"
section) is deliberately deferred to a follow-up. See
[change log](../change-logs/map-loc-attributes-to-location-levels.md).

## Revised decisions (2026-07-13, supersede the corresponding items below)

1. **No auto-creation.** `resolveLocationFromAttributes()` only matches against Locations
   the team already has (`Location::where('owner_id', ...)->where('location_level_id', ...)
   ->where('name', ...)`) - it never `firstOrCreate`s a Location. If nothing matches,
   `location_id` is left `null`, same as today's behavior for entities with no location data
   at all. This was judged simpler and safer than auto-creating from unverified ODK data,
   at the cost of not resolving farms whose location doesn't exist locally yet (those farms
   simply stay unresolved until the location is created and the entity is re-adopted).
2. **Matched by name + hierarchy position, not name alone.** The match includes
   `location_level_id` (the `LocationLevel` at the resolved `loc{n}` position in the team's
   `farmLevelChain()`), not just `owner_id` + `name`, to avoid a false match when the same
   name is reused at a different level (e.g. a district and a village both named "Kasese").
   The name comparison itself is case-insensitive (`LOWER(name) = LOWER(loc{n}_name)`,
   added 2026-07-13 after Dan's manual testing) - avoids unmatched locations purely from
   inconsistent casing between ODK data entry and the app's Location records.
3. **No `loc{n}_type` mismatch check.** Decision 3 below (log a warning on a `loc{n}_type`/
   level-name mismatch) was dropped as unnecessary for this pass.

## Context

Farm data is now sourced from ODK Central Entities (`FarmEntity` as a structural link only, see [odk-entities-farm-crud.md](odk-entities-farm-crud.md) and [farm-entities-simplify-and-gps-sync.md](farm-entities-simplify-and-gps-sync.md)). When a farm is registered directly in Enketo (via the Farm Registration XLSForm) rather than through the app's own Create/Import UI, `OdkFarmEntityService::refreshFromCentral()` "adopts" the resulting entity with `location_id` left `null` — there is currently no mechanism connecting an entity's location-hierarchy attributes back to the app's `Location`/`LocationLevel` records.

The deployed Farm Registration template encodes the location hierarchy as `loc1`/`loc1_name`/`loc1_type`, `loc2`/`loc2_name`/`loc2_type`, etc. — one triplet per location level, `loc1` being the topmost/root level, increasing with depth toward the level farms attach to. This numbering matches `LocationLevel::getPos()` (`app/Models/SampleFrame/LocationLevel.php:65-77`) exactly: `pos` is 1-indexed from the root down. No existing code derives or consumes this convention today — `LocationLevel::getCsvContentsForOdk()` (which exports `pos`) has zero callers; the `loc{n}` field names in the XLSForm are hand-authored, not generated.

The only existing hierarchy-from-columns logic is the manual import wizard (`LocationImport::collection()`, `app/Imports/LocationImport.php:66-125`), driven by a one-time spreadsheet upload with human column-mapping. This plan reuses its same "walk levels top-down, `firstOrCreate` each ancestor by code, chain `parent_id`" pattern, but drives it from live ODK entity attribute data instead.

## Decisions (agreed with Dan)

1. **Trigger**: automatic, inside `OdkFarmEntityService::refreshFromCentral()`'s existing per-entity adopt loop — no new UI, no separate action. Consistent with how that method already auto-adopts `FarmEntity` rows and auto-registers new `DatasetVariable`s.
2. **LocationLevel provisioning**: levels must already be configured by the team via the existing `LocationLevelResource` UI. This feature only ever auto-creates **`Location`** rows, never `LocationLevel` rows. If an entity has more `loc{n}` positions than the team has configured levels, the extra positions are skipped (logged, not fatal).
3. **`loc{n}_type` mismatch handling**: used only as a sanity check against the matched `LocationLevel->name` (case-insensitive, trimmed). A mismatch does not block resolution — the location is still resolved/created by position — but is logged (via `Log::warning` initially; a future admin-visible surfacing can be layered on later if this proves noisy in practice).

## Design

### 1. Parsing helper (as built)

Inline in `resolveLocationFromAttributes()` (no standalone class - there's only one caller): a regex over the entity's flat property array matching `loc(\d+)_name` keys, collected into `[n => name]`. `loc{n}`/`loc{n}_type` are not parsed - not needed once decision 3 above dropped the type check.

### 2. Shared level-chain lookup (as built)

`LocationLevel::farmLevelChain(Team $team): Collection` (`app/Models/SampleFrame/LocationLevel.php`) — starts at the team's `has_farms=true` level, walks `parent` to the root, returns the ordered list root-first. Both the import wizard (`ImportLocationsAndFarmEntities.php`) and the new resolver use this one implementation.

### 3. New resolution method (as built, revised per decisions above)

`OdkFarmEntityService::resolveLocationFromAttributes(Team $team, array $data): ?int`:

- Fetches `LocationLevel::farmLevelChain($team)`; returns `null` immediately if the team has no `has_farms` level configured yet.
- Parses `loc{n}_name` attributes from `$data` (per step 1), discarding any position beyond the chain's length (an entity's `loc{n}` numbering can run deeper than the team's configured Location levels - the extra positions are farm/household-level data, not Locations).
- If nothing remains, returns `null`.
- Otherwise takes the **highest remaining position** (the deepest resolvable `loc{n}_name` - your step 1's "highest location name") and matches:
  `Location::where('owner_id', $team->id)->where('location_level_id', $chain[pos]->id)->whereRaw('LOWER(name) = ?', [Str::lower($names[pos])])->first()`.
- Returns the matched `Location`'s id, or `null` if nothing matches - no creation (revised decision 1).

### 4. Wire into `refreshFromCentral()`

In the adopt branch (`app/Services/OdkFarmEntityService.php:650-657`, where `FarmEntity::create([..., 'location_id' => null, ...])` currently happens), call `resolveLocationFromAttributes($team, $values->all())` and use its result instead of the hardcoded `null`.

Also apply it when an *existing* local `FarmEntity` currently has `location_id === null` (an already-adopted farm from before this feature existed, or one adopted before its location attributes were available) — worth re-resolving on every refresh until it succeeds, since the read-through pattern already re-runs this loop on every list-page view.

## Out of scope / deliberately deferred

- Auto-creating `LocationLevel` records — teams configure these themselves (decision 2).
- Auto-creating `Location` records at all — dropped per revised decision 1; a farm whose location doesn't exist locally yet stays unresolved (`location_id = null`) until the location is created and the entity is re-adopted on a later refresh.
- Any `loc{n}_type` sanity check or its logging — dropped per revised decision 3.
- Applying this to `getEntityData()`/`updateFarm()`/the app's own Create form — those paths already have an explicit `location_id` supplied by a human via the app UI; this plan only closes the gap for entities created outside the app.
- Changing the Farm Registration XLSForm template itself — not needed since `_type` is no longer consulted at all.

## Implementation touchpoints

- `app/Models/SampleFrame/LocationLevel.php` — add `farmLevelChain(Team $team): Collection`.
- `app/Filament/App/Clusters/LocationLevels/Resources/FarmEntityResource/Pages/ImportLocationsAndFarmEntities.php` — refactor its inline parent-walk (lines 156-164) to use the new shared method.
- `app/Services/OdkFarmEntityService.php` — add `resolveLocationFromAttributes()`; call it from `refreshFromCentral()`'s adopt loop, and to retry resolution for existing `FarmEntity` rows with `location_id === null`.
- Tests: **deferred** (per Dan, 2026-07-13) — unit coverage for the `loc{n}_name` parsing/cap logic, and a feature test exercising `refreshFromCentral()` against a faked OData feed asserting `FarmEntity.location_id` resolves to the correct existing `Location`. Follow-up work.

## Verification

- `./vendor/bin/phpstan analyse`, `./vendor/bin/pint` (run for this change; pest coverage deferred, see above).
- Manual test against the real ODK Central server (per this feature's existing pattern): submit a Farm Registration form in Enketo with `loc1`/`loc2` values matching an existing Location, load the Farms list in the app, confirm the farm's location resolves without any manual import step.
