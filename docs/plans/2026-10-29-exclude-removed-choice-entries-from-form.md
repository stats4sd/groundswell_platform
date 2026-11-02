# Plan: Exclude "Removed from context" choice list entries from the generated ODK form

**Status: Completed** — implemented 2026-10-30; see the [change log](../change-logs/2026-10-30-exclude-removed-choice-entries-from-form.md). Manual verification on local/staging is still to be done by Dan.

Fixes [the issue](../issues/2026-10-29-removed-choice-list-entries-still-exported.md). Changes span the `packages/filament-odk-link` submodule (the exports) and this repo (the toggle action). Implementing them means a commit in the submodule plus a submodule pointer bump here. Follow-up to [the duplicate choice list work](2026-10-19-choice-list-duplicate-names.md), which made removal apply to every same-named copy but did not touch the exports.

## Problem

A team can use "Remove from context" on Contextualise choice lists. The removal is stored in `choice_list_entries_removed_owner` (`owner_id`, `choice_list_entry_id`), but none of the form generation reads it, so the removed entry is still written to the `choices` sheet and appears in the ODK form. Toggling a removal also does not mark the team's forms as needing an update, so the "updates available to publish" prompt does not appear.

## Scope of the fix

| Path | File (in `packages/filament-odk-link/src`) | Change |
|---|---|---|
| `choices` sheet | `Exports/XlsformExport/XlsformChoicesExport.php` `query()` | Exclude removed entries |
| CSV attachment | `Exports/ChoiceListAsMediaAttachmentExport.php`, via `Models/OdkLink/ChoiceList.php` `getOwnedEntries()` | Exclude removed entries |
| Attachment | `Exports/ChoiceListModelsExport.php` | Exclude removed entries |
| Toggle action | `app/Services/ChoiceListSiblingService.php` `toggleRemoved()` (this repo) | Set `draft_needs_update` on the owner's xlsforms |

## Design

### Filter

Add a reusable scope on `ChoiceListEntry`, e.g. `scopeNotRemovedFor(Builder $query, WithXlsforms $owner)`, so the three exports share one definition:

```php
$query->whereDoesntHave('ownersWhoRemovedFromContext', fn ($q) => $q->where(
    $owner->getTable() . '.' . $owner->getKeyName(), $owner->getKey()
));
```

(`ownersWhoRemovedFromContext()` already exists on `ChoiceListEntry`.) `ChoiceListModelsExport` filters a loaded collection, so it either uses `isRemoved()` (preloading `choiceListEntriesRemovedFromContext` once to avoid N+1) or `getOwnedEntries()` is changed to use the scope and the export reuses it.

Decide on `getOwnedEntries()`: it is also used elsewhere, so check callers first (`grep getOwnedEntries`). If any caller is a management screen that needs to show removed entries, add the filter as a separate method or a parameter instead of changing the default.

### `XlsformChoicesExport` grouping

The query groups by `(list_name, name, properties, cascade_filter)` and keeps `max(id)` (preferring the row with language strings). Removal is stored per entry id, but the UI treats same-named lists as one list, so **decided (Dan, 2026-10-29): a group is excluded when any same-named entry is removed for the owner**, even if other copies are not removed (e.g. a module version added after the 2026-10-19 sync, listed as "Not covered" in that change log).

A plain `where` on each row is not enough, because it would only drop the removed copy and the group would survive through the others. Instead, exclude by sibling match:

```php
->whereNotExists(function ($query) use ($owner) {
    $query->selectRaw('1')
        ->from('choice_list_entries_removed_owner as removed')
        ->join('choice_list_entries as removed_entry', 'removed_entry.id', '=', 'removed.choice_list_entry_id')
        ->join('choice_lists as removed_list', 'removed_list.id', '=', 'removed_entry.choice_list_id')
        ->where('removed.owner_id', $owner->getKey())
        ->whereColumn('removed_list.list_name', 'choice_lists.list_name')
        ->whereColumn('removed_entry.name', 'choice_list_entries.name');
})
```

This mirrors the sibling match in `SyncRemovedChoiceListEntries` (same `list_name`, same `name`; the command also matches `owner_id`, so check whether to match it here: a global entry and a team-owned entry with the same name should probably both be excluded). Wrap it in the shared scope so the CSV attachment exports use the same rule: `ChoiceListEntry::scopeNotRemovedFor()` implements the sibling-match condition above rather than a per-row `whereDoesntHave`. `ChoiceListModelsExport` (collection based) should use the same query via `getOwnedEntries()` instead of re-implementing it.

Edge case to cover in tests: a name removed in one copy of `crop_list` only still disappears from the export; a same `name` in a different `list_name` is unaffected.

### Respect `can_be_hidden_from_context`

`ChoiceListEntry::canBeHiddenFromContext()` reads `choiceList->can_be_hidden_from_context`, and the UI offers the toggle based on it. Check whether the UI already hides the toggle for lists where it is false (look at the action's `visible()` in `ListChoiceListEntries`). If the toggle is only offered for such lists, no extra check is needed in the export; stale removal rows for a list that later became non-hideable would be ignored by applying the filter only when the flag is true. Decide when implementing; default is to apply the filter regardless, which is simpler and matches what the team saw in the UI.

### Mark forms as needing an update

In `ChoiceListSiblingService::toggleRemoved()` (inside the existing transaction), after the attach/detach:

```php
$team->xlsforms()->update(['draft_needs_update' => true]);
```

This follows the pattern in `FarmEntity::booted()` and the Informed Consent / HDDS hints pages. Consider limiting it to xlsforms that use the affected list (via `xlsform_module_versions` -> `choice_lists`) rather than all of the team's forms; the simple version is fine unless it causes noticeable extra republishing.

### Follow-up (2026-10-30)

Manual testing showed the UI only checked the canonical copy, so a removal row on a non-canonical copy hid the entry from the form but still showed it as available. `ChoiceListEntry::isRemoved()` now uses the same sibling rule as the export.

## Implementation steps

1. Read callers of `getOwnedEntries()` and the "Remove from context" action's visibility rules (`ListChoiceListEntries.php:99`).
2. Add the `notRemovedFor` scope on `ChoiceListEntry` (submodule).
3. Apply it in `XlsformChoicesExport::query()`, `getOwnedEntries()` / `ChoiceListAsMediaAttachmentExport`, and `ChoiceListModelsExport`.
4. Implement the sibling-match exclusion (any same-named entry removed excludes the group) in the scope.
5. Set `draft_needs_update` in `ChoiceListSiblingService::toggleRemoved()`.
6. Tests (below).
7. Run `./vendor/bin/pest`, `./vendor/bin/phpstan analyse`, `./vendor/bin/pint` in the host app; run the package's own tests in the submodule.
8. Commit in the submodule, bump the pointer here, write the change log, update this plan's status.

## Tests

In `packages/filament-odk-link/tests/Unit/Exports/XlsformChoicesExportTest.php` (it already has `makeFormUsing()`, `choicesExportFor()` and `labelEntry()` helpers; note the export constructor needs MySQL-only `json_keys()`, so the helper builds it without the constructor):

- A removed entry is absent from the choices query; a non-removed entry in the same list remains.
- Removal by another team does not affect this team's form.
- A global entry and a team-owned entry are both filtered when removed.
- Same-named list in two module versions: removal in both copies drops the entry; removal in only one copy also drops the entry (decided behaviour).
- A same `name` in a different `list_name` is not affected.

In `tests/Feature/PlaceAdaptations/DuplicateChoiceListsTest.php` (host app):

- Toggling removal sets `draft_needs_update` on the team's xlsforms, and toggling back does too.

Add a test for `getOwnedEntries()` / the attachment exports if they are changed.

## Verification

1. On a local team, remove one `crop_list` entry, publish the Global Indicators form draft, and confirm the entry is gone from the downloaded XLSForm `choices` sheet and from the attached CSV.
2. Restore the entry, re-publish, and confirm it returns.
3. Confirm another team's form is unchanged.
4. Confirm the "updates available to publish" prompt appears after toggling.

## Risks / not covered

- Entries already referenced by existing submissions: removing them only affects new drafts; existing data keeps the old value. No migration of data.
- Forms already published stay unchanged until their next Publish; no backfill command (decided by Dan, 2026-10-29). Teams that removed entries earlier get the fix on their next publish.
- Lists with `has_custom_handling = 1` are not managed through this UI; confirm the export filter does not unexpectedly affect them.
