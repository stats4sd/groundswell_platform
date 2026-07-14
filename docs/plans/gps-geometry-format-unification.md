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
   paths agree.
3. Missing altitude/accuracy default to `0` when building `geometry` (matching how a
   geopoint widget behaves when a device doesn't report them) - only latitude+longitude
   being absent skips the `geometry` property entirely, same as before.
4. **Revised 2026-07-14**: the Excel "Import Farm list" flow (`bulkCreateFarms()`/
   `FarmEntityImport`/`ImportFarmsAction`) originally had no GPS column-mapping at all -
   flagged as out of scope in the first pass. After Dan re-synced (deleted local
   `farm_entities`, re-adopted from Central) and found imported farms genuinely had no GPS
   at all (not a bug in the geometry conversion - there was simply nothing to convert, since
   the import never collected GPS), this was added. First cut: a dedicated "GPS" section
   with four column-mapping `Select` fields. **Revised again same day, per Dan**: removed
   that dedicated section entirely - GPS columns are now auto-detected by name (case-
   insensitive exact match against "latitude"/"longitude"/"altitude"/"accuracy") among
   whatever the user already ticks in the existing "Farm Information" section's identifier/
   property checkboxes, mirroring how GPS is detected by name elsewhere in this app
   (`OdkFarmEntityService::GPS_FIELDS`) rather than needing its own UI step.
5. **Revised 2026-07-14 (later same day)**: after testing the import with whole-number GPS
   values, Dan found latitude/longitude/accuracy rendered in `geometry` with no decimal
   point (e.g. `45` instead of `45.0`) - PHP's `(string)` cast drops the decimal point for a
   whole-number float, making it indistinguishable from an integer once written. Fixed by
   always rendering these three fields with an explicit decimal point (altitude
   deliberately excluded - out of scope per Dan, stays a plain integer).

## Design

- **`OdkFarmEntityService::GEOMETRY_FIELD`** (new public const `'geometry'`).
- **`OdkFarmEntityService::buildGeometryValue(?float $latitude, ?float $longitude, ?int $altitude, ?float $accuracy): ?string`** (new) - the write-side conversion.
- **`OdkFarmEntityService::parseGeometryValue(string $geometry): array`** (new) - the read-side conversion, returning the same `['latitude' => ..., 'longitude' => ..., 'altitude' => ..., 'accuracy' => ...]` shape `getEntityData()` already returns.
- **`getEntityData()`**: new branch recognizing `GEOMETRY_FIELD` before the existing `GPS_FIELDS` branch.
- **`createFarm()`/`updateFarm()`**: `$gpsData` now built via `buildGeometryValue()` into a single `[GEOMETRY_FIELD => ...]` entry (or empty array) instead of four filtered keys - everything downstream (property reconciliation, `$rawData` merge) is unchanged since it already treated `$gpsData`'s keys generically.
- **`FarmEntityResource::table()`**: the dynamic property columns' exclusion list (`whereNotIn('name', ...)`) now also excludes `GEOMETRY_FIELD` alongside `GPS_FIELDS`, so it doesn't show up as a generic property column (it has its own dedicated GPS section already).
- **`ImportFarmsAction::getDefaultForm()`** (2026-07-14, superseded same day - see below): no dedicated GPS section - `farm_identifiers`/`farm_properties` helper text now mentions the name-matching behavior instead.
- **`FarmEntityImport::collection()`** (2026-07-14, revised same day): after building `$identifierColumns`/`$propertyColumns` from the ticked checkboxes, scans their merged header names (case-insensitive) for exact matches against `latitude`/`longitude`/`altitude`/`accuracy`; any match is pulled into the corresponding GPS column and removed from `$identifierColumns`/`$propertyColumns` (via `->reject()`) so it isn't also synced as a generic identifier/property. Each row's raw cell value is then cast to `?float`/`?int` via small typed closures, same as the superseded column-mapping version.
- **`OdkFarmEntityService::bulkCreateFarms()`** (2026-07-14): builds one `geometry` value per row via `buildGeometryValue()` (not deduped like `locationAttributesByLocationId` - GPS varies per row, and the computation is cheap pure string formatting), registers `GEOMETRY_FIELD` for reconciliation only if at least one row in the batch has GPS, and merges the row's `geometry` into its `$rawData` alongside identifiers/properties/location attributes.
- **PHPStan note**: `bulkCreateFarms()`'s `$rows` parameter's docblock was deliberately left without a literal array-shape generic (just `Collection`, described in prose instead) - `Collection`'s `TValue` generic isn't covariant, so a literal shape rejected the `Collection` `FarmEntityImport` passes even though the shapes were textually identical (a known PHPStan/Larastan limitation, not a real type error).
- **`OdkFarmEntityService::formatGpsFloat(float $value): string`** (new, protected) - guarantees a decimal point (appends `.0` if `(string) $value` has none). Applied to latitude, longitude, and `accuracy ?? 0` inside `buildGeometryValue()`; altitude is left as a plain int, matching its `?int` type throughout the codebase and Dan's explicit scope for this fix.

## Out of scope

- Backfilling already-synced farms that have the old four-property format - `getEntityData()`'s backward-compat read path already handles them; only a future edit-and-save would move them to `geometry` (via `updateFarm()`), and even then only if that farm's GPS is re-submitted.
- Legacy `FarmImport`/`Farm` model import (`ListFarms.php`) - the GPS column-mapping fields were added to the shared `ImportFarmsAction` wizard, but only `FarmEntityImport` reads them; `FarmImport::collection()` was not touched, so GPS columns mapped while importing legacy Farms are currently silently ignored. Flag if legacy Farm import also needs this - it wasn't in scope for this pass, which is about `FarmEntity`/Central sync specifically.
- No additional Excel validation for the GPS columns (e.g. asserting numeric values) - malformed cells simply fail the `(float)`/`(int)` cast to `0`/`NAN`-adjacent PHP coercion behavior same as any other numeric cast in this codebase, not specially guarded.

## Verification

- `./vendor/bin/phpstan analyse`, `./vendor/bin/pint`, `./vendor/bin/pest` - all pass (108 tests, 180 assertions as of 2026-07-14).
- New unit tests: `tests/Feature/Services/OdkFarmEntityServiceGpsTest.php` (`buildGeometryValue()`/`parseGeometryValue()`, both directions plus round-trip, plus the whole-number-GPS regression test for the decimal-point fix). No new test added for `bulkCreateFarms()`'s per-row geometry wiring itself - that method has no existing test coverage at all (it talks to Central), consistent with this plan's existing deferred-testing precedent.
- Not yet manually verified against a real ODK Central server / real Farm Registration submission - recommended before considering this fully proven out, same as the location-mapping feature it builds alongside.
