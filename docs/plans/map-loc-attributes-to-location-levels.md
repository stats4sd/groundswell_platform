# Plan: Auto-map `loc{n}`/`loc{n}_name`/`loc{n}_type` entity attributes to Location Levels

**Status: Completed**

Implemented the core resolution + wiring described below, narrowed by decisions made
2026-07-13 (see "Revised decisions" below): match-only against existing Locations (no
auto-create), matched by name **and** hierarchy position (`location_level_id`), and no
`loc{n}_type` sanity-check. Both directions (`resolveLocationFromAttributes()` read side,
`buildLocationAttributes()` write side) now have unit test coverage -
`tests/Feature/Services/OdkFarmEntityServiceLocationTest.php`. A live-feed feature test for
`refreshFromCentral()` itself (faking the OData response) remains out of scope - see
"Deferred" in the change log. See
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
- Tests (2026-07-13): `tests/Feature/Services/OdkFarmEntityServiceLocationTest.php` — unit coverage for `resolveLocationFromAttributes()` (deepest-position resolution, chain-length capping, case-insensitive match, position-scoped match to avoid same-name collisions, owner/team scoping, no-match/no-config null returns) and `buildLocationAttributes()` (arbitrary-depth chain walk, empty result for an unknown location, round-trip symmetry with `resolveLocationFromAttributes()`). A feature test exercising `refreshFromCentral()` itself against a faked OData feed remains deferred - out of scope for this pass, see change log.

## Verification

- `./vendor/bin/phpstan analyse`, `./vendor/bin/pint`, `./vendor/bin/pest` — all pass (113 tests, 189 assertions as of 2026-07-16).
- Manual test against the real ODK Central server (per this feature's existing pattern): submit a Farm Registration form in Enketo with `loc1`/`loc2` values matching an existing Location, load the Farms list in the app, confirm the farm's location resolves without any manual import step.

## Reverse direction: writing `loc{n}` attributes when the app creates/updates a farm (2026-07-13)

After the above shipped, Dan found that farms created **through the app** (Create/Import, not Enketo) - which already have a correct `FarmEntity.location_id` chosen by a human - were syncing to Central with no `loc{n}`/`loc{n}_name`/`loc{n}_type` properties at all. Central-side cascading selects (and anything else reading these properties, e.g. downstream form filtering by cluster/group) never saw them, so those farms didn't show up under their correct cluster/group even though the app itself had them filed under the right `Location`.

This is the exact reverse of `resolveLocationFromAttributes()`: given a `location_id` the app already knows, derive the same `loc{n}` triplet the read side expects.

- **New method**, `OdkFarmEntityService::buildLocationAttributes(int $locationId): array`: loads the `Location`, walks its `parent` chain up to the root. Each level's position `n` comes from `LocationLevel->pos` (the same `getPos()`-backed 1-indexed root-first position `farmLevelChain()`/`resolveLocationFromAttributes()` already rely on) - **not hardcoded to any fixed depth**, so it works for however many levels a team has configured (2, 3, 4+). For each level: `loc{n}` = fixed placeholder value `"1"` (revised 2026-07-13, then further revised 2026-07-15 - see "Hardcoded internal group/cluster IDs" below; presence signals "this level has data" for levels the 2026-07-15 lookup doesn't cover), `loc{n}_name` = the Location's `name`, `loc{n}_type` = the fixed placeholder string `"Loc{n} name"` (matching the deployed XLSForm template's own placeholder - see decision 3 above, `_type` was never meaningful even on the read side).
- **Wired into all three app-side write paths** in `OdkFarmEntityService`: `createFarm()`, `bulkCreateFarms()` (computed once per distinct `location_id` in the batch, reused across rows), and `updateFarm()` - each already receives a `$locationId`/`$rows[].locationId` parameter; the derived attributes are merged into the same `$rawData`/`$keyTypes` pipeline as identifiers/properties/GPS, so they get reconciled as Central dataset properties like anything else. `updateFarm()`'s existing previously-set-names diff already clears any `loc{n}` key that disappears if a farm's location later resolves to a shallower chain - no special-case needed there.
- **Backfilling the two already-imported test farms**: since this only affects the write path, farms already synced without these properties need one more `updateFarm()` call to pick them up - re-saving each farm from its Edit page in the app (with its `location_id` unchanged) will now include the derived `loc{n}` attributes on that update.
- **Revised 2026-07-13**: `loc{n}` (the bare number, not `_name`) is hardcoded to `"1"` rather than the Location's `code` - per Dan, only its presence (not its value) matters downstream.

## Debugging aid + owner scoping fix (2026-07-13)

While diagnosing two test farms imported via "Import Farm list" that ended up with no `location_id` match and empty `loc{n}*` properties on Central:

- Added `ray()` calls (temporary, per this repo's existing debugging convention - see git history for prior `ray()` removal in this same service) at two points: `FarmEntityImport::collection()` (dumps each row's location-match attempt, and the final `$preparedRows` sent to `bulkCreateFarms()`), and `OdkFarmEntityService::bulkCreateFarms()` (dumps each prepared entity's `location_id` and full `data` payload right before the Central API call). Both are queued-job-safe since `ray()` works regardless of process context, unlike `dd()`/`dump()`.
- **Real bug found and fixed**: `FarmEntityImport.php`'s location lookup (`Location::where('code', ...)->where('location_level_id', ...)`) had no `owner_id` scope at all - `Location` (unlike `LocationLevel`) has no automatic tenant global scope, so this could match (or fail to match) a different team's `Location` row if `code` values aren't unique across teams. Fixed by resolving `$team` before the row-mapping loop (previously resolved after) and adding `->where('owner_id', $team->id)` to the query.

## Hardcoded internal group/cluster IDs for loc1/loc2 (2026-07-15, superseded 2026-07-16 - see below)

After both write and read directions were working, Dan compared a farm registered directly via Enketo against a farm created through the app (via Create or Import) and found the app-created ones always showed up under the *first* cluster and first group in the downstream system that reads these attributes - regardless of which cluster/group they were actually filed under in the app. Cause: the `"1"` placeholder from the 2026-07-13 revision was never meant to be load-bearing for `loc1`/`loc2` specifically - but for *this* team's XLSForm, `loc1`/`loc2` **are** load-bearing: they carry the real internal cluster ID / internal group ID as defined in the ODK form's own choice list (used to drive Enketo's cascading selects), which every farm registered directly in Enketo already had baked in, but the app had no way to derive since these IDs don't exist anywhere in this app's own `Location`/`LocationLevel` records.

At the time, this was fixed by hardcoding a `GROUP_CLUSTER_LOOKUP` constant (a 30-entry name→ID table transcribed from Dan's reference sheet) with `resolveGroupClusterIds()` doing a case-insensitive lookup against it, special-cased to only the farm's own Location and its immediate parent. **This entire mechanism was replaced the next day** - see below - once investigation found the real IDs were already available generically per-team. The rest of this subsection is kept for history only.

## Generic per-team resolution via the team's own Xlsform choice lists (2026-07-16)

Dan pointed out the hardcoded table couldn't be the right long-term answer: teams define their own location levels (2-level Cluster/Group, 3-level District/Sub-district/Village, or anything else), so nothing about `loc{n}` should be hardcoded to specific level names or a fixed number of levels. He asked whether the real internal IDs could instead be found in each team's own XLSForm or its related local tables.

Investigation (see the conversation's findings, corroborated against the real team-3 database) confirmed they can: when an XLSForm template is imported, the filament-odk-link package's existing pipeline already parses its `choices` sheet into `ChoiceList` (`list_name`) + `ChoiceListEntry` (`name`, `cascade_filter`) + `LanguageString` (label text) rows. For this team's real deployed Farm Registration Xlsform, its choice lists are literally named `loc1` (7 entries) and `loc2` (30 entries) - the exact same 1-indexed, root-first numbering `LocationLevel::pos()`/`farmLevelChain()` already use - and every one of the 30 `loc2` entries' `name` (internal group ID), `cascade_filter` (internal cluster ID), and label (via a `LanguageString` of type `label`) matched Dan's original hardcoded table exactly.

This is fully generic: for a team with N levels whose form's choices sheet follows the same `loc1`/`loc2`/`loc3`/... convention, this works for any N, with no per-team or per-level hardcoding anywhere in the app.

- **Removed entirely**: `GROUP_CLUSTER_LOOKUP` constant and `resolveGroupClusterIds()` method.
- **New**: `OdkFarmEntityService::resolveChoiceIdForLocation(Team $team, int $pos, ?string $locationName): ?string` - searches all of the team's Xlsforms' module versions for a `ChoiceList` where `list_name = "loc{pos}"`, then matches a `ChoiceListEntry` by its `label` language string (case-insensitive). Returns `null` (not an error) if the team has no such choice list yet, or nothing matches - callers fall back to the existing `"1"` placeholder in that case, same safe-degrade behavior as before.
- **`buildLocationAttributes()` simplified**: every level in the chain (not just the farm's own Location + immediate parent) now goes through the same `resolveChoiceIdForLocation()` call, uniformly, regardless of how many levels or what they're named - no more special-casing tied to "Cluster"/"Group" specifically.
- **Model fix found along the way**: `Location::owner(): BelongsTo` was missing its `@return BelongsTo<Team, $this>` docblock (present on the equivalent `FarmEntity::owner()`) - without it, Larastan couldn't resolve `$location->owner`'s type as `Team`, only generic `Model`. Added it (a real, narrowly-scoped fix, not a workaround).
- **Tests**: replaced the 3 hardcoded-table-specific test cases with 5 new ones in `tests/Feature/Services/OdkFarmEntityServiceLocationTest.php`, seeding a real `Xlsform`/`ChoiceList`/`ChoiceListEntry`/`LanguageString` chain at the DB level (matching the filament-odk-link package's own test convention, since no factories exist for these models) - exact label match, case-insensitive match, a 3-level generalization test (District/Sub-district/Village, proving this isn't Cluster/Group-specific), and two fallback cases (no Xlsform at all; an Xlsform whose choice list doesn't contain the label).
- **Verified against real production data** (team 3, `php artisan tinker`): `buildLocationAttributes()` for the real Location "Golmadevi Mahila Krishak Samuha" now returns `loc1 => '7', loc2 => '9'` - matching Dan's original table exactly, with zero hardcoding.
- **Known limitation, carried forward**: this depends on whichever team designs their XLSForm choices sheet actually naming their lists `loc1`/`loc2`/`loc3`... - confirmed for this one real form; not yet cross-checked against a second team/template, since none other in this database has location data yet.
