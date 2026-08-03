# Informed Consent editing page

Implements [docs/plans/informed-consent-page.md](../plans/informed-consent-page.md).

Adds a dedicated page in the Place-based adaptations section where teams edit the informed-consent statement (`enum_intro` survey row hint) for each survey form, in each team language, using a WYSIWYG editor whose output is constrained to the markdown subset ODK Collect can render.

## New

- **`app/Services/OdkMarkdownService.php`** — lossless converter between the ODK-markdown stored in `language_strings.text` (`**bold**`, `_italic_`, literal newlines) and the HTML used by the Filament RichEditor. `toHtml()` escapes HTML and never misinterprets plain text as markdown (so legacy consent text starting with `#` or `1.` cannot be mangled); `fromHtml()` walks the DOM, keeps bold/italic/line breaks, and strips everything else (headings, links, colours, pasted styling). Filling the editor and saving without edits leaves stored text byte-identical.
- **`app/Filament/App/Pages/PlaceAdaptations/InformedConsent.php`** — page at `/app/{team}/informed-consent`, gated by the existing `view place-based adaptations` permission. One Section per form that has an `enum_intro` row (queried dynamically by row name, so Farm Registration gains an editor automatically once its template includes the row), each with a read-only question label per language and a RichEditor (bold/italic/undo/redo only) per team locale. On first visit, global module versions containing `enum_intro` are cloned for the team via `cloneForOwner()` and the `selected_xlsform_module_versions` pivots re-pointed (same pattern as the HDDS hints page). Save diffs against existing text and only touches changed rows, so an unedited save does not flag `draft_needs_update`.
- **`resources/views/filament/app/pages/place-adaptations/informed-consent.blade.php`** — instructions sidebar + page-level form with a single Save button.
- **`resources/text/informed-consent.md`** — sidebar instructions.
- **`tests/Unit/OdkMarkdownServiceTest.php`** — conversion and round-trip invariant tests (39 tests).
- **`tests/Feature/PlaceAdaptations/InformedConsentTest.php`** — renders, fill conversion, clone-on-visit without flagging, save writes ODK markdown and flags the form, no-op save, dynamic third form, 404 without rows.

## Modified

- **`app/Models/Team.php`** — added `informedConsentXlsforms()`: the team's xlsforms whose selected module versions contain an `enum_intro` row.
- **`resources/views/filament/app/pages/place-adaptations/place-adaptations-index.blade.php`** — conditional "Adapt informed consent" card between the HDDS hints and choice list cards.

## Notes

- No new composer dependency: the converter is hand-rolled because `league/html-to-markdown` backslash-escapes `*`/`_`/`#` (rendered literally by ODK Collect) and CommonMark would reinterpret legacy plain-text consent statements as markdown.
- Deployment path unchanged: edits flag `draft_needs_update` via the `SurveyRow::saved` hook; forms are redeployed when the Initial Pilot page is next opened.
- Full suite: 312 passed. phpstan/pint: no new issues (the `Pivot::$order` phpstan warning matches the pre-existing pattern in `HddsHints.php`).
