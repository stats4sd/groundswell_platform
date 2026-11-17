# Change log: fix "name already taken" when editing a choice list entry with same-named copies (2026-11-16)

Plan: [2026-11-16-fix-choice-list-entry-unique-rule.md](../plans/2026-11-16-fix-choice-list-entry-unique-rule.md)

## Problem

Editing a team-owned choice list entry (for example `GTQ` in `currency`, team 6 on staging) failed with "The name has already been taken", even when the name was unchanged. It did not reproduce on a database where the list has a single copy.

## Cause

The same `list_name` exists once per module version, and the UI applies every create, edit and delete to all copies. A team-owned entry therefore has sibling rows (same name, same owner) in the other copies. The `name` rule used `unique(ignoreRecord: true)`, which excludes only the edited row, so its own siblings made it fail. On staging, `currency` has lists 7 and 1195, with `GTQ` rows 8240 and 8241.

## Changes

- `app/Filament/App/Clusters/Localisations/Resources/ChoiceListEntryResource.php`: the `name` uniqueness rule also excludes the edited entry's siblings (`ChoiceListSiblingService::siblingsOf`) with `whereNotIn('id', ...)`. Create is unchanged.
- `tests/Feature/PlaceAdaptations/DuplicateChoiceListsTest.php`: two new tests. Editing with an unchanged name and a new label succeeds and reaches every copy (fails without the fix). Renaming to a name used by a different entry in another copy is still rejected.

## Verified

- Local: full suite, 391 passed.
- Staging (2026-11-16): editing only the labels of `GTQ` saves and both copies are updated; renaming a new test entry to `GTQ` is rejected; renaming it to an unused name succeeds in both copies; the test entry was deleted afterwards.
