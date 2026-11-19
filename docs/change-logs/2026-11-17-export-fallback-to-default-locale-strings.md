# Change log: fall back to the default locale's text when a team locale has none (2026-11-17)

Plan: [2026-11-17-export-fallback-to-default-locale-strings.md](../plans/2026-11-17-export-fallback-to-default-locale-strings.md)

The code change is in the `packages/filament-odk-link` submodule; this repo bumps the submodule and holds the plan and this log.

## Problem

A team that creates its own locale for a language (for example "Spanish (Guatemala)" next to the default "Spanish") got blank cells in the generated XLSForm for every string it had not translated, even though the default Spanish text exists. Strings that are only stored against the default locale of a language, such as location level names and farmer hints, were affected.

## Change

For each locale column, the text is chosen in this order: the team locale's own non-empty text, then the non-empty text of the default locale of the same language, then empty. There is no fallback to another language. An empty or blank string counts as empty, because the translation import writes `''` for blank cells.

Applies to `label`, `hint`, `required_message`, `relevant_message` and `constraint_message`. Media columns are unchanged. A locale that is the default of its language has no fallback. Stored data is never modified.

- `ExportsXlsformContent::getLanguageStrings` (survey sheet and choice list CSV attachments) resolves the text as above, reading the row's loaded `languageStrings` instead of querying per cell and looking up each locale's default locale once per export.
- `XlsformChoicesExport::getChoiceLabels` falls back to the default locale after trying the locale across same-named duplicate entries.
- New `DefaultLocaleFallbackTest` in the submodule (16 tests).

## Verified

- Local: submodule 268 tests and main repo 400 tests pass. A downloaded generated form showed the team's text where the upload had it and the default Spanish text where the upload was blank.
- Staging (2026-11-17), team 3, Farmer Registration: after uploading a Spanish (Guatemala) translation with most cells blank, the form deployed to ODK Central (15:37:19) and, in Enketo with Spanish selected, showed the team's text for the choices that had it ("Yes in Spanish", "No in Spanish") and the default Spanish text for the blank ones (question labels, group labels and the hint).

## Notes found while testing (not part of this change)

- The Admin Panel "Download xls file" button regenerates the workbook from the current data each time; it does not download the file that was deployed to ODK Central.
- After a translation import completes the form is only flagged `draft_needs_update`; it is deployed the next time the Initial Pilot page is opened, which surprised the tester. This is the behaviour issue 2 (explicit update action with progress) addresses.
- The translation file lists the strings of every module version of the template, so a question can appear twice (issue 5, module version scope). Clearing the default-language reference columns of the file has no effect, because the upload reads only the column of the locale being edited (see docs/issues/2026-11-17-translation-file-reference-columns-look-editable.md).
