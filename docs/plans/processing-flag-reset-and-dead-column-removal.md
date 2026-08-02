# Plan: Reset `processing` flags on job failure, and remove dead error columns

**Status:** Not Started

Two related cleanups ahead of the September ODK Link rewrite. Part A makes every failure point in the template-import and draft-deployment/publishing chains reset the `processing` flag, so a single failed job can no longer permanently brick a template or form. Part B removes the five error/status columns that were designed for ODK error persistence and have never been written or read.

`packages/filament-odk-link` is a **git submodule** — every package change here needs a commit in the submodule repo plus a pointer bump in the app repo.

---

# Part A — `processing` flag resilience

## Current lifecycle (audited 2026-08-02)

Only four writes to `processing` exist in the entire codebase:

| Value | Where | Model |
| --- | --- | --- |
| `true` | `packages/filament-odk-link/src/Listeners/HandleXlsformTemplateAdded.php:46` | XlsformTemplate / XlsformModuleVersion |
| `false` | `packages/filament-odk-link/src/Jobs/FinishXlsformTemplateImport.php:30` | XlsformTemplate / XlsformModuleVersion |
| `true` | `packages/filament-odk-link/src/Models/OdkLink/Xlsform.php:400` (`generateXlsfile()`) | Xlsform |
| `false` | `packages/filament-odk-link/src/Jobs/XlsformDeployment/UpdateXlsformFile.php:33` (in `finally`) | Xlsform |

No chain uses `->catch()`, and no job in either chain resets the flag on failure.

**Stuck-template path:** the upload chain is `XlsformTemplateWorkbookImport → PrepareSurveyRowPaths → FinishSurveyRowImport → FinishChoiceListEntryImport → LinkModuleVersionToLocales → ImportAllLanguageStrings → FinishXlsformTemplateImport` (`HandleXlsformTemplateAdded.php:79-89`). Only the final job clears the flag. Any earlier failure leaves `processing = true` forever, which disables the availability checkbox, the "Replace XLSForm" action and the Edit action (`XlsformTemplateTable.php:35,48,90`) and pins the "currently being processed" callout (`XlsformTemplateInfoList.php:36`). There is no sweeper or reset command.

**Stuck-xlsform path:** `deployDraft()` (`Xlsform.php:417-434`) chains `Excel::queue(XlsformWorkbookExport)` → `UpdateXlsformFile` → `DeployDraftXlsformToOdkCentral`, setting `processing = true` before the export. `UpdateXlsformFile`'s `finally` clears it — but if the **export job itself** fails, `UpdateXlsformFile` never runs and the flag sticks. A stuck flag makes `deployDraft()` and `publishForm()` return `null` silently (`Xlsform.php:420,443`) and pins the status attribute at `PROCESSING` (`Xlsform.php:134`). Commit e1529b5 ("better ODK draft deployment exception tracing") added retries and logging to `DeployDraftXlsformToOdkCentral` but did not touch the flag.

**Adjacent fatal:** `publishForm()` at `Xlsform.php:448` calls `->chain()` on `deployDraft()`'s return value, which can be `null`.

## Changes

The `failed()` methods below are the **same** methods [job-failure-db-notifications.md](job-failure-db-notifications.md) adds for notification purposes — implement the two plans together for these jobs: one `failed()` per job that resets the flag *and* notifies. Neither plan adds a second hook.

### A1. Template import chain (submodule)

Add `failed(?Throwable $e)` to each of `PrepareSurveyRowPaths`, `FinishSurveyRowImport`, `FinishChoiceListEntryImport`, `LinkModuleVersionToLocales`, `ImportAllLanguageStrings`, `FinishXlsformTemplateImport`: `$this->model->update(['processing' => false]);` then notify. Add the equivalent failure hook to `XlsformTemplateWorkbookImport` (queued Maatwebsite import at the chain head — `ImportFailed` event handler or `failed()` method).

Rather than six copies, a small shared trait in the package (`ResetsProcessingOnFailure`, reading a `$model` property) keeps them identical; each job already holds the model as a public property.

### A2. Deployment / publishing chain (submodule)

- `XlsformWorkbookExport` — add `failed(Throwable $e)` (Maatwebsite calls it for queued exports): `$this->xlsform->update(['processing' => false]);` + notify. **This is the actual stuck-flag hole.** The per-sheet exports are sheets of this workbook and are covered by the parent.
- `UpdateXlsformFile` — change the swallow-and-continue catch (`:28-31`) to `$this->fail($e)`; the `finally` still clears the flag, and the chain stops instead of deploying a form with no file. Add `failed()` for the notification.
- `DeployDraftXlsformToOdkCentral::failed()` (`:99`) and `PublishXlsformOnOdkCentral::failed()` (`:47`) — add `$this->xlsform->update(['processing' => false]);` defensively. On the normal path the flag is already false by the time these run, but the line is one statement and covers any future reordering plus the `deployDraft(published: true)` path.
- `Xlsform::publishForm()` (`:441-455`) — guard the `null` return from `deployDraft()` at `:448`: log + notify and return `null` instead of fataling on `->chain()`.

### A3. Recovering already-stuck rows

Existing environments hold rows stuck at `processing = true` from past failures. After deploying, reset them manually once (with no jobs in flight):

```sql
UPDATE xlsforms SET processing = 0 WHERE processing = 1;
UPDATE xlsform_templates SET processing = 0 WHERE processing = 1;
UPDATE xlsform_module_versions SET processing = 0 WHERE processing = 1;
```

Not worth an artisan command for a one-off; the fixes above prevent recurrence.

## Known limitations, deliberately deferred to the September rewrite

- `UpdateXlsformFile` clears the flag *before* `DeployDraftXlsformToOdkCentral` runs, so the duplicate-deployment guard does not protect the ODK-upload phase. Moving the clear to the end of the chain changes the chain's semantics — rewrite territory.
- `Xlsform::status` has no failure state; a failed deploy still reads as `NOT DEPLOYED`.
- `XlsformTemplate::deployDraft()` via `HasXlsformDrafts.php:49-57` sets no flag at all.
- `locales.processing_count` has its own stuck/underflow risks (non-atomic `++`/`--` in `TeamTranslationReviewEditForm.php:145` and the two Notify jobs; unsigned column can throw on double-decrement; a non-`ImportFailed` job death never decrements). Worth capturing as an issue in docs/issues; out of scope here.

---

# Part B — Remove the dead error columns

## Verification result (exhaustive grep, 2026-08-02): all five are dead

Nothing reads or writes any of them outside migration definitions and model `$casts`:

| Column | Package migration | App migration (the one that actually runs) | Other references |
| --- | --- | --- | --- |
| `xlsform_templates.odk_error` | `packages/filament-odk-link/database/migrations/001_create_xlsform_templates_table.php:29` | `database/migrations/03_xlsform_management/2024_03_10_03_101233_2_create_xlsform_templates_table.php:28` | none |
| `xlsforms.odk_error` | `.../002_create_xlsforms_table.php:40` | `.../2024_03_10_03_101234_3_create_xlsforms_table.php:43` | none |
| `submissions.errors` | `.../010_create_submissions_table.php:23` | `.../2024_03_10_03_101236_5_create_submissions_table.php:23` | cast at `packages/filament-odk-link/src/Models/OdkLink/Submission.php:39` |
| `submissions.processed` | `.../010_create_submissions_table.php:24` | `.../2024_03_10_03_101236_5_create_submissions_table.php:24` | none — cleanest of the five |
| `submissions.entries` | `.../010_create_submissions_table.php:27` | `.../2024_03_10_03_101236_5_create_submissions_table.php:27` | cast at `Submission.php:40`; dead helper `Submission::addEntry()` at `:120-132` (never called anywhere) + its comment at `:118` |

`submissions.entries` was not in the original task list but is exactly the same case (declared, cast, never used — its only companion is an uncalled helper method), so it is included; strike it from B2 if there is a reason to keep it. No factories or seeders reference any of the five. `DeployDraftXlsformToOdkCentral`'s new failure handling (e1529b5) pointedly logs/notifies rather than writing `odk_error`, so the columns are definitively superseded.

**Migration mechanics:** the app migrations under `database/migrations/03_xlsform_management/` are byte-identical published copies of the package migrations, registered via the directory glob in `app/Providers/AppServiceProvider.php:78-81`. The package service provider never calls `runsMigrations()`, so **only the app copies execute**; the package copies exist for `--publish-migrations` on fresh installs. Both copies must therefore be edited in lockstep or they desync.

## Changes

1. **New app migration** `database/migrations/2026_XX_XX_XXXXXX_drop_unused_error_columns.php` (no `down()` per convention): drop `odk_error` from `xlsform_templates` and `xlsforms`; drop `errors`, `processed`, `entries` from `submissions`. Guard each with `Schema::hasColumn()` so the migration no-ops on fresh databases built from the edited create-table migrations.
2. **Edit the package create-table migrations** (submodule commit): remove the five column definitions from `001_create_xlsform_templates_table.php`, `002_create_xlsforms_table.php`, `010_create_submissions_table.php`.
3. **Edit the app copies identically** so the published pairs stay byte-identical.
4. **Model cleanup** (submodule): remove the `errors` and `entries` casts from `Submission::$casts` (`Submission.php:39-40`), delete `Submission::addEntry()` (`:118-132`).
5. Submodule pointer bump in the app repo.

## Tests / verification

1. `./vendor/bin/pest` — the test database is rebuilt from migrations per run, so a green suite proves the edited create-table migrations and the guarded drop migration coexist. Add one test constructing a `Submission` and asserting the removed attributes are simply absent is unnecessary — nothing referenced them; the suite passing is the assertion.
2. Part A tests (in `tests/Feature/Jobs/ProcessingFlagResetTest.php` or alongside the notification tests from the companion plan): for each chain job, set the model's `processing = true`, call `failed(new Exception('boom'))`, assert `processing` is false. For `publishForm()`: force `processing = true` and assert the call returns `null` without throwing.
3. `./vendor/bin/phpstan analyse` and `./vendor/bin/pint` in both the app and the submodule.
4. Manual: upload a template workbook with a deliberately broken sheet — confirm the template returns to `processing = false`, the table actions re-enable, and (with the companion plan landed) a notification reports the failure. Deploy a draft with the export forced to fail — confirm the xlsform does not stick at `PROCESSING`.
5. Fresh-install check: `php artisan migrate:fresh` on a scratch database — no error from the drop migration (hasColumn guards), no `odk_error`/`errors`/`processed`/`entries` columns present.
