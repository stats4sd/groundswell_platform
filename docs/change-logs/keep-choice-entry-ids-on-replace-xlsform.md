# Change log: "Replace XLSForm" keeps choice entry ids (and the team translations linked to them)

Implements [docs/plans/2026-11-11-keep-choice-entry-ids-on-replace-xlsform.md](../plans/2026-11-11-keep-choice-entry-ids-on-replace-xlsform.md). Follow-up to [Replace XLSForm keeps team versions](replace-xlsform-keeps-team-versions.md).

## Problem

After Replace XLSForm, the options (choices) of select questions were blank in Enketo for teams that use their own locale (for example "Spanish (Guatemala)"). Teams on a default locale were not affected.

`XlsformTemplateChoicesImport` upserted choice entries on `(name, choice_list_id, cascade_filter)`. `cascade_filter` is nullable and mostly NULL, and a unique index never treats NULL as equal to NULL, so the upsert inserted a new row on every import. The cleanup after the import then deleted the old rows, and the `deleting` hook of `ChoiceListEntry` deleted their language strings. Default locales are re-imported from the file, but the strings of a team locale are not in the file, so they were lost on every Replace. This was true before the earlier Replace XLSForm fix and was not changed by it.

## Changes (in the `packages/filament-odk-link` submodule)

- `XlsformTemplateChoicesImport::collection()` matches the existing global entries (`owner_id` NULL) of the lists in scope by list, name and cascade_filter (NULL equals NULL) before the upsert. One lookup query per chunk.
- Matched rows are updated by id (`properties` and `updated_during_import` only), so ids and the language strings linked to them survive.
- Rows without a match go through the existing upsert unchanged (new entries, duplicated non-NULL keys).
- If the same key exists more than once, the first id wins; the others are not updated and are removed by the existing cleanup.

## Tests

- Package: new `tests/Unit/Imports/XlsformTemplateChoicesImportTest.php` (6 cases): ids kept across two imports; team locale string kept and still linked to the same entry; removed entry deleted with its strings and new entry inserted; entries with a cascade filter (including the same name with different filters) kept; team-owned entry with the same name not matched or changed; `properties` updated from the new file.
- Host: `tests/Feature/ReplaceXlsformKeepsTeamVersionsTest.php` adds a team locale with a label for every global choice entry before the second (v2) import, and checks that the entries keep their ids and the team strings stay linked to them.
- The new tests fail with the fix reverted (6 of 6 in the package, 1 in the host).
- Results: host suite 385 passed. Package suite 219 passed and 24 failed; the 24 failures are the pre-existing `TeamFactory` path-case failures. PHPStan reports no new errors in the changed file.

## Not changed (decisions)

- Translations that earlier Replaces already deleted are not restored and there is no repair command (decision by Dan, 2026-11-11). Teams must export a fresh translation file after this is deployed; from then on their translations survive later Replaces.
- An entry whose name, list or `cascade_filter` changes in the file is a new entry, and the team's strings for the old one are lost.
- Entries missing from the new file are still deleted with their language strings; team-owned entries are never matched; no migration.

## Rollout / verification

Merge the submodule PR into `staging-hotfix-20261026`, then this PR; deploy to staging, restart the queue workers and reload PHP-FPM. Then run the manual verification in the plan (same file Replaced twice: `MIN(id)`, `MAX(id)`, `MIN(created_at)` and the entry count of the global choice entries stay the same; a team translation uploaded after the deployment survives one more Replace).
