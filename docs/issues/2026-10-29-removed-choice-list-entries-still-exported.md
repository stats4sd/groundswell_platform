# Choice list entries a team "Removed from context" are still exported to the generated ODK form

**Status**: Open — found on 2026-10-29 by reading the export code; not yet reproduced against a real form.

## What happens

On Survey Dashboard > Place-based adaptations > Contextualise choice lists, a team can use "Remove from context" to hide an entry. The removal is stored in `choice_list_entries_removed_owner` (`owner_id`, `choice_list_entry_id`) and read through `WithXlsforms::choiceListEntriesRemovedFromContext()` / `ChoiceListEntry::isRemoved()`.

Nothing in the form generation reads that table, so the removed entry is still written to the `choices` sheet and shows up as an option in the generated ODK form.

## Evidence (code review, 2026-10-29)

`choice_list_entries_removed_owner` and its relations are referenced only by the UI and the sync command:

- `app/Services/ChoiceListSiblingService.php` (toggle across sibling lists)
- `app/Console/Commands/SyncRemovedChoiceListEntries.php`
- `ChoiceListEntry::isRemoved()` / `toggleRemoved()` (package model)

None of the export paths filter on it:

| Path | Filter applied today | Removed entries excluded? |
|---|---|---|
| `packages/filament-odk-link/src/Exports/XlsformExport/XlsformChoicesExport.php` `query()` (the `choices` sheet) | `owner_id` is the form owner or null; list belongs to a module version of the form | No |
| `ChoiceListAsMediaAttachmentExport` (CSV attachment) via `ChoiceList::getOwnedEntries()` | owner or no owner | No |
| `ChoiceListModelsExport` (`HelperService` attachment) | `owner_id` is the form owner or null | No |

`XlsformChoicesExportTest` has no case for removed entries.

## Relation to earlier work

`fix-issues-20261019` (commit `1a4b480`, [plan](../plans/2026-10-19-choice-list-duplicate-names.md), [change log](../change-logs/2026-10-19-choice-list-duplicate-names.md)) made removal apply to every same-named copy of `crop_list` and added `choice-lists:sync-removed-entries`. It only changed the UI and the removal data; it did not touch the exports. So the removal rows are now consistent across copies, but the form generation still ignores them. This is not a regression from that branch, the filter was probably never implemented in the exports (the relation was added in the polymorphic-owner refactor, `3a1a22e`).

## Things to check before fixing

- `XlsformChoicesExport` groups entries by `(list_name, name, properties, cascade_filter)` and keeps the `max(id)` of the group, preferring one with language strings. A removal is stored per entry id, so the filter must exclude a group when the *chosen* entry or any sibling copy is removed. After the 2026-10-19 sync every copy has a removal row, but `choice-lists:sync-removed-entries` does not cover new module versions (listed under "Not covered" in the change log).
- Only lists with `can_be_hidden_from_context` should be affected; confirm this flag matches what the UI offers.
- Removed entries that are already referenced by existing submissions: dropping them from the form only affects new drafts, existing data keeps the old value.
- Per-team forms are only regenerated on publish; after a fix, a removal made earlier needs a re-publish (consider `draft_needs_update`, as `FarmEntity` does).

## Suggested direction (not decided)

1. Add a `whereDoesntHave('ownersWhoRemovedFromContext', fn ($q) => $q->where(<owner key>, $this->xlsform->owner->getKey()))` condition to `XlsformChoicesExport::query()`, and the equivalent to `getOwnedEntries()` and `ChoiceListModelsExport`.
2. Add tests: a removed entry is absent from the choices sheet, a non-removed one remains, and removal by another team has no effect.
3. Mark the team's xlsforms `draft_needs_update` when an entry is toggled, so the next publish picks it up.

Changes belong in the `packages/filament-odk-link` submodule, so they need a submodule commit and pointer bump in this repo.
