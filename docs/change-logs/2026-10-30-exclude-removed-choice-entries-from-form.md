# Change log: Exclude "Removed from context" choice list entries from the generated ODK form

Implements [the plan](../plans/2026-10-29-exclude-removed-choice-entries-from-form.md) for [the issue](../issues/2026-10-29-removed-choice-list-entries-still-exported.md). Entries a team removed with "Remove from context" were still written to the `choices` sheet and the attachments of the generated form.

## Changes

`packages/filament-odk-link` (submodule):

- `ChoiceListEntry::scopeNotRemovedFor($owner)` (new): excludes an entry when the owner removed any entry with the same `list_name` and `name` (sibling match, so a removal in one copy of a same-named list hides the entry in all copies, and a team-owned entry with the same name as a removed global entry is hidden too).
- `ChoiceListEntry::isRemoved()`: now uses the same sibling rule (any entry with the same `list_name` and `name` removed by the team), so the Contextualise choice lists page shows the same state as the generated form even when only a non-canonical copy has a removal row (e.g. manual DB edits, or a copy added later). Found during manual testing on 2026-10-30.
- `XlsformChoicesExport::query()`: applies the scope (the `choices` sheet).
- `ChoiceList::getOwnedEntries()`: applies the scope (used by `ChoiceListAsMediaAttachmentExport`, the CSV attachment).
- `ChoiceListModelsExport`: reads entries through the scope instead of the unfiltered relation.
- `tests/Unit/Exports/XlsformChoicesExportTest.php`: 6 new tests (removed entry excluded, other team's removal ignored, team-owned same-name entry excluded, removal in one copy excludes all copies, same name in another list unaffected, `getOwnedEntries()` filtered).

Host app:

- `ChoiceListSiblingService::toggleRemoved()`: sets `draft_needs_update` on the team's xlsforms, on both remove and restore, so the "updates available to publish" prompt appears.
- `tests/Feature/PlaceAdaptations/DuplicateChoiceListsTest.php`: new tests for the flag, and for an entry removed in only one copy (shows as removed for every copy; Restore clears all rows).

## Tests

- Host app `./vendor/bin/pest`: 337 passed. Pint and PHPStan clean for the changed host files.
- Package `./vendor/bin/pest`: all `XlsformChoicesExportTest` tests pass. 24 tests fail in unrelated files (`OdkUserServiceTest`, `OdkProjectServiceTest`, `EntityDatasetRelationsTest`, `HasXlsformsTest`, `XlsformSyncWithTemplateTest`, `Smoke\ModelsTest`); not compared against a baseline run.

## Not changed

- No backfill: forms already published pick up the fix on the team's next Publish (decided).
- Existing submissions keep the old value; only new drafts lose the removed entries.
