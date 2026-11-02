# Plan: Translation import memory exhaustion, stuck `processing_count`, and duplicate XLSForm generation (staging issues reported 2026-10-28)

**Status: Not Started**

Changes span this repo (translation upload/import) and the `packages/filament-odk-link` submodule (XLSForm file generation). Implementing them means a commit in the submodule plus a submodule pointer bump here. Staging needs both deployed, then `php artisan queue:restart` (staging runs `queue:work database --daemon` and keeps old code until restarted).

## Background

Alex reported on staging (team 3, "Guatemala - ARGIDIVI") that the Spanish translations were not appearing in the Global Indicators form, and that its translation file seemed stuck on "Processing". The Women's form translations worked. He also reported two template-level issues (see "Out of scope: template changes").

### What the staging investigation established

| # | Finding | Evidence |
|---|---|---|
| 1 | Uploading the Global translation file crashed the web request with `Allowed memory size of 134217728 bytes exhausted (tried to allocate 33558528 bytes)` at `Queue.php:135` (payload serialisation). | `laravel.log` 2026-10-28 15:07:45 |
| 2 | Cause: `TranslationUploadInspector::unknownRows()` reads `$template->surveyRows` / `->choiceListEntries`, which caches every row of the template on the model. The same model instance is then passed to `XlsformTemplateLanguageImport`, which does not use `SerializesModels`, so Laravel serialises the whole model plus the cached relations. The Global template is the largest, so only it exceeded the limit. | `app/Imports/TranslationUploadInspector.php`, `app/Imports/XlsformTemplateLanguageImport.php`, `TeamTranslationReviewEditForm::submit()` |
| 3 | `processing_count` is incremented and saved *before* the import is dispatched, so a crash during dispatch leaves the locale stuck. Locales 5 and 6 sat at 3 and 1. | `TeamTranslationReviewEditForm.php:146` |
| 4 | A second upload (after the web `memory_limit` was raised) wrote all strings (Global: 798 survey + 764 choice) but the count stayed at 1: no `jobs` rows, no `failed_jobs` rows, no completion notification. The reason the final notification never ran is **not identified**. | staging DB, 2026-10-29 |
| 5 | `UpdateXlsformFile` for the Women's form (id 9) failed with `File temp/9/Women's Form - Test Form.xlsx does not exist` (`failed_jobs` id 14), while the same form's draft deployed successfully seconds later. Cause: two generation chains for the same form wrote the same temp path; the first `UpdateXlsformFile` consumed the file (`addMediaFromDisk` moves the source unless `preservingOriginal()`), the second found nothing. The apostrophe in the title is not involved. | `laravel.log` 2026-10-29 11:16:38-48; `Xlsform::generateXlsfile()` |
| 6 | `deployDraft()` checks `$this->processing` on a possibly stale model instance, and `generateXlsfile()` sets the flag afterwards, so two near-simultaneous requests can both pass the check. | `Xlsform.php` `deployDraft()` / `generateXlsfile()` |

### Staging workaround already applied

The staging site runs PHP 8.4 (not 8.5). Web `memory_limit` was 128M (8.4 FPM) and was raised for the staging pool only. This only hides finding 2; Phase 1 removes the need for it.

## Phase 1: stop serialising whole templates into the import (app repo)

1. `TranslationUploadInspector::unknownRows()`: read ids with `$template->surveyRows()->pluck('id')` and `$template->choiceListEntries()->pluck('id')` (query builder, no cached relation).
2. `XlsformTemplateLanguageImport`: hold `localeId`, `xlsformTemplateId` and `importedById` instead of model instances, and resolve the models lazily where needed (`model()`, `withValidator()`, the `ImportFailed` handler). The import object is serialised once per chunk job, so it must stay small. Pull the id lookups in `withValidator()` through query builders as in step 1.
3. Update `TeamTranslationReviewEditForm::submit()` to construct the import with ids.
4. Test (Pest, `tests/Feature/SurveyLanguages/`): after `TranslationUploadInspector::validate()` the template has no loaded `surveyRows` / `choiceListEntries` relations, and `serialize()` of the constructed import stays below a small size threshold regardless of template size.

## Phase 2: make `processing_count` reliable (app repo + package `Locale`)

1. Increment atomically (`Locale::whereKey($id)->increment('processing_count')`) only **after** `Excel::queueImport` returned; wrap in try/catch and report the exception plus a danger notification if dispatching fails (nothing to decrement in that case).
2. Decrement atomically and never below zero in `NotifyUserThatLanguageImportIsComplete` and `...IsFailed`: `Locale::whereKey($id)->where('processing_count', '>', 0)->decrement('processing_count')`.
3. Add a sweeper command (for example `locales:reset-stuck-processing`, scheduled every 15 minutes): reset `processing_count` to 0 for locales whose `updated_at` is older than 30 minutes while no queued job references them. This covers imports killed without firing `ImportFailed` (worker crash, timeout, OOM), which includes finding 4.
4. Tests: dispatch failure leaves the count unchanged; double decrement stays at 0; sweeper resets a stale locale and leaves a recent one alone.
5. Investigate finding 4 separately once Phase 1 is deployed, by re-running the Global upload with the worker log open. If the completion job still never runs, find out why the chain stops.

Supersedes the suggested direction in `docs/issues/2026-08-02-locale-processing-count-stuck-risk.md`; update that issue's status when this lands.

## Phase 3: prevent concurrent XLSForm generation for one form (package)

1. `Xlsform::generateXlsfile()`: claim the form atomically before doing any work, for example `Xlsform::whereKey($this->getKey())->where('processing', false)->update(['processing' => true])`, and return without dispatching when no row was updated. `deployDraft()` and `publishForm()` already treat a skipped generation as "form is processing".
2. Use a unique temp path per run, for example `temp/{id}/{uuid}.xlsx`, and keep the human-readable title as the media file name via `usingFileName()`. Delete the temp file after `UpdateXlsformFile` has stored it.
3. Make sure a failure anywhere in the chain clears `processing` (the existing `failed()` handlers already do this for `UpdateXlsformFile`; check the export jobs).
4. Tests (package, Pest): two `generateXlsfile()` calls in a row dispatch one chain; two chains with distinct paths both succeed; the stored media keeps the form title as its file name.
5. Identify what dispatched two chains for form 9 at about 11:15-11:16 on 2026-10-29. Candidates: `Team::deployDraftForms()` triggered twice (page loaded or reloaded twice), plus the `saved` hook in `Xlsform::booted()`. Step 1 makes the cause harmless; document it if found.

## Rollout order

1. Phase 1 and 2 (app repo only) can ship first; Phase 3 needs the submodule bump.
2. Deploy to staging, `php artisan queue:restart`.
3. Re-upload the Global Spanish translation for a locale, with `storage/logs/laravel.log` open. Confirm: no memory error, the completion notification arrives, `processing_count` returns to 0.
4. Trigger a draft deploy for all three forms and confirm no `failed_jobs` rows.

## Out of scope: template changes (Alex)

- **Crop area units choice filter.** Alex will simplify the template and move `plants` / `shoots` to a separate, non-localisable list. Name the new list so it does not end in `_unit`: `FinishChoiceListEntryImport` applies the `conversion_rate` extra-property handling to any localisable list whose name contains `_unit`.
- **Missing conversion rates on default options.** Entry `properties` come from extra columns on the template's `choices` sheet, and `ChoiceListAsMediaAttachmentExport` exports only those, so the template needs a `conversion_rate` column filled in for the default options. Re-uploading the template changes choice entry ids, so translation files must then be regenerated from the new template's blank download.

## Open items

- Cause of the missing completion notification in finding 4 (Phase 2, step 5).
- In the Enketo preview of the Global form after the second import, most labels are Spanish, but some options and labels still look empty. Not yet checked: whether those are choices with no Spanish text in Alex's file, lists handled outside the translation file (for example the location lists), or a real import gap. Check after Alex's template changes and the next import.
- The staging PHP 8.5 FPM service has been stuck in `reloading` since 2026-10-27. Unrelated to this plan; needs a restart or a look from whoever manages the server.
