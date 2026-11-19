# Translation file: same-named choice entries from different modules are listed more than once

**Status**: Open — to be discussed (not decided). Split off from issue 5 (module version scope) on 2026-11-19.

The same `list_name` can exist in several modules of a template (for example `crop_list` in `crops` and `crop_intensification`). Each copy has its own `choice_list_entries` rows, so the translation file lists the same entry once per copy and a translator translates it more than once. This stays true after the file is limited to the module versions a form uses (`docs/plans/2026-11-19-translation-file-scope-to-form-module-versions.md`).

Local numbers (entries the form really uses, `list_name`+`name` pairs that repeat): Global Indicators Form 82 pairs of 591 entries; Women's Form 3 pairs of 160.

Idea: list one row per (`list_name`, `name`) and, on import, write the text to every same-named entry.

Points to discuss:

- Same name in one list with different `properties` (filters, cascading selects) must stay separate; the merge key needs the properties.
- A merged row needs one representative `entry_id`, and `TranslationUploadInspector` and the import validation must understand it.
- Form generation already fills a locale from any same-named duplicate (`XlsformChoicesExport::getChoiceLabels`), so a missed copy does not break the form. The gain is mostly fewer rows for the translator (about 14% of choice rows in the Global Indicators Form).
- Risk: medium (import path and fan-out), value: moderate.
