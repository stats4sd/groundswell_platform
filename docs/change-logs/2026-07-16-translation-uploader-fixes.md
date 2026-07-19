# Translation Uploader Fixes (issue #36) — Change Log

Implements [docs/plans/2026-07-16-translation-uploader-fixes.md](../plans/2026-07-16-translation-uploader-fixes.md), which addresses GitHub issue #36 and the expanded findings in [docs/code-reviews/2026-07-16-translation-uploader-review.md](../code-reviews/2026-07-16-translation-uploader-review.md). All work landed on branch `fix/translation-uploader-issue-36` (the `filament-odk-link` submodule carries a matching `fix/translation-uploader-issue-36` branch). Every behavioural change was written test-first (RED → GREEN).

## What shipped

### Import — header-resolved target column + full per-chunk validation
- New `App\Imports\TranslationUploadInspector` reads an uploaded file and exposes `textColumnIndex(Locale)` (the last header column matching the locale's `language_label`, since the export appends the current locale after the reference columns) and `validate(Locale, XlsformTemplate)` (returns human-readable errors for wrong fixed headers, a missing target column, or rows whose entry ids do not belong to the template).
- `XlsformTemplateLanguageImport` now takes a required 4th constructor argument `int $textColumnIndex` and reads the translation text from that column instead of the hard-coded column G (`$row[6]`). This was the root cause of issue #36: templates with more than one default language imported the wrong language's text.
- `withValidator()` was rewritten to validate **every** row in each chunk against the template's own survey rows and choice entries, instead of only the first row of the chunk looked up globally.

### Export — owner-filtered reference columns, verified-empty template, sheet protection
- `XlsformTemplateTranslationsExport` gained an optional `?WithXlsforms $owner` constructor argument. When an owner is supplied, the default-language reference columns are limited to the languages that owner has selected. Data rows (`processEntry`) now iterate the same owner-filtered locale set as `headings()`, so columns stay aligned.
- The empty-template path (`withExistingStrings: false`) reliably leaves the current-locale column blank; the previously conditionally-set `$currentStringForLanguage` is now always initialised.
- `styles()` enables sheet protection: identifier columns (A–E) and the header row stay locked, translation data columns (F onward) are unlocked, so uploads keep matching the template.

### Edit-form component — distinct downloads, real empty template, validated + team-scoped submit
- `TeamTranslationReviewEditForm` now defines two distinctly-named actions, `download_existing_{id}` and `download_empty_{id}` (previously both shared `download_{id}` and both passed `withExistingStrings: true`, so the "empty template" button returned existing translations). Both pass `owner: $this->team`.
- `submit()` loops the team's own templates (not `XlsformTemplate::all()`), validates each stored upload with `TranslationUploadInspector`, deletes invalid files and reports the errors via a persistent notification, and passes the resolved column index into the import.
- Hardened the file-upload field label against non-array state (`blank($state)` instead of `count($state)`), which previously threw during render.

### Locale visibility scoping + component cleanups
- `TeamTranslationEntry` table is scoped to default locales plus locales created by the current team, so a team no longer sees other teams' work-in-progress locales.
- Removed the dead `validateFileUpload()` method (superseded by the inspector), an unused `mount()` parameter, and a stray newline inside a `view()` name.

### Dead component removal + package pivot typo
- Deleted the unused `TeamTranslationReview` Livewire component and its view (not embedded anywhere; hardcoded form selection; always produced an empty export). The root-level `resources/views/team-translation-review.blade.php` modal view (used by `TeamTranslationEntry`) is retained.
- Fixed `Locale::owners()` in the package: the pivot referenced the misspelled `langauge_id` (the column is `language_id`) and used mis-cased `BelongsToMany`.

## Deferred follow-ups
- [docs/issues/2026-07-16-structured-locale-sharing.md](../issues/2026-07-16-structured-locale-sharing.md) — structured cross-team locale sharing with a review/approval step.
- [docs/issues/2026-07-16-locale-delete-action.md](../issues/2026-07-16-locale-delete-action.md) — a delete action for team-created locales.

## Deviation from the plan
The plan's `TranslationUploadInspector` read the file via `Excel::toCollection`. In this environment that corrupts the facade-cached maatwebsite `Reader` (its `spreadsheet` property is `unset()` during garbage collection), which then breaks serialization of the queued import chain that runs immediately afterwards in the same request — the exact inspector → `queueImport` sequence used by `submit()`. The inspector instead reads the workbook directly via PhpSpreadsheet `IOFactory`, leaving the shared Reader untouched. Public interface is unchanged.

## New tests
- `tests/Support/TranslationFixture.php`, `tests/Support/ArrayUpload.php` — shared fixtures.
- `tests/Feature/SurveyLanguages/TranslationFixtureTest.php`
- `tests/Feature/SurveyLanguages/TranslationImportTest.php`
- `tests/Feature/SurveyLanguages/TranslationExportTest.php`
- `tests/Feature/SurveyLanguages/TranslationEditFormTest.php`
- `tests/Feature/SurveyLanguages/TranslationEntryTableTest.php`

## Quality gates
- `./vendor/bin/pest` — 155 passed.
- `./vendor/bin/pint --dirty` — passed.
- `./vendor/bin/phpstan analyse` — 188 pre-existing-style errors; no new error category. The only message in new code is a `Collection::map()` closure-typehint variance in `submit()` identical to the pre-existing one in `form()`.
- Package `composer test` — 168 passed; `composer analyse` — the export file reports zero errors and the touched `Locale::owners()` adds none (the sole Locale.php error is pre-existing, at an unrelated line).
