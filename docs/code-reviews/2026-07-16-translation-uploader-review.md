# Review: ODK Translation Text Uploader (GitHub issue #36)

**Date**: 2026-07-16
**Branch**: `dev`
**Reviewer**: Claude (exploratory review of the translation upload/download system, prompted by issue #36)
**Scope**: `app/Imports/XlsformTemplateLanguageImport.php`, `app/Livewire/SurveyLanguages/*`, `packages/filament-odk-link/src/Exports/XlsformTemplateTranslationsExport.php`, related Locale/Language models and migrations.

## Summary

All three problems in issue #36 are confirmed in code, and each has a clear, localised cause:

1. **Wrong-column import — CONFIRMED.** The importer hard-codes the translation text column as index 6 (column G), which is only correct when the template has exactly one default language. With 4 default-language reference columns, column G is French — exactly matching the reported "Abkhazian shows the French text".
2. **Unselected languages in the file — CONFIRMED.** The export includes every *template* default locale and never receives the team, so team language selection cannot influence the columns.
3. **"Empty template" download is not empty — CONFIRMED, and the empty export already exists.** Both download actions call the export with `withExistingStrings: true`; the export's default (`false`) already produces a genuinely empty template. There is also a second, masking bug: both actions share the same Filament action name, so they resolve to the same handler regardless of their closures.

The review also confirms both problems from the follow-up comment (no delete for team-created locales; team-created locales visible to all teams), plus several smaller defects. Details below.

---

## How the pieces fit together

The active flow is: `SurveyTranslations` Filament page → [`TeamTranslationEntry`](../../app/Livewire/SurveyLanguages/TeamTranslationEntry.php) (a table of `Locale` records per language) → "View / Edit Translation" modal → [`team-translation-review.blade.php`](../../resources/views/team-translation-review.blade.php) → [`TeamTranslationReviewEditForm`](../../app/Livewire/SurveyLanguages/TeamTranslationReviewEditForm.php), which hosts the two download actions and the file upload. On submit, the uploaded file is queued through [`XlsformTemplateLanguageImport`](../../app/Imports/XlsformTemplateLanguageImport.php), which upserts `LanguageString` rows for the target `Locale`.

The downloaded file is produced by [`XlsformTemplateTranslationsExport`](../../packages/filament-odk-link/src/Exports/XlsformTemplateTranslationsExport.php). Its column layout (from `headings()`) is:

| Col | Index | Content |
|-----|-------|---------|
| A | 0 | `row type` (`survey` / `choices`) |
| B | 1 | `choice_list_id` (hidden, width 0) |
| C | 2 | `entry_id` (hidden, width 0) |
| D | 3 | `name` |
| E | 4 | `translation type` |
| F… | 5… | **one column per template default locale** (`$template->locales` filtered `is_default`) |
| last | 5 + n | the current locale being translated |

The number of reference columns (F onwards) is **variable** — it is the number of default locales present on every default module version of the template.

---

## 1. Main bug: import reads a hard-coded column (issue assumption CONFIRMED)

[`XlsformTemplateLanguageImport::model()`](../../app/Imports/XlsformTemplateLanguageImport.php#L73) does:

```php
'text' => $row[6],
```

Index 6 is column G. Given the layout above, G holds the current locale's text **only when the template has exactly one default locale** (5 fixed columns + 1 reference column → target at index 6). The issue's suspicion — "assuming only 1 other language in the uploaded file" — is exactly right.

In the reported reproduction the template has 4 default locales (English, French, Nepali, Spanish → columns F–I), so the target column is J (index 9). The import instead read column G — French — and wrote French text into the Abkhazian locale's `LanguageString` rows, which then compiled into the ODK form. This fully explains the observed behaviour; no ODK-side involvement.

Note the import does not implement `WithHeadingRow`; the header row is only skipped because `isEmptyWhen()` checks `$row[0] === 'row type'`. There is no check anywhere that the file's last-column header matches the target locale, that the column count matches the template's current locale set, or that the file corresponds to the template it is uploaded against (beyond the per-row `entry_id` existence check).

### Recommended fix

Resolve the target column from the header row instead of hard-coding it. Two workable options:

- **Read the header row first** (e.g. in the importer via a `beforeSheet`/`BeforeImport` event, or a lightweight `HeadingRowImport`-style pre-pass in `submit()`), find the index whose heading equals `$locale->language_label`, and store it on the importer. This mirrors the matching logic that already exists (unused) in `TeamTranslationEntry::validateFileUpload()`.
- **Take the last column** (`count($row) - 1`). Simpler, and structurally the current locale is always last — but it is fragile against trailing edits/blank columns users add in Excel, so header matching is preferred, possibly with "last column" as a fallback and a validation error when neither resolves.

Either way, the fix should be paired with upload-time validation (see finding 5) so a mismatched file is rejected with a clear message instead of silently importing the wrong column. The existing unique index on `language_strings` (`locale_id`, `linked_entry_id`, `linked_entry_type`, `language_string_type_id` — migration `029`) means a corrected re-upload cleanly overwrites the bad rows via the upsert, so no data migration is needed beyond re-importing.

---

## 2. Languages not selected by the team appear in the file (CONFIRMED)

[`XlsformTemplateTranslationsExport::__construct()`](../../packages/filament-odk-link/src/Exports/XlsformTemplateTranslationsExport.php#L32) builds the reference columns from:

```php
$this->locales = $template->locales->filter(fn (Locale $locale) => $locale->is_default);
```

`$template->locales` is a computed attribute on `XlsformTemplate` — the intersection of locales linked to every default module version. It is a property of the **template**, with no team context. Team language selection lives on the `language_owner` pivot (`HasXlsforms::languages()` / `locales()`, with a `locale_id` per team+language row), and the export never receives the team, so Spanish appears for a team that never selected it.

### Recommended fix

Pass the owner/team into the export and filter the default locales to languages the team has selected, e.g. `$template->locales->filter(fn ($l) => $l->is_default && $team->languages->contains($l->language_id))`. Every current call site (`TeamTranslationReviewEditForm`, `TeamTranslationEntry::validateFileUpload`) already has `$this->team` available.

**Ordering constraint:** do not ship this before fix 1. While the import hard-codes index 6, changing the number of reference columns per team changes *which* wrong text gets imported. Once the import resolves the column by header, the reference column count becomes free to vary.

---

## 3. "Download empty template" downloads the current translations (CONFIRMED — two stacked bugs)

Both download actions in [`TeamTranslationReviewEditForm::form()`](../../app/Livewire/SurveyLanguages/TeamTranslationReviewEditForm.php#L62-L81) call the export identically:

- "Download existing translations" (line 69): `new XlsformTemplateTranslationsExport($xlsformTemplate, $this->locale, withExistingStrings: true)`
- "Download empty translation template" (line 80): `new XlsformTemplateTranslationsExport($xlsformTemplate, $this->locale, withExistingStrings: true)` — same flag.

**A real empty-template export already exists and needs no new code**: `withExistingStrings` defaults to `false`, and `processEntry()` then leaves the final column blank (`$currentStringForLanguage->text ?? ''` with the variable never set). The fix is to drop the `withExistingStrings: true` argument from the "empty" action.

**However, that alone will not fix the button.** Both actions are registered with the same name — `Action::make('download_' . $xlsformTemplate->id)` — inside the same schema. Filament mounts and resolves actions by name, so both buttons resolve to a single action definition; the "empty" button executes the "existing" closure today, and would keep doing so after the flag change. The actions must get distinct names, e.g. `download_existing_{id}` and `download_empty_{id}`.

(The issue's observation that the two downloads are "expected" to match when no translations exist is consistent with this: with no `LanguageString` rows for the locale, `withExistingStrings: true` also produces blanks.)

---

## Expanded findings (follow-up comment + review observations)

### 4. Team-created locales are visible to every team, and cannot be deleted (comment on #36 — both CONFIRMED)

- **Visibility:** the translations table in [`TeamTranslationEntry::table()`](../../app/Livewire/SurveyLanguages/TeamTranslationEntry.php#L59-L62) lists `$this->language->locales()` — *all* locales for the language, with no filter on `creator_id`. Any locale a team creates via "Add new" or `duplicate()` immediately appears for every team that selected that language. The modal blade even acknowledges the situation ("This translation was uploaded by another team…") rather than scoping it. Whether cross-team sharing is intended is a product decision, but the current behaviour (every team's drafts and mistakes are global) matches the reported problem. If sharing is intended, it should at minimum exclude other teams' incomplete/draft locales; if not, filter to `is_default` or `creator_id === $this->team->id`.
- **No delete:** `recordActions` contains only "Select" and "View / Edit"; neither the table nor the edit modal offers a delete for team-created locales, so a mistaken duplicate is permanent without manual DB intervention. A delete action should be added, visible only when `$record->is_editable` (creator is current team) and `!$record->is_default`, guarded against deleting the team's currently-selected locale, and cascading its `languageStrings` (the `locales` migration/model should be checked for cascade behaviour when implementing).

### 5. The upload validation that would have caught bug 1 exists but is never wired up

[`TeamTranslationEntry::validateFileUpload()`](../../app/Livewire/SurveyLanguages/TeamTranslationEntry.php#L162-L213) reads the uploaded file, checks the required headers (including a column headed `$record->language_label`), and starts checking row completeness — but nothing calls it. It also has its own bugs: it checks for a header `'type'` where the export writes `'row type'`, compares against `'translation_type'` where the export writes `'translation type'`, uses `$headers->contains(...)` (boolean) where the variable names suggest indexes were intended, and its two TODOs are unfinished. When implementing fix 1, this method (corrected) is the natural home for upload-time validation, attached to the `SpatieMediaLibraryFileUpload` in `TeamTranslationReviewEditForm`.

### 6. Import entity-existence validation only checks the first row of each chunk

[`XlsformTemplateLanguageImport::withValidator()`](../../app/Imports/XlsformTemplateLanguageImport.php#L119-L146) does `$row = collect($data)->first()` in the `after()` hook, so only the first row per 200-row chunk is checked for a matching `SurveyRow`/`ChoiceListEntry`. Rows 2–200 with stale or edited `entry_id`s pass validation, and the friendly "wrong template?" error only fires if the *first* row happens to be bad. The check should iterate all rows in `$data` (batched with a single `whereIn` lookup per chunk to avoid 200 queries).

### 7. `submit()` iterates all templates instead of the team's

[`TeamTranslationReviewEditForm::submit()`](../../app/Livewire/SurveyLanguages/TeamTranslationReviewEditForm.php#L117) loops `XlsformTemplate::all()` where the form itself is built from `$this->team->xlsforms`. Harmless today only because the media filter finds no file for unrelated templates, but it does extra work and would misbehave if template IDs ever collide across media custom properties. Loop the team's templates instead.

### 8. Dead component: `TeamTranslationReview`

[`TeamTranslationReview`](../../app/Livewire/SurveyLanguages/TeamTranslationReview.php) (with `downloadHouseholdAction`/`downloadFieldworkAction`) appears unused — no blade embeds it (the modal uses the root `team-translation-review` view, which embeds the edit form component directly). It contains its own bugs (hardcoded `xlsforms->first()`/`->last()` with TODOs, the fieldwork download saves as `HOLPA_household_translations`, and both downloads produce *empty* templates since they omit `withExistingStrings`). Recommend deleting it rather than maintaining it.

### 9. Minor defects noted in passing

- `Locale::owners()` declares `->withPivot(['langauge_id'])` — typo for `language_id`, so the pivot attribute is always null ([Locale.php:119](../../packages/filament-odk-link/src/Models/OdkLink/XlsformLanguages/Locale.php#L119)).
- The import identifies rows purely by the hidden `entry_id` column (width 0, but not protected). A user who sorts or edits hidden columns in Excel silently retargets translations. Sheet protection on columns A–E would be cheap insurance.
- The modal `view()` name in `TeamTranslationEntry` line 145 contains a leading newline inside the string literal (`view('\n team-translation-review', …)`); Laravel's view finder trims it, but it should be cleaned up.
- `TeamTranslationEntry::mount(Locale $locale)` takes a `$locale` parameter it never uses.
- **No tests** cover `XlsformTemplateLanguageImport`, `XlsformTemplateTranslationsExport`, or the Livewire components — nothing in `tests/` or the package's test suite references them. The column-layout round-trip (export → import) is exactly the kind of contract a Pest test should pin down before fixing bug 1.

---

## Suggested fix order

1. **Import column resolution by header** (fix 1) + wire up corrected upload validation (finding 5), with a round-trip Pest test using a template with ≥2 default locales.
2. **Rename the duplicate download actions and pass `withExistingStrings: false`** for the empty template (fix 3) — small, independent, user-visible.
3. **Filter export reference columns by team selection** (fix 2) — only after step 1 is deployed.
4. **Locale scoping and delete action** (finding 4) — needs a product decision on cross-team sharing first.
5. Cleanups: findings 6–9.
