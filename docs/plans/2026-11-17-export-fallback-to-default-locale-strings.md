# Plan: fall back to the default locale's text when a team locale has none

**Status: Completed.** Change log: [2026-11-17-export-fallback-to-default-locale-strings.md](../change-logs/2026-11-17-export-fallback-to-default-locale-strings.md)

Branches: main repo `fix-export` (plan, change log, submodule bump); submodule `packages/filament-odk-link` (code and tests), PR base `staging-hotfix-20261026`.

## Problem

A team that has created its own locale for a language (for example "Spanish (Guatemala)" next to the default "Spanish") gets a generated form whose columns for that locale are empty wherever the team has not translated a string. In the generated XLSForm this means blank `label::Spanish (Guatemala) (es)` cells, even though the default Spanish text exists. The blank cells show in Enketo, and if a row is blank in every language ODK rejects the form.

Reported by Alex for strings that are only stored against the default locale of a language, such as location level names and farmer hints (the `label::Lang (iso)` property headers are always saved to the language's default locale).

## Decision (agreed 2026-11-16)

For every locale column in a generated form, the text is chosen in this order:

1. The team locale's own text, if it is not empty.
2. The default locale of the same language, if its text is not empty.
3. Empty. There is no fallback to another language (for example English).

An empty string counts as empty: the translation import writes `''` for blank cells, so a blank cell must not hide the default text.

Applies to the text string types: `label`, `hint`, `required_message`, `relevant_message`, `constraint_message`. Media columns (`mediaimage`, `mediaaudio`, `mediavideo`) are left as they are, because a file for one language is not valid for another.

A locale that is itself the default locale of its language has no step 2.

## Where it applies

All exports that fill locale columns from `language_strings`:

- `XlsformSurveyExport` (survey sheet), through `ExportsXlsformContent::getLanguageStrings`.
- `XlsformChoicesExport::getChoiceLabels` (choices sheet). It already fills each locale from any same-named duplicate entry; the default-locale step is added after that, so the order stays: this locale across the duplicates, then the default locale of the language across the duplicates, then empty.
- `ChoiceListAsMediaAttachmentExport` (choice lists attached as CSV), through the same trait method.

Not changed: the settings sheet, the translation review page and download/upload, the import, and the locale "completeness" status. The stored data is never modified; only the generated file differs.

## Fix

- In `ExportsXlsformContent`, add one resolver that returns the text for a row, a locale and a string type using the order above, with the fallback only for the text types. `getLanguageStrings` uses it.
- Read from the row's already loaded `languageStrings` relation (loading it when it is not loaded) instead of running a query per cell as `getLanguageStrings` does today.
- Find a locale's default counterpart through its language (`Language::defaultLocale`), resolved once per export for the form's locales, not per cell.
- In `XlsformChoicesExport`, the label index already holds every locale's non-empty strings (it is not limited to the form's locales), so the default locale's text is available without another query.

## Tests

In the submodule (`tests/Unit/Exports/`):

1. Survey export: a team locale with text keeps its own text even when the default locale has different text.
2. A team locale with no string, or an empty string, gets the default locale's text.
3. Neither has text: the cell is empty, and nothing is taken from another language.
4. Every text string type (label, hint, required_message, relevant_message, constraint_message) falls back.
5. `mediaimage` does not fall back.
6. A locale that is the default of its language is unchanged and does not look for another locale.
7. Choices export: a team locale falls back to the default locale's label, including through same-named duplicate entries, and still keeps its own label when it has one. Existing choices export tests must keep passing.
8. The choice-list CSV attachment export falls back too.

Then the full submodule and main suites, and `./vendor/bin/pint` on the changed files only.

## Verification on staging

1. Team 6 (locales: English default, Spanish (Guatemala)): in the Contextualise choice lists page leave a Spanish (Guatemala) label empty for a team entry whose default Spanish text exists, or use an entry that only has default Spanish text, and redeploy the drafts.
2. Download the draft xlsx from ODK Central. In the survey and choices sheets the Spanish (Guatemala) column shows the default Spanish text where the team has none, shows the team's text where it has some, and is empty where neither exists.
3. The forms still deploy, open in Enketo, and no new `failed_jobs` rows appear.

## Out of scope

- Falling back to English or any other language.
- Media columns.
- Writing the fallback text into the database, or showing it on the translation review page.
- Issue 5 (module version scope), which is discussed separately.
