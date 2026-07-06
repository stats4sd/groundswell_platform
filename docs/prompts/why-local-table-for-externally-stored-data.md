# Investigation: Why does `farm_entities` exist if farm data lives in ODK Central?

**Question asked:** "As farm data are stored in ODK central entity list, please tell me why we need to have farm_entities table in database?" — followed by "Let's reconsider the architecture" (i.e. could the local table be reduced further or eliminated).

Context: `app/Models/SampleFrame/FarmEntity.php` / `farm_entities` table, part of the ODK-Central-Entities-backed Farm CRUD feature (`docs/plans/odk-entities-farm-crud.md`).

## Part 1 — why the table exists at all

`farm_entities` is not a cache of farm content. The actual identifiers/properties data is never persisted long-term — `OdkFarmEntityService::refreshFromCentral()` and `getEntityData()` always read it live from Central's OData feed; local `EntityValue` rows are an overwritten-every-refresh mirror, not authoritative storage. The table holds only:

1. **A stable local anchor for the rest of the app.** Filament resources are Eloquent-model-based throughout (route model binding, policies, relationships to `Location`/`Team`/future `FarmSurveyData`). ODK Central has no concept of a foreign key into this app's schema.
2. **Fields that need real SQL, not per-row API calls.** `team_code` import dedup and `location_id`-based joins/filtering need indexed `WHERE` queries against potentially thousands of rows.
3. **Mapping to Central's identity/versioning.** `odk_uuid` and `odk_version` bridge "the local row someone clicked Edit on" to "the specific Central entity + version to PATCH" (optimistic concurrency).
4. **Consistency with the rest of the package** — `Xlsform`, `Submission`, `OdkProject` all follow the same local-mirror-plus-remote-system-of-truth pattern; this isn't a Farm-specific exception.

## Part 2 — could it be reduced further or eliminated? (verified against actual Filament 5 vendor source, not just docs)

**Full elimination is not realistic.** Three independent walls:

1. **Filament's Create/Edit/Delete lifecycle is Eloquent-typed at its core**, confirmed directly in `vendor/filament/`:
   - `CreateRecord::handleRecordCreation(array $data): Model` (`CreateRecord.php:213`)
   - `EditRecord::handleRecordUpdate(Model $record, array $data): Model` (`EditRecord.php:281`)
   - `InteractsWithRecord::getRecord(): Model`, `$record` typed `Model|int|string|null` (`InteractsWithRecord.php:16,53`)
   - `resolveRecordRouteBinding(): ?Model` runs a real Eloquent query via `app(static::getModel())->resolveRouteBindingQuery(...)` (`HasRoutes.php:44`)
   - `DeleteAction` calls `$record->delete()` directly (`DeleteAction.php:52`)

   Faking this over a live API would mean either building a fake Eloquent model that secretly does HTTP calls instead of SQL (reimplements a query compiler for OData, breaks anything — including Filament internals — that assumes real query-builder semantics), or abandoning `CreateRecord`/`EditRecord` entirely for hand-rolled Livewire pages re-deriving tenancy scoping, policy gates, notifications, and redirect-after-save from scratch.

2. **A future `FarmSurveyData` database-level foreign key needs a local, stable, indexable row regardless of Filament.** Cascading deletes and orphan-prevention are SQL/InnoDB features that only work between tables in the same database. This requirement doesn't go away even in an otherwise-live-API design — it's a relational-database constraint, not a framework limitation.

3. **Live-API import dedup would be slower and less correct.** Today: one indexed SQL query (`FarmEntity::where('owner_id', $team->id)->pluck('team_code')`) inside a `DB::transaction` that's confirmed to roll back cleanly on API failure (see the Phase 4 "Bug found during Import testing" note in the main plan doc). A live-API equivalent (per-code OData `$filter` calls, or pulling the whole team's feed and diffing in memory) is both much slower for 500–1000-row imports and loses the local transactional rollback guarantee — there's nothing to roll back locally if there are no local rows.

**Partially feasible, but narrow:** Filament 5 does have a genuine `Table::records(Closure)` API (`vendor/filament/tables/src/Concerns/HasRecords.php`) that could back the **List page only** with a live Central pull, with search/sort/pagination handled in PHP over an in-memory array (ODK's own OData filter support for arbitrary per-team dynamic properties is not guaranteed to cover this use case). This doesn't touch Create/Edit/Delete/FK integrity at all.

**Conclusion: shrink, don't eliminate.** The one real reduction available: `latitude`/`longitude`/`altitude`/`accuracy` are the sole columns that store actual farm *content* locally with no Central sync — every other column has a concrete, evidenced reason to stay (listed in Part 1). Decision made: migrate GPS to Central-synced entity properties (tagged via the existing `DatasetVariable.description` mechanism, same pattern as the identifier/property split), closing that inconsistency. See the plan for that work: `docs/plans/farm-entities-simplify-and-gps-sync.md`.

## Delta — from investigation to a batched simplification plan

This investigation was itself an open "why does X exist" question with no assumed answer. The follow-up that turned it into an actionable plan was a different kind of prompt — a specific, already-decided simplification pointed at already-shipped, tested, working code, not a fresh investigation:

> In app/Services/OdkFarmEntityService.php, resolveEntityListName() function can be simpler. Let's plan to hardcode it to return "Farm_Summary" first.

That one, on its own, would have been a narrow one-line change. It became the seed of a combined plan once a second and third simplification were already sitting unapproved in the same conversation — this GPS migration, and a separate decision to stop persisting farm content into the generic `entities`/`entity_values` package tables at all (not just deferring their write until render time, but never writing them). Asked whether to scope the plan narrowly or combine all three, the answer was:

> Include all three.

Reusable shape for next time: once you've shipped something and a later look at the working code turns up more than one spot that's more complicated than current requirements actually need (a dynamic lookup with no observed variation, a persistence layer that turned out unnecessary, a data category that quietly doesn't follow the pattern everything else follows), don't plan each in isolation by default — ask whether to batch them into one reviewed plan once it's clear they touch the same file or call sites. Resulting plan: `docs/plans/farm-entities-simplify-and-gps-sync.md`.
