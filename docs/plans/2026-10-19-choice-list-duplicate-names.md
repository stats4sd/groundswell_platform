# Plan: De-duplicate same-named choice lists on "Contextualise choice lists"

**Status: Completed** — implemented, covered by tests (full suite passes) and manually verified in the browser; see the [change log](../change-logs/2026-10-19-choice-list-duplicate-names.md).

## Problem

On Survey Dashboard > Place-based adaptations > Contextualise choice lists, selecting `crop_list` shows every crop twice (70 rows instead of 35).

### Root cause

`choice_lists` has two rows named `crop_list`:

| id | module | module version |
|----|--------|----------------|
| 27 | `crops` | 14 |
| 52 | `crop_intensification` | 16 |

This is by design in the `filament-odk-link` package: the unique key is `(xlsform_module_version_id, list_name)`, so every module that declares a list gets its own copy on template import. `crop_list` is currently the only localisable list (`is_localisable = 1`, `has_custom_handling = 0`) that exists in more than one module. Each copy holds the same 35 entries (70 rows total).

The Filament page treats `list_name` as unique, so:

- `ListChoiceListEntries::table()` filters by `choiceList.list_name`, matching both copies, so every entry appears twice.
- `ListChoiceListEntries::mount()` and the `choice_list_id` hidden field in `ChoiceListEntryResource::getFormSchema()` use `ChoiceList::where('list_name', ...)->first()`, so only one copy is ever used.
- `ChoiceListEntryResource::getNavigationItems()` builds one item per `ChoiceList` row.

### Functional impact (not just cosmetic)

- "Remove from context" is stored per entry id in `choice_list_entries_removed_owner`, so removing "Rice" hides it from only one module's form. The other module still offers it.
- "Add new" always attaches the new entry to the first copy (id 27); `crop_intensification` never receives it.
- Per-team customisations drift apart between the copies.

## Decisions (agreed with Dan)

- The `crops` and `crop_intensification` modules must always share the same `crop_list` contents for a team. Renaming one list was rejected: `list_name` is the XLSForm identifier (`select_one crop_list` and the `choices` sheet), so renaming in the DB only would be undone by the next template re-import, and renaming at source would split the lists (the opposite of what we want).
- **Approach A**: fix at the UI layer, treating `list_name` as the unit of management and writing through to all same-named copies. No schema or package change.
- Reconciliation rule for existing out-of-sync removals: **if any copy of an entry is removed by a team, treat it as removed for all copies.**
- Out of scope: newly imported module versions that create a *new* same-named copy will not auto-sync with existing copies. This is deferred to a possible Approach B (a genuinely shared choice list, e.g. a pivot between modules and lists), which touches the `filament-odk-link` package (import, form compilation, `xlsformModuleVersion()` relation).

## Design

All changes are in the app layer (`app/`); `packages/filament-odk-link` is not modified.

### 1. Sibling-resolution helper

A small service (e.g. `App\Services\ChoiceListSiblingService`) providing:

- `listIdsFor(string $listName): Collection` - ids of all `ChoiceList` rows with that `list_name` (localisable, non-custom-handling).
- `canonicalListId(string $listName): int` - the lowest id; the copy the table displays.
- `siblingsOf(ChoiceListEntry $entry): Collection` - entries in the other copies with the same `name` and same `owner_id` (global entries have `owner_id = null`).

### 2. Table and navigation

- `ListChoiceListEntries::table()`: replace the `whereHas('choiceList', list_name = ...)` filter with `where('choice_list_id', canonicalListId)`, so each entry appears once.
- `ChoiceListEntryResource::getNavigationItems()`: de-duplicate by `list_name` (e.g. `->unique('list_name')`).
- `mount()` and the `choice_list_id` hidden field: use the canonical list id.

### 3. Write-through to all copies

| Action | Behaviour |
|--------|-----------|
| Toggle Removed | Toggle on the entry and all its siblings; the displayed state comes from the canonical copy (kept in sync). |
| Create (team entry) | Create the entry in every copy of the list, copying `properties` and label `languageStrings` for each locale. Implemented in an `after` hook on `CreateAction`, since the Repeater only saves language strings for the record it is bound to. |
| Edit (team entry) | After save, apply `properties` and label text changes to siblings. |
| Delete (team entry) | Delete siblings as well; the model's `deleting` hook already removes their language strings. |

Uniqueness validation on `name` currently checks `choice_list_id` + `owner_id`; it will check across all copies so a duplicate cannot be created in just one of them.

All multi-copy writes run inside a DB transaction.

### 4. One-time data reconciliation command

An artisan command (e.g. `choice-lists:sync-removed-entries`) that, for each group of same-named entries across copies, applies the "any removed means removed for all" rule to `choice_list_entries_removed_owner`.

- Supports `--dry-run` (default) listing the exact `(team, entry)` rows that would be added; the real run requires an explicit flag.
- Idempotent.
- It writes team data, so the dry-run output will be reviewed with Dan before it is run on any real environment.

### 5. Tests (Pest)

- Two same-named lists show each entry once on the page and one sidebar item.
- Toggle Removed removes/restores all copies.
- Create adds an entry (with labels for every team locale) to every copy; Edit and Delete propagate.
- A duplicate name is rejected across copies.
- Reconciliation command: dry-run changes nothing; real run adds the missing removal rows and is idempotent.

Also run `phpstan`, `pint` and the full suite.

## Risks and open items

- Copies created by future imports will not be synced (explicitly deferred, see above).
- A list that gains a third copy works with the helper (it resolves by `list_name`), but pre-existing entries in the new copy would not be reconciled automatically beyond running the command again.
- The reconciliation command modifies team removal data; review dry-run output first.

## Status log

Plan written and approved 2026-10-19. Implemented and manually verified the same day.
