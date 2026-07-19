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

## Follow-up (2026-07-14): GPS support for the Excel import flow

Dan deleted local `farm_entities` and re-synced from Central (re-adopting everything fresh). Farms from the Farm Registration form showed complete GPS; farms imported via "Import Farm list" showed latitude/longitude missing - not a bug in the geometry conversion above, but a genuine gap: that import path never collected GPS at all.

- **`ImportFarmsAction::getDefaultForm()`** - new "GPS" section with four optional column-mapping `Select` fields (`latitude_column`, `longitude_column`, `altitude_column`, `accuracy_column`); a new `gpsColumnValues()` helper wires them into the existing `farm_identifiers`/`farm_properties` `disableOptionWhen()` checks so a column can't be double-mapped.
- **`FarmEntityImport::collection()`** - resolves the (optional) mapped columns and casts each row's cell to `?float`/`?int`, added to the row array.
- **`OdkFarmEntityService::bulkCreateFarms()`** - builds a `geometry` value per row via `buildGeometryValue()` (not deduped across rows like location attributes - GPS varies per farm), only registers `GEOMETRY_FIELD` for reconciliation if the batch has at least one row with GPS.
- **PHPStan fix along the way**: `bulkCreateFarms()`'s `$rows` docblock lost its literal array-shape generic (kept as prose instead) - `Collection`'s `TValue` isn't covariant, so the shape rejected `FarmEntityImport`'s `Collection` even though both sides were textually identical. Known PHPStan/Larastan limitation, not a real bug.
- **Deliberately not done**: legacy `FarmImport`/`Farm` model import wasn't wired to read these new columns (they're shown in the shared wizard but silently ignored there) - flag if that's also wanted, out of scope for this FarmEntity-focused pass.

## Follow-up (2026-07-14, later same day): latitude/longitude/accuracy rendering as integers

After testing the import with whole-number GPS values, Dan reported latitude/longitude/accuracy showing as integers in `geometry` (e.g. `45` instead of `45.0`). Root cause: PHP's `(string)` cast drops the decimal point for a whole-number float (`(string) 45.0 === '45'`) - the underlying values were genuinely floats throughout (verified: every parameter/property declaration was already `?float`), just rendered without a decimal, indistinguishable from an integer.

- **`OdkFarmEntityService::formatGpsFloat(float $value): string`** (new, protected) - appends `.0` if the plain string cast has no decimal point.
- `buildGeometryValue()` now applies this to latitude, longitude, and `accuracy ?? 0` - altitude deliberately left as a plain int (Dan's report only covered the other three; altitude's `?int` type is unchanged everywhere).
- Applies to both write paths automatically (single-farm Create/Edit form and the Excel import), since both call the same `buildGeometryValue()`.
- New test: "always renders latitude/longitude/accuracy with a decimal point, even for whole numbers"; updated the existing "defaults a missing altitude and accuracy to 0" test's expectation (accuracy's default now renders `0.0`, not `0`).

## Follow-up (2026-07-14, later same day): removed the dedicated GPS section from the import wizard

Per Dan: no separate "GPS" step needed - auto-detect GPS columns by name among whatever's already ticked in the existing "Farm Information" identifier/property checkboxes, same as how GPS is detected by name elsewhere in this app.

- **`ImportFarmsAction::getDefaultForm()`** - removed the "GPS" section and its four `Select` fields, and the now-unused `gpsColumnValues()` helper (along with its use in the identifier/property `disableOptionWhen()` checks). Updated both checkboxes' helper text to mention that a column literally named Latitude/Longitude/Altitude/Accuracy is automatically treated as GPS.
- **`FarmEntityImport::collection()`** - after building `$identifierColumns`/`$propertyColumns` from the ticked checkboxes, scans their merged header names (case-insensitive exact match) for `latitude`/`longitude`/`altitude`/`accuracy`; a match is pulled into the corresponding GPS variable and removed from `$identifierColumns`/`$propertyColumns` via `->reject()`, so it isn't double-counted as a generic identifier/property. The per-row float/int casting is otherwise unchanged.

## Out of scope

- No backfill of already-synced farms with the old four-property format - they'll keep reading correctly via the backward-compat path in `getEntityData()`, and only move to `geometry` if/when they're next edited and saved.
- No additional Excel validation for the GPS columns (e.g. asserting numeric values).
- Not yet manually verified against a real ODK Central server.
