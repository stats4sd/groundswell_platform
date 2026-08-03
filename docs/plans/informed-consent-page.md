# Informed Consent editing page (Place-based adaptations)

**Status:** Completed — see [change log](../change-logs/informed-consent-page.md)

## Context

Teams must be able to adapt the informed-consent statement read to participants at the start of each survey form. That text is the `hint` of the `enum_intro` survey row (`type: note`, `introduction` module) — currently present in the Global Indicators and Women's Form templates; Farm Registration will gain it later. Editing is technically possible via the translation spreadsheet round-trip, but it's important enough to deserve a dedicated, prominent page in the Place-based adaptations section, similar to the HDDS hint editing page but text-focused: one large WYSIWYG editor per form per team language, instead of a table.

Stored text goes **verbatim** into the exported xlsform cell → ODK Collect, which renders only a limited markdown subset (`**bold**`, `_italic_`, literal newlines; headers not rendered in hints). So the DB keeps ODK-markdown; the page converts markdown→HTML to fill the editor and HTML→ODK-markdown on save.

User decisions: layout = one Section per form with all language editors stacked inside (no tabs).

## Key existing patterns to reuse

- Template page: [HddsHints.php](../../app/Filament/App/Pages/PlaceAdaptations/HddsHints.php) — `canAccess()` via `view place-based adaptations` permission, `HelperService::getCurrentOwner()`, **clone-on-first-visit** (`XlsformModuleVersion::cloneForOwner()` + re-point `selected_xlsform_module_versions` pivot preserving `order`), breadcrumbs, save via `languageStrings()->updateOrCreate(...)` + `$record->touch()` (fires `SurveyRow::saved` hook → `draft_needs_update = true` on linked xlsforms; redeploy happens later via Initial Pilot page).
- `SurveyRow->getLanguageString('hint'|'label', Locale)` (trait `HasLanguageStrings`), `$team->locales`, `Locale::language_label`, `LanguageStringType::where('name', 'hint')`.
- Entry cards: `place-adaptations-index.blade.php` `<x-rounded-section>` conditional card pattern.
- Instructions sidebar: `tfile('...')` + `resources/text/*.md`.
- Filament RichEditor state is an HTML string on dehydration (don't call `->json()`); toolbar restricted with grouped format `->toolbarButtons([['bold','italic'],['undo','redo']])`.
- Fixture precedent: `makeTeamWithHdds()` in `tests/Feature/PlaceAdaptations/HddsHintsTest.php`.

## Design decisions

1. **Clone on mount** (HddsHints precedent), one clone per affected module version, with a `[$globalVersionId => $clone]` map to avoid double-cloning if a version is shared. Cloning keeps form state keyed by stable (team-owned) SurveyRow IDs. `cloneForOwner` attaches xlsforms only after rows are saved, so no spurious `draft_needs_update`.
2. **Dynamic lookup by row name**: per team xlsform, find the selected module version `whereHas('surveyRows', name = 'enum_intro')` via `Xlsform::xlsformModuleVersions()`. Farm Registration appears automatically once its template gains the row.
3. **Hand-rolled markdown↔HTML converter, no new composer dep.**
   - Reject `league/html-to-markdown` (not installed; backslash-escapes `*`/`_`/`#`, which ODK Collect renders literally).
   - Reject `Str::markdown()` for fill: legacy consent text is plain text — a line starting with `#`/`1.`/`>` would become header/list/quote, then get stripped on save = silent data loss.
   - Canonical lossless mapping so fill→save with no edits is a byte no-op:
     - `toHtml`: normalise CRLF, HTML-escape, `**x**`→`<strong>`, `_x_`→`<em>`, wrap all in one `<p>`, `\n`→`<br>`.
     - `fromHtml`: DOMDocument walk — text verbatim, `strong/b`→`**`, `em/i`→`_`, `br`→`\n`, block elements (`p,div,h1–h6,blockquote,li`)→contents + `\n\n` boundary, everything else unwrapped; replace `&nbsp;` (TipTap empty-paragraph padding), trim trailing whitespace/newlines. Consecutive `<br>`s are preserved as consecutive newlines. Round-trip unit tests are the invariant.
4. **Change-diffing in save()**: compare existing text (trimmed; `LanguageString::text` setter coerces null→`' '`) before writing, and only `touch()` rows that changed — so Save with no edits does not flag `draft_needs_update`.
5. No new permission: reuse `view place-based adaptations` (already seeded/granted).

## Files

### New
1. `app/Services/OdkMarkdownService.php` — `toHtml(?string): string` and `fromHtml(?string): string` per decision 3.
2. `app/Filament/App/Pages/PlaceAdaptations/InformedConsent.php` — Filament Page, auto-discovered (URL `/app/{team}/informed-consent`), mount/form/save per decisions 1–4.
3. `resources/views/filament/app/pages/place-adaptations/informed-consent.blade.php` — instructions sidebar + `<form wire:submit="save">{{ $this->form }}` + submit button.
4. `resources/text/informed-consent.md` — sidebar instructions.
5. `tests/Unit/OdkMarkdownServiceTest.php`, `tests/Feature/PlaceAdaptations/InformedConsentTest.php`.

### Modified
6. `app/Models/Team.php` — add `informedConsentXlsforms(): Collection` beside `hddsModuleVersion()`.
7. `resources/views/filament/app/pages/place-adaptations/place-adaptations-index.blade.php` — conditional "Adapt informed consent" card.

## Verification

- Unit tests: toHtml/fromHtml conversions and round-trip invariants (`fromHtml(toHtml($x)) === $x`).
- Feature tests: renders section per form, fill conversion, clone-on-visit without flagging, save writes ODK markdown + flags `draft_needs_update`, no-op save doesn't flag, third form appears when `enum_intro` row added, 404 without any rows.
- Full suite + phpstan + pint.
- Manual: card → page → edit/save → DB text is ODK markdown → xlsx hint cell contains `**bold**`/newlines.
