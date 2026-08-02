# Location import query reduction

Follows up the performance issue recorded in [location-import-row-validation.md](location-import-row-validation.md) and repeated in [surface-import-errors-in-ui.md](../plans/surface-import-errors-in-ui.md): `LocationImport::collection()` ran about six queries per row, so a 392-row file occupied a queue worker for roughly a minute. No plan file — the work was scoped directly from that report.

## What was actually costing the time

The reported six reads per row were real but were the smaller half of the bill. Every `Location::create()` also fired `Location`'s own write-side machinery:

- The `saved` hook loaded the owning team, updated it, and mass-updated its xlsforms — three queries per save.
- `$touches = ['parent']` sent `Model::touchOwners()` (`HasRelationships.php:988-1003`) up the ancestor chain: it lazily loaded the parent, updated it, **re-fired `saved` on it**, then recursed to the grandparent. So the three-query hook ran once per ancestor, per created location.

A newly created location three levels deep therefore cost about fifteen queries, nearly all writes, on top of the six reads.

## Changes

### 1. Deleted a query that fed nothing

`collection()` ended with a `Location::query()->...->first()` whose only purpose was to append to a returned `$importedLocations` array. `Maatwebsite\Excel\Sheet::import` calls `$import->collection($rows)` and discards the result, and nothing else read it. The lookup and the array are gone, and the method now returns `void`.

### 2. Suppressed the per-row owner flagging and ancestor touching

`Location::withoutFlaggingOwner()` (new) turns off the `saved`/`deleted` owner hooks for the duration of a callback; the body of both hooks moved into a new `Location::flagOwner(Team $owner)` so there is one definition of what flagging means. `collection()` wraps its loop in it and calls `flagOwner()` once at the end, only when the chunk actually created something.

New locations are saved with `$location->save(['touch' => false])`, which skips `touchOwners()` entirely. To keep the ancestors' `updated_at` honest — `Location::cacheKey()` keys the five-minute `farms_all_count` cache on it — the ids of every ancestor of every created location are collected and bumped in a single `whereIn(...)->update()` at the end of the chunk.

### 3. In-memory index of the owner's existing locations

All of the owner's locations are loaded once per chunk into an array keyed by level and code, so `findOrCreateLocation()` resolves parents and detects existing rows without touching the database. Newly created rows are written back into the index, so a parent repeated across rows is still created once.

Three details worth keeping in mind:

- **The index is built lazily from `collection()`, never from the constructor.** Each chunk is a separate queued `ReadChunk` job with the importer serialized into its payload (`Maatwebsite\Excel\ChunkReader::read`), so building it up front would write the whole hierarchy into every chunk's payload.
- **Codes are normalised on both sides of the comparison.** MySQL's collation on `locations.code` matches case-insensitively and ignores trailing whitespace, and comparing a numeric spreadsheet cell against the varchar column is a loose comparison; a raw array lookup does none of that. Without `locationKey()` normalising (`trim`, `mb_strtolower`, string cast) a row that used to reuse an existing location would silently insert a duplicate, and `locations.code` has had no unique index since `2025_11_24_112030_drop_unique_from_locations_table`. Note that SQLite, which the tests run on, is case-sensitive here and so agrees with the raw lookup rather than with production — hence the explicit test.
- **Oldest wins on duplicate codes.** The index is built `orderBy('id')` and keeps the first entry per key, matching the row the unordered `first()` it replaced returned in practice.

## Result

Query counts measured with `LocationImportCollectionTest`'s fixture (a two-level District/Village hierarchy), running it against the previous implementation for the "before" column:

| Chunk | Before | After |
| --- | --- | --- |
| 5 rows creating 6 locations | 59 | 11 |
| 25 rows creating 26 locations | 279 | 31 |
| 25 rows creating nothing | 100 | 1 |

The fixed cost is one `SELECT` for the index, one `UPDATE` for the ancestor timestamps and three to flag the owner. Everything else is one `INSERT` per location actually created.

## Behaviour changes

- Ancestors are touched once per chunk rather than once per descendant, so their `updated_at` reflects the chunk's finish rather than each individual insert.
- The owner flags (`teams.has_updated_locations`, `xlsforms.draft_needs_update`) are set once per chunk, and not at all when a chunk creates nothing. Previously they were rewritten on every save. Both are idempotent booleans, so the end state is the same.
- Codes that differ only by case or by leading/trailing whitespace now match an existing location on **any** database, not just MySQL. Under SQLite this is new behaviour; under MySQL it matches what the queries already did.
- A code like `007` no longer matches a stored `7`. MySQL's loose numeric comparison did match them; that is almost certainly not what anyone wanted from a location code, but it is a change.

## Not addressed

- Inserts are still one statement per location. Batching them is awkward because each row needs its parent's autoincrement id before the child can be built.
- The concurrency window is wider: a location added by another process mid-chunk is now invisible until the next chunk rather than for a single row. Chunk jobs are chained sequentially so this needs a genuinely concurrent second writer to bite, and with no unique index the failure mode would be a duplicate row rather than an error.
- `Location::hasFarms()` reads `$this->locationLevel?->has_households`, but `LocationLevel` has no such property — `getCsvContentsForOdk()` a few lines below reads `has_farms`. PHPStan has always flagged this; typing the relation generics in this change just made it name the right class. Left alone as out of scope.
