# Translation file: the default-language columns look editable but are ignored on upload

**Status**: Open — improvement (TODO), not a bug. Found during local testing of the default-locale fallback (2026-11-17).

The translation file a team downloads from Survey Translations has one text column per default language (for example `English (default)`, `Spanish (default)`) followed by the column for the locale being edited (for example `Spanish (Guatemala)`). The upload only reads the last column, for the locale being edited (`TranslationUploadInspector::textColumnIndex`). The default-language columns are reference text only; clearing or changing them does nothing.

A translator can easily assume otherwise. In testing, blanking `English (default)` and `Spanish (default)` for three questions changed nothing: the generated form still had the English and default Spanish text, and the Spanish (Guatemala) column fell back to the default Spanish.

Suggested shape: make the reference columns visibly read-only in the downloaded file, for example grey them out, protect those cells, or add "(reference only)" to their headings, and say the same in the instructions on the upload page. Check that the upload's header validation (`TranslationUploadInspector`) still accepts the changed headings.

Related: the same file also lists the strings of every module version of the template, so a question can appear twice (issue 5, module version scope); a fix there may change the same export.
