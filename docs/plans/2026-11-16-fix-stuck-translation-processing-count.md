# Plan: fix the translation "Processing" status getting stuck

**Status: In Progress.** Implemented and tested locally (full suite passes); waiting for staging verification and the change log.

Branch: `fix-processing-count`. Main repo only; no submodule change.

## Problem

On staging, the Survey Translations page showed a locale as "Processing" long after its imports had finished. The `locales.processing_count` column stayed above zero (for example 3 with nothing left in the `jobs` table), and only a manual reset in the database cleared it.

## Cause

`Locale::status` reports "Processing" while `processing_count > 0`. The count is maintained with read-modify-write on a model instance:

- `TeamTranslationReviewEditForm::submit()` runs `$this->locale->processing_count++` then `save()` once per template, while the queue workers are already importing the earlier templates.
- `NotifyUserThatLanguageImportIsComplete` and `NotifyUserThatLanguageImportIsFailed` run `$this->locale->processing_count--` then `save()` on their own copy of the locale.

Each `save()` writes an absolute value computed from a possibly stale copy, so two writers racing overwrite each other and an increment or decrement is lost. This was proven on staging with a database trigger audit: a decrement written by the queue worker was overwritten by the web request's later write, which set the count from 1 to 3.

A second, smaller gap: if the dispatch itself fails after the increment, or the completion job fails permanently (`failed()` only notifies), nothing decrements the count.

## Fix

1. **Atomic updates.** Replace the model-level `++`/`--` with single SQL statements that do not depend on a stale copy:
   - Increment: `Locale::whereKey($id)->increment('processing_count', $count)`.
   - Decrement: `Locale::whereKey($id)->where('processing_count', '>', 0)->decrement('processing_count')`. The guard keeps the count from going below zero (it is `unsignedInteger`) if a job runs twice.
   - Put both in one small class in the main repo, `App\Services\LocaleImportCounter` (`started(Locale, int $count = 1)` and `finished(Locale)`), so the three call sites share it. `Locale` lives in the submodule, so the helper is kept out of it to keep this change main-repo only.
2. **Increment before dispatching.** In `submit()`, count the valid files first, increment once by that number before queueing any import, then queue them. A fast worker can then never decrement ahead of the matching increment.
3. **Compensate when dispatch fails.** If queueing a template's import throws, decrement for it, report the error and tell the user, instead of leaving the count raised.
4. **Release the count exactly once per import, even when the finishing job fails.** Today `handle()` decrements first, so a retry after a later step throws would decrement again and take another import's count. Move the decrement to the end of `handle()` in both finishing jobs, after the module version links, the draft flag and the notification have succeeded, and make `failed()` of each job decrement as well. A job that fails permanently never reached the end of `handle()`, so the count is released once; a retried job decrements only on the attempt that completes.
5. The jobs stop calling `save()` on the locale for the count.

Not changed: the import itself, the notifications' wording, and `Locale::status`.

## Tests

Add to `tests/Feature/SurveyLanguages/TranslationEditFormTest.php` (and a job test file if cleaner):

1. Submitting with two valid template files raises `processing_count` by 2 before any import runs, and ends at 0 once both complete.
2. A stale copy cannot overwrite the count: increment through one `Locale` instance while another, older instance decrements; the stored count is the correct net value.
3. The decrement never goes below 0, including when a finishing job runs more than once.
4. The failed-import job decrements the count.
5. If queueing an import throws, the count is returned to its previous value and the user is notified.
6. A completion job that fails on its first attempt and succeeds on retry releases the count once; one that fails permanently releases it once.

Run the full suite, `./vendor/bin/pint` on the changed files.

## Verification on staging

1. Reset any stuck count first: `UPDATE locales SET processing_count = 0 WHERE id = <locale>` (after checking `jobs` is empty).
2. Upload and submit translations for a locale that has several templates (team 6, locale 8), three or four times in a row, as when the problem showed. Between runs wait for the "Translation Import Complete" notifications.
3. After all imports finish, `SELECT id, processing_count FROM locales WHERE id = 8` must be 0 and the page must show the normal status, not "Processing".
4. Submit a deliberately invalid upload and confirm the count returns to 0 as well.

## Out of scope

- The "Processing" label for a locale that is genuinely importing stays as it is.
- Resetting existing stuck counts: repaired by hand on staging; no migration.
