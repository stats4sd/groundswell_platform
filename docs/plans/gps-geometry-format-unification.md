# Plan: Unify GPS storage on Central to the `geometry` property format

**Status: Completed**

See [change log](../change-logs/gps-geometry-format-unification.md).

## Context

Farms registered directly in Enketo (the Farm Registration XLSForm) store GPS as a single
ODK Central Entity property named `geometry`, formatted as ODK's own geopoint string
representation: four space-separated values, `latitude longitude altitude accuracy` (e.g.
`45.4215 -75.6972 70.0 4.5`).

Farms created through the app (`OdkFarmEntityService::createFarm()`/`updateFarm()`,
via the Create/Edit Farm Entity Filament pages) instead wrote four separate properties -
`latitude`, `longitude`, `altitude`, `accuracy` (`GPS_FIELDS`) - a format mismatch Dan found
when comparing farms from both sources on Central.

## Decisions

1. **Read side** (`getEntityData()`, which feeds the "GPS" section on `FarmEntityResource`):
   recognize a `geometry` property and parse it into the same four GPS fields the form
   already uses. The legacy four-separate-properties format (`GPS_FIELDS`) is still read,
   for backward compatibility with farms already written that way by the app - the two are
   mutually exclusive per farm in practice, so there's no real ambiguity.
2. **Write side** (`createFarm()`/`updateFarm()`): write a single `geometry` property
   instead of four - matching the Enketo-registered format going forward, so both creation
   paths agree. `bulkCreateFarms()` (the Excel "Import Farm list" flow) has no GPS
   parameters or column-mapping at all today - out of scope, nothing to change there.
3. Missing altitude/accuracy default to `0` when building `geometry` (matching how a
   geopoint widget behaves when a device doesn't report them) - only latitude+longitude
   being absent skips the `geometry` property entirely, same as before.

## Design

- **`OdkFarmEntityService::GEOMETRY_FIELD`** (new public const `'geometry'`).
- **`OdkFarmEntityService::buildGeometryValue(?float $latitude, ?float $longitude, ?int $altitude, ?float $accuracy): ?string`** (new) - the write-side conversion.
- **`OdkFarmEntityService::parseGeometryValue(string $geometry): array`** (new) - the read-side conversion, returning the same `['latitude' => ..., 'longitude' => ..., 'altitude' => ..., 'accuracy' => ...]` shape `getEntityData()` already returns.
- **`getEntityData()`**: new branch recognizing `GEOMETRY_FIELD` before the existing `GPS_FIELDS` branch.
- **`createFarm()`/`updateFarm()`**: `$gpsData` now built via `buildGeometryValue()` into a single `[GEOMETRY_FIELD => ...]` entry (or empty array) instead of four filtered keys - everything downstream (property reconciliation, `$rawData` merge) is unchanged since it already treated `$gpsData`'s keys generically.
- **`FarmEntityResource::table()`**: the dynamic property columns' exclusion list (`whereNotIn('name', ...)`) now also excludes `GEOMETRY_FIELD` alongside `GPS_FIELDS`, so it doesn't show up as a generic property column (it has its own dedicated GPS section already).

## Out of scope

- `bulkCreateFarms()`/`FarmEntityImport`/`ImportFarmsAction` - no GPS column-mapping exists in the "Import Farm list" wizard today; adding one would be a separate, larger change (new form field + row shape + wiring), not requested here.
- Backfilling already-synced farms that have the old four-property format - `getEntityData()`'s backward-compat read path already handles them; only a future edit-and-save would move them to `geometry` (via `updateFarm()`), and even then only if that farm's GPS is re-submitted.

## Verification

- `./vendor/bin/phpstan analyse`, `./vendor/bin/pint`, `./vendor/bin/pest` - all pass (107 tests, 179 assertions as of 2026-07-13).
- New unit tests: `tests/Feature/Services/OdkFarmEntityServiceGpsTest.php` (`buildGeometryValue()`/`parseGeometryValue()`, both directions plus round-trip).
- Not yet manually verified against a real ODK Central server / real Farm Registration submission - recommended before considering this fully proven out, same as the location-mapping feature it builds alongside.
