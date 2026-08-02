# Job failure notifications, `processing` flag resilience, and dead error-column removal

**Date:** 2026-08-02
**Branch:** `database-notifications` (app + `packages/filament-odk-link` submodule, in lock-step)
**Plans:** [job-failure-db-notifications.md](../plans/job-failure-db-notifications.md) and [processing-flag-reset-and-dead-column-removal.md](../plans/processing-flag-reset-and-dead-column-removal.md), implemented together as the plans specify (one `failed()` per job doing both flag reset and notification).

## Package changes (`stats4sd/filament-odk-link` submodule)

- New `Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure` trait: `notifyJobFailure(title, ?Throwable, recipients, ?actionUrl)` builds a danger/persistent Filament notification (body truncated to 200 chars, null-safe on the exception) and sends it `sendToDatabase(..., isEventDispatched: true)` + `broadcast()` per recipient. Includes a `superAdmins()` resolver (returns empty collection rather than throwing if the role is missing).
- New `Stats4sd\FilamentOdkLink\Concerns\ResetsProcessingOnFailure` trait: a `failed()` that clears `processing` on the job's `$model` and notifies Super Admins. Applied to all six template-import chain jobs (`PrepareSurveyRowPaths`, `FinishSurveyRowImport`, `FinishChoiceListEntryImport`, `LinkModuleVersionToLocales`, `ImportAllLanguageStrings`, `FinishXlsformTemplateImport`), to `XlsformTemplateWorkbookImport` (Maatwebsite calls the `failed()` method directly for queued imports implementing `WithEvents` — no separate `importFailed` hook needed, that would double-notify), and to the sync-dispatched `FinishLanguageStringImport` / `AddMissingChoiceListStrings` in case they are ever queued.
- `XlsformTemplateChoiceListImport` (fire-and-forget chain head) now implements `WithEvents` and has a notify-only `failed()` (no flag involvement — the workbook chain owns the flag).
- `XlsformWorkbookExport` — **the actual stuck-flag hole**: now takes an optional `$user` and has a `failed()` that resets the xlsform's `processing` flag and notifies (user, falling back to Super Admins).
- `UpdateXlsformFile` — the `FileDoesNotExist`/`FileIsTooBig` catches now call `$this->fail($exception)` instead of swallowing, so the chain stops rather than deploying a form with no file; takes an optional `$user`; added `failed()` (reset + notify).
- `DeployDraftXlsformToOdkCentral::failed()` — now also resets `processing` defensively, stores the notification in the database, and notifies Super Admins when `$user` is null instead of returning early.
- `PublishXlsformOnOdkCentral` — same treatment; fixed the null-unsafe `$exception->getmessage()`; added `$tries = 3` / `backoff [15, 60]` matching the deploy job.
- `Xlsform::generateXlsfile()` now threads `auth()->user()` into the export and `UpdateXlsformFile`. `Xlsform::publishForm()` guards the previously-fatal null return from `deployDraft()` (logs, notifies the user, returns null).
- `PullSubmissionsFromXlsform`, `PullSubmissionsFromXlsformQuietly`, `ProcessOdkSubmission` — `failed()` → Super Admins with form/owner/submission context. The `abort(500, …)` in the quiet pull is now a thrown `Exception`.
- `HandleXlsformTemplateAdded` — deleted the never-set `$importedBy` property (also fixes a phpstan unknown-class error).
- Dead column removal: `odk_error` dropped from the `001`/`002` create-table migrations; `errors`, `processed`, `entries` dropped from `010_create_submissions_table`; `Submission` model lost the `errors`/`entries` casts and the never-called `addEntry()` helper.

## App changes

- `AppPanelProvider` and `AdminPanelProvider` now call `->databaseNotifications()->databaseNotificationsPolling('30s')` — the notification bell renders, including the previously-orphaned database notifications from `LocationImport`/`FarmEntityImport`. Program panel skipped per plan.
- `NotifyUserThatLanguageImportIsComplete` — added `failed()` → user; guarded the fatal null when the team has no xlsform matching the template.
- `NotifyUserThatLanguageImportIsFailed` — added `failed()` → user; its success-path notification (itself a failure report) is now stored via `sendToDatabase` instead of broadcast-only (`->send()` dropped — it only writes to the session, which is meaningless in a queued job).
- **Deviation from plan:** `RegisterNewUserToOdkCentral` was NOT made a queued listener. The `RegisteredWithData` event carries the user's plaintext password (`$data['original_password']`), and queueing the listener would serialise that password into the queue store and the `failed_jobs` table. It stays synchronous; the silent swallow is replaced by log + durable Super Admin notification, which achieves the plan's actual goal (failures surface) without the security regression. The stray `ShouldQueue` import is gone.
- App copies of the three create-table migrations edited to match the package copies' column removals. Note: the plan claimed the pairs were byte-identical; they are not (the app copies have pre-existing drift — HOLPA custom columns on `submissions`, `odk_project_id` on `xlsforms`, a different config key). The drift predates this work and only the five dead columns were touched.
- New guarded migration `2026_08_02_000001_drop_unused_error_columns.php` (up-only, `Schema::hasColumn` guards so it no-ops on fresh databases).
- New tests: `tests/Feature/Jobs/ProcessingFlagResetTest.php` (every chain job resets the flag on `failed()`; `publishForm()` returns null while processing) and `tests/Feature/Jobs/JobFailureNotificationTest.php` (trait behaviour incl. null exception, truncation, collections, action URL; per-chain recipient assertions against the `notifications` table; `UpdateXlsformFile` with a missing file now fails and notifies instead of continuing).
- Spun-out issue: [docs/issues/2026-08-02-locale-processing-count-stuck-risk.md](../issues/2026-08-02-locale-processing-count-stuck-risk.md).

## Verification

- App: 201 tests pass (16 new); phpstan error set identical to baseline (170 pre-existing); pint clean on changed files.
- Package: 179 tests pass; phpstan: 0 new errors, 1 fixed; pint clean on changed files.
- `php artisan migrate --pretend` confirms the drop migration checks columns before dropping.

## Deployment checklist

1. Confirm `BROADCAST_CONNECTION=reverb` in every deployed `.env` — with it unset, `config/broadcasting.php` defaults to the `null` driver and every broadcast notification is silently dropped (database copies still stored).
2. Run migrations (drops the five dead columns).
3. One-off recovery of already-stuck rows (with no jobs in flight):
   ```sql
   UPDATE xlsforms SET processing = 0 WHERE processing = 1;
   UPDATE xlsform_templates SET processing = 0 WHERE processing = 1;
   UPDATE xlsform_module_versions SET processing = 0 WHERE processing = 1;
   ```
4. Manual smoke: upload a template workbook with a broken sheet (template returns to `processing = false`, actions re-enable, bell shows the failure); deploy a draft with the export forced to fail (form does not stick at `PROCESSING`).
