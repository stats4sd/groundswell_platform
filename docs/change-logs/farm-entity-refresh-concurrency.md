# Change log: Concurrency-safe `refreshFromCentral()` farm adoption

No plan file - direct fix off an error log investigation.

## Problem

Loading the farms list (`ListFarmEntities::mount()` → `OdkFarmEntityService::refreshFromCentral()`) for a team whose ODK Central entity list held ~360 farms uploaded outside the platform threw:

```
SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
'09c0447f-cf96-4b44-a12a-c0dc328c3fd6' for key 'farm_entities.farm_entities_odk_uuid_unique'
```

Adoption itself was working - all 366 Central farms were created, with no `team_code` overlap against the 392 pre-existing rows. The error came from a **second, concurrent** refresh for the same team:

- The uuid in the error exists as row 401, `created_at` equal to the second the exception was thrown.
- Inserts carried on to row 759, 12 seconds *after* the exception - a single run would have aborted there, so two runs were in flight.
- The first-time adoption took ~14s, which is long enough for a second page load (a reload, a second tab) to overlap it. The second run snapshotted `farm_entities` part-way through the first run's inserts, then walked the same feed and hit the first uuid the other run had inserted after that snapshot.

Three things made that fatal rather than harmless:

1. `$farmsByUuid` was read once before the loop and never re-checked, and the adopt branch called a bare `FarmEntity::create()` - nothing keyed the insert on the unique column.
2. That lookup was scoped `where('owner_id', $team->id)`, but `farm_entities.odk_uuid` is `unique()` **globally** - a uuid held by another team's row could never be found and would always collide.
3. The loop ran without a transaction, so the failure left the team's farm list half-adopted with no way to tell which rows had made it.

## Changes

All in `App\Services\OdkFarmEntityService`:

- **`refreshFromCentral()`** - now fetches the feed, reduces it to the live data map, then takes a per-team `Cache::lock("farm-entities-refresh:{$team->id}", 120)` around the writes only. The lock is **non-blocking**: a run that loses returns the live Central read it has already paid for and skips the sync (its rows appear on the next load) rather than tying up a worker. The lock is released in a `finally`, so a failing sync doesn't leave the team locked out for the full 120s TTL.
- **Whole sync wrapped in `DB::transaction()`** - a failure part-way through now rolls back rather than leaving a partially-adopted list.
- **New `extractLiveData()`** - splits the pure feed → live data map reduction out of the sync. Keying on uuid also collapses a uuid repeated within one feed, so the sync can't see the same farm twice in a pass.
- **New `syncLocalFarms()` / `syncLocalFarm()`** - the adopt/restore/backfill logic, now keyed on `FarmEntity::withTrashed()->firstOrNew(['odk_uuid' => $uuid])`: matched on the unique column alone, so a uuid inserted by a concurrent run is found instead of re-created, and deliberately not scoped by `owner_id` for the reason in problem (2) above. A row found under a different `owner_id` is left untouched (shouldn't happen - Central entity uuids are unique across projects - and its `team_code`/location belong to that team's feed).
- **`ensurePropertyRegistered()` called once per distinct property name** instead of once per farm per property. Every farm in a feed carries the same handful of names, so a 366-farm feed with ~10 properties was issuing ~3,660 `firstOrCreate` queries where ~10 suffice. This also keeps the new transaction short.

## Tests

New `tests/Feature/Services/OdkFarmEntityRefreshTest.php` (9 tests): adoption of new entities, idempotency across two refreshes, a uuid repeated within one feed, a uuid inserted by a concurrent run mid-pass, restore of a locally soft-deleted farm, a uuid held by another team, the lock skipping the sync while held, lock release after a successful sync, and rollback (plus lock release) when one farm fails part-way through. The rollback test was verified to fail with the `DB::transaction()` wrapper removed.

## Not addressed

`FarmEntity`'s `saved`/`deleted` hooks update `teams.has_updated_locations` and every one of the team's `xlsforms` **per row**, so a 366-farm adoption fires ~366 × 3 extra writes and now holds row locks on those tables for the duration of the transaction. Correct, but it's the main reason the first adoption takes ~14s.
