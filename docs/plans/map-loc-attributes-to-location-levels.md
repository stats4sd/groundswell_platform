# Plan: Auto-map `loc{n}`/`loc{n}_name`/`loc{n}_type` entity attributes to Location Levels

**Status: Not Started**

## Context

Farm data is now sourced from ODK Central Entities (`FarmEntity` as a structural link only, see [odk-entities-farm-crud.md](odk-entities-farm-crud.md) and [farm-entities-simplify-and-gps-sync.md](farm-entities-simplify-and-gps-sync.md)). When a farm is registered directly in Enketo (via the Farm Registration XLSForm) rather than through the app's own Create/Import UI, `OdkFarmEntityService::refreshFromCentral()` "adopts" the resulting entity with `location_id` left `null` — there is currently no mechanism connecting an entity's location-hierarchy attributes back to the app's `Location`/`LocationLevel` records.

The deployed Farm Registration template encodes the location hierarchy as `loc1`/`loc1_name`/`loc1_type`, `loc2`/`loc2_name`/`loc2_type`, etc. — one triplet per location level, `loc1` being the topmost/root level, increasing with depth toward the level farms attach to. This numbering matches `LocationLevel::getPos()` (`app/Models/SampleFrame/LocationLevel.php:65-77`) exactly: `pos` is 1-indexed from the root down. No existing code derives or consumes this convention today — `LocationLevel::getCsvContentsForOdk()` (which exports `pos`) has zero callers; the `loc{n}` field names in the XLSForm are hand-authored, not generated.

The only existing hierarchy-from-columns logic is the manual import wizard (`LocationImport::collection()`, `app/Imports/LocationImport.php:66-125`), driven by a one-time spreadsheet upload with human column-mapping. This plan reuses its same "walk levels top-down, `firstOrCreate` each ancestor by code, chain `parent_id`" pattern, but drives it from live ODK entity attribute data instead.

## Decisions (agreed with Dan)

1. **Trigger**: automatic, inside `OdkFarmEntityService::refreshFromCentral()`'s existing per-entity adopt loop — no new UI, no separate action. Consistent with how that method already auto-adopts `FarmEntity` rows and auto-registers new `DatasetVariable`s.
2. **LocationLevel provisioning**: levels must already be configured by the team via the existing `LocationLevelResource` UI. This feature only ever auto-creates **`Location`** rows, never `LocationLevel` rows. If an entity has more `loc{n}` positions than the team has configured levels, the extra positions are skipped (logged, not fatal).
3. **`loc{n}_type` mismatch handling**: used only as a sanity check against the matched `LocationLevel->name` (case-insensitive, trimmed). A mismatch does not block resolution — the location is still resolved/created by position — but is logged (via `Log::warning` initially; a future admin-visible surfacing can be layered on later if this proves noisy in practice).

## Design

### 1. Parsing helper

A small regex-based parser recognizing attribute keys of the shape `loc(\d+)`, `loc(\d+)_name`, `loc(\d+)_type` from an entity's flat property array, grouping them by `n` into `[n => ['code' => ..., 'name' => ..., 'type' => ...]]`. Lives alongside the resolution logic (see below) rather than as a standalone class — there's only one caller.

### 2. Shared level-chain lookup

Extract the root→leaf `LocationLevel` walk currently duplicated inline in `ImportLocationsAndFarmEntities.php:156-164` into a reusable method, e.g. `LocationLevel::farmLevelChain(Team $team): Collection` — starts at the team's `has_farms=true` level, walks `parent` to the root, returns the ordered list root-first. Both the import wizard and the new resolver use this one implementation.

### 3. New resolution method

New method, e.g. `OdkFarmEntityService::resolveLocationFromAttributes(Team $team, array $data): ?int`:

- Parses `loc{n}` attributes from `$data` (per step 1).
- Fetches `LocationLevel::farmLevelChain($team)` (per step 2).
- Walks levels in order (position 1..N); for each position present in both the parsed attributes and the team's configured chain:
  - Logs a warning if `loc{n}_type` doesn't match the level's `name` (trimmed, case-insensitive) — resolution continues regardless.
  - `Location::firstOrCreate(['owner_id' => $team->id, 'code' => $code], ['name' => $name, 'location_level_id' => $level->id, 'parent_id' => $previousLocation?->id])`, matching `LocationImport::collection()`'s existing create shape.
  - If a position from the parsed attributes exceeds the team's configured chain length, stop and log a warning (level provisioning is out of scope per decision 2).
- Returns the deepest resolved `Location`'s id, or `null` if no `loc1` attribute was present at all (non-farm-hierarchy entities, or entities with no location data yet).

### 4. Wire into `refreshFromCentral()`

In the adopt branch (`app/Services/OdkFarmEntityService.php:650-657`, where `FarmEntity::create([..., 'location_id' => null, ...])` currently happens), call `resolveLocationFromAttributes($team, $values->all())` and use its result instead of the hardcoded `null`.

Also apply it when an *existing* local `FarmEntity` currently has `location_id === null` (an already-adopted farm from before this feature existed, or one adopted before its location attributes were available) — worth re-resolving on every refresh until it succeeds, since the read-through pattern already re-runs this loop on every list-page view.

## Out of scope / deliberately deferred

- Auto-creating `LocationLevel` records — teams configure these themselves (decision 2).
- Any UI surfacing of the `loc{n}_type` mismatch warning beyond application logs — revisit if this proves to be a real signal worth showing admins.
- Applying this to `getEntityData()`/`updateFarm()`/the app's own Create form — those paths already have an explicit `location_id` supplied by a human via the app UI; this plan only closes the gap for entities created outside the app.
- Changing the Farm Registration XLSForm template itself (e.g. the `loc{n}_type` calculation currently appears to emit a static placeholder string rather than the real level name in Dan's test template) — that's data-entry-side, not app-side, and not needed for this plan since `_type` is only a sanity check, not load-bearing.

## Implementation touchpoints

- `app/Models/SampleFrame/LocationLevel.php` — add `farmLevelChain(Team $team): Collection`.
- `app/Filament/App/Clusters/LocationLevels/Resources/FarmEntityResource/Pages/ImportLocationsAndFarmEntities.php` — refactor its inline parent-walk (lines 156-164) to use the new shared method.
- `app/Services/OdkFarmEntityService.php` — add `resolveLocationFromAttributes()`; call it from `refreshFromCentral()`'s adopt loop.
- Tests: unit coverage for the parser (`loc1`/`loc1_name`/`loc1_type` grouping, ignoring unrelated keys), and a feature test exercising `refreshFromCentral()` against a faked OData feed response with `loc1`/`loc2` attributes, asserting the created `FarmEntity.location_id` resolves to the correct nested `Location`.

## Verification

- `./vendor/bin/pest --filter=OdkFarmEntityService` (extend existing test coverage for this service).
- `./vendor/bin/phpstan analyse`, `./vendor/bin/pint`.
- Manual test against the real ODK Central server (per this feature's existing pattern): submit a Farm Registration form in Enketo with `loc1`/`loc2` values, load the Farms list in the app, confirm the farm's location resolves/creates the correct nested `Location` rows without any manual import step.
