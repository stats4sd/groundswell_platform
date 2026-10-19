# Change log: De-duplicate same-named choice lists

Implements [the plan](../plans/2026-10-19-choice-list-duplicate-names.md). `crop_list` exists once per module (`crops`, `crop_intensification`), so the Contextualise choice lists page showed every crop twice and remove/add only affected one copy.

## Changes

- `app/Services/ChoiceListSiblingService.php` (new): resolves all same-named lists and the matching entries in other copies; toggles removal, replicates, syncs and deletes across copies in transactions.
- `ListChoiceListEntries`: table shows only the canonical (lowest id) list; Create/Edit/Delete/Toggle Removed write through to all copies.
- `ChoiceListEntryResource`: sidebar de-duplicated by `list_name`; the `name` unique rule checks all same-named lists; `choice_list_id` uses the page's canonical list.
- `choice-lists:sync-removed-entries` (new command): if a team removed an entry in any copy, adds the removal to all copies. Dry-run by default; `--apply` writes. Idempotent.
- `tests/Feature/PlaceAdaptations/DuplicateChoiceListsTest.php` (new, 8 tests).

## Manual verification (2026-10-19)

- `crop_list` lists 35 rows. Adding a row creates 2 `choice_list_entries` records (one per copy); deleting it removes both.
- `choice-lists:sync-removed-entries` found one out-of-sync row (team 5, `crop_list` / `1`, entry 495) and `--apply` added the missing removal for entry 496 in `choice_list_entries_removed_owner`.

## Not covered

New module versions that introduce another same-named copy are not auto-synced; deferred to a shared-choice-list design (plan Approach B).
