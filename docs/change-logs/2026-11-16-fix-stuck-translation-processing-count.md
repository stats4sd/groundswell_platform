# Change log: fix the translation "Processing" status getting stuck (2026-11-16)

Plan: [2026-11-16-fix-stuck-translation-processing-count.md](../plans/2026-11-16-fix-stuck-translation-processing-count.md)

## Problem

On staging, locale 8 on the Survey Translations page stayed on "Processing" after its imports had finished. `locales.processing_count` stayed above zero with an empty `jobs` table, and only a manual reset cleared it.

## Cause

`processing_count` was changed with read-modify-write on model instances (`++` / `--` then `save()`), by the web request in `TeamTranslationReviewEditForm::submit()` and by the two finishing jobs on the queue workers. Each `save()` wrote an absolute value computed from a possibly stale copy, so one writer could overwrite another's change. A database trigger audit on staging caught it: a worker's decrement was overwritten by the request's next write, taking the count from 1 to 3. The request raised the count once per template over about 6 seconds, which was the window for the overwrite.

The finishing jobs also decremented at the start of `handle()`, so a retry after a later step failed would decrement twice, and a job that failed permanently never released its count.

## Changes

- `app/Services/LocaleImportCounter.php` (new): `started()` and `finished()`, each a single SQL statement (`increment`, and `decrement` guarded by `processing_count > 0`).
- `app/Livewire/SurveyLanguages/TeamTranslationReviewEditForm.php`: `submit()` validates all files first, raises the count once by the number of valid imports before queueing any, and gives a count back and notifies the user if queueing an import throws.
- `app/Jobs/NotifyUserThatLanguageImportIsComplete.php`, `app/Jobs/NotifyUserThatLanguageImportIsFailed.php`: release the count as the last step of `handle()` and in `failed()`, so each import releases exactly one count.
- Tests: new `tests/Feature/SurveyLanguages/LocaleProcessingCountTest.php`, plus two tests in `TranslationEditFormTest.php`.

The helper lives in the main repo (not on `Locale`) so the change stays out of the submodule.

## Verified

- Local: full suite, 400 passed.
- Staging (2026-11-17): with an audit trigger on `locales`, locale 8 was submitted many times, including overlapping submits (nine submits within about three minutes while earlier imports were still finishing, and a decrement landing between two submits). Every logged change was exactly `+3` (one write per submit, three templates) or `-1` (one per finished import); before the fix the same submit logged `+1, +1, +1` over about 6 seconds. The count returned to 0 after each burst, and the Survey Translations page showed "Ready for use" for both Spanish translations instead of sticking on "Processing". No new `failed_jobs` rows (max id stayed at 78).
- The audit table and trigger are temporary and were not kept.
