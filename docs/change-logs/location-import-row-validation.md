# Change log: LocationImport row validation and failure visibility

Follow-up to [farm-crud-cutover-and-import-chaining.md](farm-crud-cutover-and-import-chaining.md). Diagnosed from a real failed import on `remove-extra-holpa-items` (`storage/logs/laravel.log`, 2026-08-02 10:18:36).

## The failure

```
SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'code' cannot be null
insert into `locations` (`owner_id`, `code`, `name`, `location_level_id`, `parent_id`, ...)
values (5, ?, Lalbandi, 1, ?, ...)
```

One row of a 392-row CSV (`Farm_Summary_2206.csv`, record 379) had a blank `loc1` cell — the column the user had mapped to the Municipality unique code — while `loc1_name` held `Lalbandi`. `locations.code` and `locations.name` are both `NOT NULL`, and `LocationImport` had no validation, so the blank went straight into `Location::create()`.

Three things then compounded it:

- `Maatwebsite\Excel\Jobs\ReadChunk` wraps a whole chunk in one transaction and `chunkSize()` is 1000, so all 392 rows rolled back — 0 locations from one bad cell.
- The farm import is the tail of the location import's job chain (that chaining is what fixed finding M13), so the abort cancelled it too — 0 farm entities.
- `FarmEntityImport` was the only importer in this flow that notified the user on failure. With it never running, and `LocationImport`'s `ImportFailed` handler only writing to `imports.errors` — a column nothing in the app renders — the whole failure was silent. The wizard said the file was being processed in the background; nothing ever contradicted it.

The chaining change did not cause the error (`LocationImport::collection()` is unchanged from `dev`), but it did turn a partial, noisy failure into a total, silent one.

## Changes

All in `app/Imports/LocationImport.php`.

| Change | Detail |
| --- | --- |
| `WithValidation` added | `rules()` requires every column the user mapped — code and name, at the level being imported and at every parent level in the chain. A blank cell is now a row-level validation failure instead of a chunk-wide `QueryException`. |
| `customValidationMessages()` | Names both the spreadsheet column and the mapping the user chose for it: `The 'loc1' column is empty. You mapped this column to the Municipality unique code, and every row must have a value in it.` |
| `requiredColumns()` | Private helper backing both of the above. Resolves parent level names in one `whereIn(...)->pluck()` rather than a query per level. |
| `formatFailures()` | `imports.errors` now always gets the `[{row, attribute, errors}]` shape used by `FarmEntityImport` and `QueueFarmEntityImport`. It previously wrote a bare exception-message string here, which the `'collection'` cast turned into a one-element list — a fourth payload shape for any future reader to handle. |
| `notifyFailure()` | The user now gets a `danger` notification (`sendToDatabase` + `broadcast`) when a location import fails, mirroring `FarmEntityImport`. A failed location import used to produce no user-facing output at all. |
| `describeFailure()` | Plain-text failure summary shared by the notification body and the dependent farm import's "skipped because..." record. Capped at 10 rows, since one badly prepared file can fail every row. Escaped with `e()` + `nl2br()` in the notification body. |

`Import::find(...)` in the `ImportFailed` handler is now null-safe.

## Verified

- `tests/Feature/Imports/ImportLocationsAndFarmEntitiesChainingTest.php` — four new tests (blank code reported against its row and column with no locations created; the dependent farm import is told which row broke it; `rules()` covers the level and every parent level; the user is notified). The two existing assertions on the old bare-string error shape were updated to the canonical shape.
- Full suite: 171 passed. `pint` and `phpstan` clean.
- Replayed against the real CSV that failed: the import now reports `row 379`, `attribute loc1`, with the mapped-column message, and the farm import's record carries `Row 379: ...` instead of a raw SQL statement.

## Not addressed

- **`imports.errors` is still never rendered anywhere.** The notification is now the only path by which a user learns anything. Planned in [surface-import-errors-in-ui.md](../plans/surface-import-errors-in-ui.md).
- **A single bad row still costs the whole file.** Validation fails the import rather than skipping the row (`SkipsOnFailure` is deliberately not used, matching `FarmEntityImport`), and the chunk transaction still rolls back all 392 rows. Partial imports of a location hierarchy are arguably worse than none, but this should be a conscious decision rather than a side effect of `chunkSize()`.
- ~~**`LocationImport::collection()` runs ~6 queries per row** (count/create/first per level, no caching), so 392 rows occupy a worker for about a minute.~~ Fixed in [location-import-query-reduction.md](location-import-query-reduction.md) — the reads were only part of it; the write-side `saved` hook and `$touches` cascade cost more.
- **The broadcast blocks when Reverb is down.** Pre-existing — `LocationImport`'s `AfterImport` handler already broadcast — but the new failure notification widens the window in which a queue worker can stall on an unreachable Reverb.
