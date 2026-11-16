# Change log: fix missing choice labels on new teams, and deploy job noise (2026-11-12)

Plan: [2026-11-12-fix-missing-choice-labels-and-deploy-job-noise.md](../plans/2026-11-12-fix-missing-choice-labels-and-deploy-job-noise.md)

All code changes are in the `packages/filament-odk-link` submodule (branch `hotfix-20261112`); the submodule pointer needs bumping here after it is committed and merged.

## Problem (reported by Alex, new test team 6 on staging)

Deploying Global Indicators failed repeatedly with `<label> node for itemset doesn't exist! [instance(who_family)/root/item/label]`. The generated xlsx had 479 choices rows, and exactly the 7 `who_family` rows had no label in English or Spanish.

Cause: `who_family` exists in 8 lists, and form 29 selected module versions 12, 13, 16 and 20 together. The 10/27 change to `XlsformChoicesExport` picked, per group of same-named entries, the newest one that had *any* `language_strings` row. Entries 167-169 (lists 21, 85, 140) only hold blank placeholder rows for locales 6 and 8, so one of them was picked over the fully labelled entry 166, and the exported labels for locales 1 and 8 were empty.

## Changes

- `XlsformChoicesExport`: the representative of a group must have a non-empty `label` string; each locale's label is filled from any same-named entry in the group that has text for that locale.
- `DeployDraftXlsformToOdkCentral`: fails once, without a notification, when the form's owner no longer exists (team deleted while jobs were queued). When ODK reports a missing-label error, the message now names the choice list, the entries and the languages (`ChoiceLabelDiagnostics`, reads the attached xlsx).
- `UpdateXlsformFile`: when the temp file is gone because an identical job just attached it, it stops the chain quietly instead of notifying, and leaves `processing` alone.

## Tests

Package: `XlsformChoicesExportTest` (4 new), `ChoiceLabelDiagnosticsTest` (5 new). Host app: `tests/Feature/Jobs/DeployJobGuardsTest.php` (4 new).

## Not done

- `AddMissingChoiceListStrings` treats blank placeholder rows as labelled, so entries 167-169 never got locale 1-4 labels. The export no longer depends on it, but the data stays incomplete.
- Duplicate `generateXlsfile` dispatches are not prevented; an atomic `processing` lock risks leaving forms stuck if a job dies.
- Failed `UpdateXlsformFile` duplicates still write a `failed_jobs` row (only the user notification is suppressed).

## Also changed

- Submodule tests: the `TeamFactory` namespace in `tests/database/Factories/TeamFactory.php` and `tests/Models/Team.php` now matches the lowercase `tests/database` directory. The mismatch broke 24 tests on case-sensitive filesystems (Linux).

## Verified on staging (2026-11-16)

- Local: submodule 252 tests and main repo 389 tests pass.
- T1: team 6 Global Indicators deploys; the downloaded draft's `choices` sheet has all 7 `who_family` rows labelled in English and Spanish (479 data rows, none blank in both). No new `failed_jobs` rows.
- T2: dispatching a deploy for form 25 (soft-deleted team 5) adds one `failed_jobs` row (`XlsformOwnerMissingException`), logs `Skipped deploying a form whose owner no longer exists`, sends no notification and does not retry.
- T4: team 3 Initial Pilot generated 6 drafts with no new `failed_jobs`; its Global Indicators `choices` sheet has no row blank in both languages.
- T3 (concurrent duplicate dispatch) was not exercised on staging; covered by unit tests.
