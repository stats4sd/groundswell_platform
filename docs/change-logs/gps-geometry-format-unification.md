# Change log: Unify GPS storage on Central to the `geometry` property format

Implements [the plan](../plans/gps-geometry-format-unification.md). Farms registered directly in Enketo store GPS as a single `geometry` Entity property (ODK's geopoint string format, `"lat lon alt acc"`, e.g. `"45.4215 -75.6972 70.0 4.5"`); farms created through the app instead wrote four separate properties (`latitude`/`longitude`/`altitude`/`accuracy`). Dan found this mismatch when comparing farms from both sources on Central.

## Changes

- **`App\Services\OdkFarmEntityService`**:
  - New `GEOMETRY_FIELD` constant (`'geometry'`).
  - New `buildGeometryValue(?float $latitude, ?float $longitude, ?int $altitude, ?float $accuracy): ?string` - builds the space-separated string; returns `null` if latitude or longitude is missing; defaults a missing altitude/accuracy to `0`.
  - New `parseGeometryValue(string $geometry): array` - the reverse, returning the same four-key shape `getEntityData()` already used for `GPS_FIELDS`.
  - `getEntityData()` now recognizes a `geometry` property and parses it into the GPS section's fields - alongside the existing (now legacy-only) `GPS_FIELDS` four-property read, for backward compatibility with farms already written that way.
  - `createFarm()`/`updateFarm()` now write a single `geometry` property (via `buildGeometryValue()`) instead of four separate ones - the rest of each method's identifier/property reconciliation pipeline needed no changes since it already treated GPS keys generically.
- **`FarmEntityResource::table()`** - the dynamic property columns' exclusion list now also excludes `GEOMETRY_FIELD`, so it doesn't leak through as a generic property column (it already has its own dedicated GPS section on the form).
- **Tests**: `tests/Feature/Services/OdkFarmEntityServiceGpsTest.php` (new) - unit coverage for `buildGeometryValue()`/`parseGeometryValue()` including the round-trip and the missing-lat/lon/altitude/accuracy edge cases.

## Out of scope

- The Excel "Import Farm list" flow (`bulkCreateFarms()`/`FarmEntityImport`/`ImportFarmsAction`) has no GPS column-mapping at all today, so there's nothing there to convert - GPS currently only ever enters the app through the manual Create/Edit Farm Entity form.
- No backfill of already-synced farms with the old four-property format - they'll keep reading correctly via the backward-compat path in `getEntityData()`, and only move to `geometry` if/when they're next edited and saved.
- Not yet manually verified against a real ODK Central server.
