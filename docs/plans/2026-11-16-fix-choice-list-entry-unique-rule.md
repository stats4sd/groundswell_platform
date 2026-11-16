# Plan: fix the choice list entry "name already taken" error when editing an entry

**Status: In Progress.** Implemented and tested locally (regression tests added, full suite passes); waiting for staging verification and the change log.

Branch: `fix-choice-list-unique-rule`. Main repo only; no submodule change.

## Problem

Alex could not save an edit to a team-owned choice list entry (for example the `GTQ` currency entry) even when the name was not changed, for instance when only a label was edited. The form showed that the name is already taken.

## Cause

The same `list_name` exists once per module version, and teams manage the copies as one list. Every create, edit and delete made through the UI is applied to all same-named copies (`ChoiceListSiblingService`), so a team-owned entry always has sibling rows with the same name and owner in the other copies.

The `name` field in `ChoiceListEntryResource::getFormSchema()` uses `unique(ignoreRecord: true, ...)` scoped to all same-named lists and the current owner. `ignoreRecord` excludes only the row being edited, so its own siblings still match and the rule fails. The edit test in `DuplicateChoiceListsTest` only renames an entry, which hides this; the failure was reproduced locally with an unchanged name.

## Fix

In the `name` rule, exclude the edited record **and its siblings** from the uniqueness check:

- Resolve the sibling ids with `ChoiceListSiblingService::siblingsOf($record)` (same name, same owner, same-named lists). This is the same set the edit action already syncs afterwards.
- Add them to the rule with `whereNotIn('id', ...)` inside `modifyRuleUsing`. `ignoreRecord` stays.
- On create there is no record, so nothing is excluded and the rule behaves as today.

Behaviour after the fix:

| Action | Result |
|---|---|
| Edit labels, name unchanged | saves |
| Rename to a name no other entry of the team uses in any copy | saves; siblings follow (existing behaviour) |
| Rename to a name another, different entry already uses in any copy | rejected with the unique error |
| Create with a name already used in any copy | rejected with the unique error |

Not changed: global entries (no owner) are still not editable, and the rule still ignores other teams' entries.

## Tests

Add to `tests/Feature/PlaceAdaptations/DuplicateChoiceListsTest.php`:

1. Editing a team entry that has siblings, with the name unchanged and a new label, succeeds and the new label reaches every copy. This fails before the fix.
2. Renaming an entry to a name that a different team entry already uses (in a sibling copy only) is rejected with the `unique` error.
3. Renaming to a new, unused name still succeeds in every copy (the existing edit test keeps covering this).
4. Create with a name already used in another copy is still rejected (the existing test keeps covering this).

Run `./vendor/bin/pest` and `./vendor/bin/pint` on the changed files.

## Verification on staging

Open Contextualise choice lists for a list the team has already customised (for example the currency list with `GTQ`), edit only a label of a team-owned entry, and confirm it saves and the label shows in the other copies. Then try renaming an entry to the name of another team entry and confirm it is still rejected.

## Out of scope

- Data repair: no existing rows are wrong, the error only blocks the save.
- Names containing spaces (the export converts spaces to underscores); not part of this issue.
