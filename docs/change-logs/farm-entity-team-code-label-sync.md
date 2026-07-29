# Change log: Read farm_entities.team_code from Central's `label`, not a `team_code` property

Implements [the plan](../archive/plans/farm-entity-team-code-label-sync.md). `createFarm()`/`updateFarm()` already push `team_code` to Central as the Entity's `label`; `refreshFromCentral()`'s adopt logic read the other direction inconsistently, preferring a `team_code` property (which entities registered directly in Enketo never have) over `label`.

## Changes

- **`OdkFarmEntityService::refreshFromCentral()`**:
  - Adopting a new entity (`FarmEntity::create(...)`): `team_code` is now set from the entity's `label` directly (falling back to `uuid` only if `label` is empty), instead of preferring a `team_code` property that may not exist.
  - Already-adopted entities: if the local `team_code` no longer matches Central's current `label`, it's updated to match - keeps them in sync if `label` changes directly on Central. Mirrors the existing per-refresh retry pattern already used for `location_id` in this same loop.

## Not done

- No new automated test for this specific change - it's a small, narrowly-scoped correction to an existing loop that already had coverage gaps (this loop's `refreshFromCentral()` itself has no feature test yet, tracked as deferred in `map-loc-attributes-to-location-levels.md`).
