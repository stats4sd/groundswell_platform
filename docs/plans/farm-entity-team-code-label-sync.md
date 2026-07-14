# Plan: Read farm_entities.team_code from Central's `label`, not a `team_code` property

**Status: Completed**

See [change log](../change-logs/farm-entity-team-code-label-sync.md).

## Context

`OdkFarmEntityService::createFarm()`/`updateFarm()` already push the app's `team_code` to
Central as the Entity's `label` (not as a same-named property) - `team_code` and `label`
are meant to be the same value. But `refreshFromCentral()`'s adopt logic (for entities that
exist on Central without a matching local `FarmEntity` row, e.g. registered directly in
Enketo) read the *other* direction inconsistently: it preferred a `team_code` **property**
first, only falling back to `label` if that property was absent. Entities registered
directly via the Farm Registration XLSForm have no `team_code` property at all (their
`entities` sheet doesn't define one) - only `label` - so this mismatch is mostly latent,
but is the wrong source of truth even where a stray `team_code` property does exist.

## Decision

`refreshFromCentral()` now always reads `team_code` from the entity's `label`, matching the
write side exactly. Applied in two places:

1. **Adopting a new entity** (no local `FarmEntity` row yet): `team_code` set from `label`
   directly (falling back to the entity's `uuid` only if `label` is somehow empty, since
   `farm_entities.team_code` is a non-nullable column).
2. **An already-adopted entity**: if its local `team_code` no longer matches Central's
   current `label`, update it to match - keeps things in sync if `label` was ever changed
   directly on Central (e.g. via Central's own UI) rather than through this app's Edit form.
   Mirrors the existing "keep retrying until it resolves" pattern already used for
   `location_id` in this same loop (see
   [map-loc-attributes-to-location-levels.md](map-loc-attributes-to-location-levels.md)).

## Verification

- `./vendor/bin/phpstan analyse`, `./vendor/bin/pint`, `./vendor/bin/pest` - all pass (107 tests, 179 assertions as of 2026-07-13, no new tests added for this specific change - see change log).
