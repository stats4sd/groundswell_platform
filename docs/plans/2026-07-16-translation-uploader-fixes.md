# Translation Uploader Fixes (GitHub issue #36) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Completed — see [change log](../change-logs/2026-07-16-translation-uploader-fixes.md)

**Goal:** Fix all confirmed defects in the ODK translation upload/download system (issue #36 plus the expanded findings in [docs/code-reviews/2026-07-16-translation-uploader-review.md](../code-reviews/2026-07-16-translation-uploader-review.md)), with tests written first so each fix turns a failing test green.

**Architecture:** The importer stops hard-coding column G and instead receives the target column index, resolved from the file's header row by a new `TranslationUploadInspector` class that also validates uploads before they are queued. The export gains an optional `owner` filter so reference columns match the team's selected languages, and locks the identifier columns via sheet protection. The Livewire components get correct action wiring, team-scoped locale visibility, and the dead component is removed.

**Tech Stack:** Laravel 11, Filament 3 (Schemas/Actions API), Livewire 3, maatwebsite/excel 3.1, PhpSpreadsheet, Pest 3 (SQLite in-memory, `DatabaseSeeder` auto-runs, `QUEUE_CONNECTION=sync`).

## Global Constraints

- Follow the laravel-php-guidelines skill for all PHP (typed properties, early returns, no `else` where avoidable, import all classnames, no single-letter variables, string interpolation).
- No `private const` in PHP (user rule) — inline single-use literals or use private properties.
- Never mention Claude in commit titles or messages; no `Co-Authored-By: Claude` lines.
- Do not hard-wrap prose in `.md` files.
- Tests before fixes: every behavioural task writes the failing test first, verifies RED, then implements, then verifies GREEN.
- All work on a feature branch off `dev`.
- Deployment-order note from the review ("ship the export column filter only after the import fix") is satisfied automatically because everything lands in one branch/PR; within the branch, the import fix (Task 2) still precedes the export change (Task 3) for commit hygiene.
- Out of scope (explicitly deferred, captured in Task 7): structured cross-team locale sharing, and a delete button for team-created locales.

## Reference: exported file column layout

`XlsformTemplateTranslationsExport` produces: `A` row type, `B` choice_list_id, `C` entry_id, `D` name, `E` translation type, then one column per template default locale, then the **current locale as the last column**. Column headers for default locales are `Locale::language_label` = `"{Language name} (default)"`; the current locale's header is its `description` (e.g. `"Abkhazian (test)"`).

The test fixture (Task 1) creates **three** default locales (English, French, Spanish) so the wrong-column bug is observable: the buggy import reads index 6 = "French (default)", while the correct target column is the last one.

---

### Task 1: Branch + shared test fixture

**Files:**
- Create: `tests/Support/TranslationFixture.php`
- Create: `tests/Support/ArrayUpload.php`
- Create: `tests/Feature/SurveyLanguages/TranslationFixtureTest.php`

**Interfaces:**
- Produces: `Tests\Support\TranslationFixture::make(): TranslationFixture` with public properties `team`, `template`, `moduleVersion`, `englishLocale`, `frenchLocale`, `spanishLocale`, `abkhazianLocale`, `surveyRows` (Collection of 2 `SurveyRow`), `choiceListEntries` (Collection of 2 `ChoiceListEntry`). Every later test task consumes this.
- Produces: `Tests\Support\ArrayUpload` — a minimal `FromArray` export used to write user-style upload files in tests.

- [ ] **Step 1: Create the branch**

```bash
git checkout dev && git pull && git checkout -b fix/translation-uploader-issue-36
```

- [ ] **Step 2: Write the fixture class**

`tests/Support/TranslationFixture.php`. Everything is created inside `Model::withoutEvents()` so no ODK-deployment `booted()` hooks fire, and module-version links are attached explicitly (deterministic, independent of the `Locale::created` hook). Languages and `LanguageStringType`s already exist via the always-run `Prep` seeders.

```php
<?php

namespace Tests\Support;

use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class TranslationFixture
{
    public Team $team;

    public XlsformTemplate $template;

    public XlsformModuleVersion $moduleVersion;

    public Locale $englishLocale;

    public Locale $frenchLocale;

    public Locale $spanishLocale;

    public Locale $abkhazianLocale;

    /** @var Collection<int, SurveyRow> */
    public Collection $surveyRows;

    /** @var Collection<int, ChoiceListEntry> */
    public Collection $choiceListEntries;

    /**
     * Template with 3 default locales (English, French, Spanish), 2 survey rows and 2 choice
     * entries with 'label' strings per default locale, owned by a team that has selected
     * English, French and Abkhazian (NOT Spanish), plus a team-created Abkhazian locale
     * ("Abkhazian (test)") with no language strings yet.
     */
    public static function make(): self
    {
        Http::fake();

        return Model::withoutEvents(function (): self {
            $fixture = new self;

            $fixture->team = Team::factory()->create();

            $fixture->template = XlsformTemplate::forceCreate(['title' => 'Translation Test Form']);
            $fixture->template->owner()->associate($fixture->team);
            $fixture->template->save();

            $module = XlsformModule::create([
                'xlsform_template_id' => $fixture->template->id,
                'label' => 'Main module',
                'name' => 'main',
            ]);

            $fixture->moduleVersion = XlsformModuleVersion::create([
                'xlsform_module_id' => $module->id,
                'name' => 'v1',
                'is_default' => true,
            ]);

            Xlsform::create([
                'xlsform_template_id' => $fixture->template->id,
                'owner_id' => $fixture->team->id,
            ]);

            $fixture->surveyRows = collect([
                ['name' => 'q1', 'type' => 'text', 'row_number' => 1],
                ['name' => 'q2', 'type' => 'integer', 'row_number' => 2],
            ])->map(fn (array $attributes): SurveyRow => SurveyRow::create([
                ...$attributes,
                'xlsform_module_version_id' => $fixture->moduleVersion->id,
            ]));

            $choiceList = ChoiceList::create([
                'xlsform_module_version_id' => $fixture->moduleVersion->id,
                'list_name' => 'yes_no',
            ]);

            $fixture->choiceListEntries = collect(['yes', 'no'])
                ->map(fn (string $name): ChoiceListEntry => ChoiceListEntry::create([
                    'choice_list_id' => $choiceList->id,
                    'name' => $name,
                ]));

            $fixture->englishLocale = $fixture->createDefaultLocale('en');
            $fixture->frenchLocale = $fixture->createDefaultLocale('fr');
            $fixture->spanishLocale = $fixture->createDefaultLocale('es');

            $abkhazian = Language::where('iso_alpha2', 'ab')->firstOrFail();

            $fixture->abkhazianLocale = Locale::create([
                'language_id' => $abkhazian->id,
                'is_default' => false,
                'creator_id' => $fixture->team->id,
                'description' => 'Abkhazian (test)',
            ]);
            $fixture->abkhazianLocale->xlsformModuleVersions()->attach($fixture->moduleVersion->id);

            $fixture->team->languages()->attach([
                $fixture->englishLocale->language_id => ['locale_id' => $fixture->englishLocale->id],
                $fixture->frenchLocale->language_id => ['locale_id' => $fixture->frenchLocale->id],
                $abkhazian->id => ['locale_id' => $fixture->abkhazianLocale->id],
            ]);

            $fixture->createDefaultLanguageStrings();

            return $fixture;
        });
    }

    private function createDefaultLocale(string $isoAlpha2): Locale
    {
        $language = Language::where('iso_alpha2', $isoAlpha2)->firstOrFail();

        $locale = Locale::create([
            'language_id' => $language->id,
            'is_default' => true,
        ]);

        $locale->xlsformModuleVersions()->attach($this->moduleVersion->id);

        return $locale;
    }

    private function createDefaultLanguageStrings(): void
    {
        $labelType = LanguageStringType::where('name', 'label')->firstOrFail();

        $defaultLocales = collect([$this->englishLocale, $this->frenchLocale, $this->spanishLocale]);

        $defaultLocales->each(function (Locale $locale) use ($labelType): void {
            $languageName = $locale->language->name;

            $this->surveyRows->each(fn (SurveyRow $surveyRow) => LanguageString::create([
                'locale_id' => $locale->id,
                'language_string_type_id' => $labelType->id,
                'linked_entry_id' => $surveyRow->id,
                'linked_entry_type' => SurveyRow::class,
                'text' => "{$languageName} label for {$surveyRow->name}",
            ]));

            $this->choiceListEntries->each(fn (ChoiceListEntry $entry) => LanguageString::create([
                'locale_id' => $locale->id,
                'language_string_type_id' => $labelType->id,
                'linked_entry_id' => $entry->id,
                'linked_entry_type' => ChoiceListEntry::class,
                'text' => "{$languageName} label for {$entry->name}",
            ]));
        });
    }
}
```

- [ ] **Step 3: Write the upload-builder support class**

`tests/Support/ArrayUpload.php`:

```php
<?php

namespace Tests\Support;

use Maatwebsite\Excel\Concerns\FromArray;

class ArrayUpload implements FromArray
{
    /** @param array<int, array<int, mixed>> $rows */
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }
}
```

- [ ] **Step 4: Write the fixture sanity test**

`tests/Feature/SurveyLanguages/TranslationFixtureTest.php`:

```php
<?php

use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Tests\Support\TranslationFixture;

test('the translation fixture builds a template with three default locales and a team-created locale', function () {
    $fixture = TranslationFixture::make();

    $templateLocaleIds = $fixture->template->locales->pluck('id');

    expect($templateLocaleIds)->toContain(
        $fixture->englishLocale->id,
        $fixture->frenchLocale->id,
        $fixture->spanishLocale->id,
        $fixture->abkhazianLocale->id,
    );

    $defaultLocales = $fixture->template->locales->filter(fn (Locale $locale) => $locale->is_default);

    expect($defaultLocales)->toHaveCount(3)
        ->and($fixture->team->languages)->toHaveCount(3)
        ->and($fixture->team->languages->pluck('iso_alpha2'))->not->toContain('es')
        ->and($fixture->abkhazianLocale->language_label)->toBe('Abkhazian (test)')
        ->and($fixture->englishLocale->language_label)->toBe('English (default)')
        ->and($fixture->template->surveyRows)->toHaveCount(2)
        ->and($fixture->template->choiceListEntries)->toHaveCount(2);
});
```

- [ ] **Step 5: Run the sanity test**

Run: `./vendor/bin/pest tests/Feature/SurveyLanguages/TranslationFixtureTest.php`
Expected: PASS. If `language_label` assertions fail, check the `Language` seeder names for `en`/`ab` and adjust expected strings — do not change the accessor.

- [ ] **Step 6: Commit**

```bash
git add tests/Support/TranslationFixture.php tests/Support/ArrayUpload.php tests/Feature/SurveyLanguages/TranslationFixtureTest.php
git commit -m "test: add shared fixture for translation upload/download tests"
```

---

### Task 2: Import fix — header-resolved target column + full per-chunk validation (review fixes 1 and 6)

**Files:**
- Create: `app/Imports/TranslationUploadInspector.php`
- Modify: `app/Imports/XlsformTemplateLanguageImport.php`
- Test: `tests/Feature/SurveyLanguages/TranslationImportTest.php`

**Interfaces:**
- Produces: `App\Imports\TranslationUploadInspector::__construct(UploadedFile|string $file)`, `->textColumnIndex(Locale $locale): ?int`, `->validate(Locale $locale, XlsformTemplate $template): array` (list of human-readable error strings, empty when valid). Task 4 consumes all three.
- Produces: `XlsformTemplateLanguageImport::__construct(Locale $locale, XlsformTemplate $xlsformTemplate, User $importedBy, int $textColumnIndex)` — note the new required fourth argument. Task 4's `submit()` consumes this.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/SurveyLanguages/TranslationImportTest.php`:

```php
<?php

use App\Imports\TranslationUploadInspector;
use App\Imports\XlsformTemplateLanguageImport;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Tests\Support\ArrayUpload;
use Tests\Support\TranslationFixture;

function storeTranslationUpload(array $rows): string
{
    Excel::store(new ArrayUpload($rows), 'translation-upload.xlsx', 'local');

    return Storage::disk('local')->path('translation-upload.xlsx');
}

function uploadHeadings(): array
{
    return ['row type', 'choice_list_id', 'entry_id', 'name', 'translation type', 'English (default)', 'French (default)', 'Spanish (default)', 'Abkhazian (test)'];
}

function uploadRowsFor(TranslationFixture $fixture, callable $textFor): array
{
    $rows = [uploadHeadings()];

    foreach ($fixture->surveyRows as $surveyRow) {
        $rows[] = ['survey', '', $surveyRow->id, $surveyRow->name, 'label', 'en ref', 'French text', 'es ref', $textFor($surveyRow->name)];
    }

    foreach ($fixture->choiceListEntries as $entry) {
        $rows[] = ['choices', $entry->choice_list_id, $entry->id, $entry->name, 'label', 'en ref', 'French text', 'es ref', $textFor($entry->name)];
    }

    return $rows;
}

test('translation text is imported from the target locale column, not column G', function () {
    Notification::fake();
    Storage::fake('local');

    $fixture = TranslationFixture::make();
    $user = createAppUser($fixture->team);

    $path = storeTranslationUpload(uploadRowsFor($fixture, fn (string $name) => "AB Test; {$name}"));

    $inspector = new TranslationUploadInspector($path);

    expect($inspector->textColumnIndex($fixture->abkhazianLocale))->toBe(8)
        ->and($inspector->validate($fixture->abkhazianLocale, $fixture->template))->toBe([]);

    Excel::import(
        new XlsformTemplateLanguageImport($fixture->abkhazianLocale, $fixture->template, $user, 8),
        $path,
    );

    $importedTexts = LanguageString::where('locale_id', $fixture->abkhazianLocale->id)->pluck('text');

    expect($importedTexts)->toHaveCount(4);

    $importedTexts->each(fn (string $text) => expect($text)->toStartWith('AB Test; '));
});

test('the inspector reports a missing target locale column', function () {
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    $headingsWithoutTarget = array_slice(uploadHeadings(), 0, 8);
    $path = storeTranslationUpload([$headingsWithoutTarget]);

    $inspector = new TranslationUploadInspector($path);

    expect($inspector->textColumnIndex($fixture->abkhazianLocale))->toBeNull()
        ->and($inspector->validate($fixture->abkhazianLocale, $fixture->template))
        ->toHaveCount(1)
        ->and($inspector->validate($fixture->abkhazianLocale, $fixture->template)[0])
        ->toContain('Abkhazian (test)');
});

test('the inspector reports rows that do not belong to the template', function () {
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    $rows = uploadRowsFor($fixture, fn (string $name) => "AB Test; {$name}");
    $rows[1][2] = 999999;

    $path = storeTranslationUpload($rows);

    $errors = (new TranslationUploadInspector($path))->validate($fixture->abkhazianLocale, $fixture->template);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('Translation Test Form');
});

test('the import rejects a file where a row after the first has an unknown entry id', function () {
    Notification::fake();
    Storage::fake('local');

    $fixture = TranslationFixture::make();
    $user = createAppUser($fixture->team);

    $rows = uploadRowsFor($fixture, fn (string $name) => "AB Test; {$name}");
    $rows[2][2] = 999999;

    $path = storeTranslationUpload($rows);

    expect(function () use ($fixture, $user, $path) {
        Excel::import(
            new XlsformTemplateLanguageImport($fixture->abkhazianLocale, $fixture->template, $user, 8),
            $path,
        );
    })->toThrow(ValidationException::class);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/SurveyLanguages/TranslationImportTest.php`
Expected: FAIL — first three tests error with `Class "App\Imports\TranslationUploadInspector" not found`; the fourth also fails for the same reason. (After Step 3 alone they must STILL fail: test 1 imports "French text" instead of "AB Test; …" because `model()` reads `$row[6]`, and test 4 throws nothing because only the first row per chunk is checked. PHP silently ignores the extra constructor argument, so no error masks the real failures.)

- [ ] **Step 3: Create the inspector**

`app/Imports/TranslationUploadInspector.php`:

```php
<?php

namespace App\Imports;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class TranslationUploadInspector
{
    public Collection $headings;

    public Collection $rows;

    public function __construct(UploadedFile|string $file)
    {
        $sheetRows = Excel::toCollection((object) [], $file)->first() ?? collect();

        $this->headings = $sheetRows->first() ?? collect();
        $this->rows = $sheetRows->skip(1)->values();
    }

    // The current locale's column is always the last one matching its label, because the
    // export appends it after the default-locale reference columns.
    public function textColumnIndex(Locale $locale): ?int
    {
        $lastMatchingIndex = null;

        foreach ($this->headings as $index => $heading) {
            if ($heading === $locale->language_label) {
                $lastMatchingIndex = $index;
            }
        }

        return $lastMatchingIndex;
    }

    /** @return array<int, string> */
    public function validate(Locale $locale, XlsformTemplate $template): array
    {
        $errors = [];

        $expectedFixedHeadings = ['row type', 'choice_list_id', 'entry_id', 'name', 'translation type'];

        foreach ($expectedFixedHeadings as $index => $expectedHeading) {
            if (($this->headings[$index] ?? null) !== $expectedHeading) {
                $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
                $errors[] = "Column {$columnLetter} should have the header '{$expectedHeading}'. Please use the template downloaded from this platform and do not edit the column headers.";
            }
        }

        if ($this->textColumnIndex($locale) === null) {
            $errors[] = "The file is missing the translation column '{$locale->language_label}'. Please use the template downloaded from this platform for this translation.";
        }

        $unknownRows = $this->unknownRows($template);

        if ($unknownRows->isNotEmpty()) {
            $exampleNames = $unknownRows->pluck(3)->filter()->take(5)->join(', ');
            $errors[] = "{$unknownRows->count()} row(s) do not match any question or choice entry in the form '{$template->title}' (e.g. {$exampleNames}). Please check you are uploading the correct translation file for this form.";
        }

        return $errors;
    }

    private function unknownRows(XlsformTemplate $template): Collection
    {
        $surveyRowIds = $template->surveyRows->pluck('id');
        $choiceListEntryIds = $template->choiceListEntries->pluck('id');

        return $this->rows
            ->filter(fn (Collection $row) => filled($row[0] ?? null))
            ->filter(fn (Collection $row) => filled($row[2] ?? null))
            ->filter(fn (Collection $row) => match ($row[0]) {
                'survey' => ! $surveyRowIds->contains((int) $row[2]),
                'choices' => ! $choiceListEntryIds->contains((int) $row[2]),
                default => true,
            });
    }
}
```

- [ ] **Step 4: Fix the import class**

In `app/Imports/XlsformTemplateLanguageImport.php`:

1. Add the fourth constructor parameter:

```php
public function __construct(
    public Locale $locale,
    public XlsformTemplate $xlsformTemplate,
    public User $importedBy,
    public int $textColumnIndex,
) {
    $this->languageStringTypes = LanguageStringType::all();
}
```

2. In `model()`, replace `'text' => $row[6],` with:

```php
'text' => $row[$this->textColumnIndex] ?? '',
```

Also delete the commented-out `//Validator::make(...)` line and the stale `// 'updated_during_import' => 1, - TODO` comment while in the method.

3. Replace `withValidator()` entirely — the old version only checked the first row per chunk and looked entities up globally instead of within the template:

```php
public function withValidator(Validator $validator): void
{
    $validator->after(function (Validator $validator) {
        $surveyRowIds = $this->xlsformTemplate->surveyRows->pluck('id');
        $choiceListEntryIds = $this->xlsformTemplate->choiceListEntries->pluck('id');

        foreach ($validator->getData() as $rowIndex => $row) {
            $validIds = match ($row[0]) {
                'survey' => $surveyRowIds,
                'choices' => $choiceListEntryIds,
                default => collect(),
            };

            if ($validIds->contains((int) $row[2])) {
                continue;
            }

            $validator->errors()->add("{$rowIndex}.2", "The {$row[0]} row named <b>{$row[3]}</b> does not match any question or choice entry in the form '{$this->xlsformTemplate->title}'. <br/><br/> Please ensure you are using the correct template to upload the translations.");
        }
    });
}
```

Add `use Illuminate\Validation\Validator;` and remove the now-unused imports (`SurveyRow`, `ChoiceListEntry` if no longer referenced elsewhere in the file, `ChoiceListEntriesInfo`, `Illuminate\Support\Facades\Validator`, `Illuminate\Support\Str` — check each with a quick grep of the file before removing).

- [ ] **Step 5: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/SurveyLanguages/TranslationImportTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Imports/ tests/Feature/SurveyLanguages/TranslationImportTest.php
git commit -m "fix: resolve translation import column from file headers instead of hard-coded column G

The importer assumed exactly one template language and always read
column G, so templates with several default languages imported the
wrong language's text (issue #36). The target column is now resolved
from the header row via TranslationUploadInspector, and import
validation now checks every row per chunk against the template's own
survey rows and choice entries instead of the first row only."
```

---

### Task 3: Export — owner-filtered reference columns, verified empty template, sheet protection (review fixes 2, part of 3, finding 9)

**Files:**
- Modify: `packages/filament-odk-link/src/Exports/XlsformTemplateTranslationsExport.php`
- Test: `tests/Feature/SurveyLanguages/TranslationExportTest.php`

**Interfaces:**
- Consumes: `TranslationFixture` from Task 1.
- Produces: `XlsformTemplateTranslationsExport::__construct(XlsformTemplate $template, Locale $currentLocale, bool $withExistingStrings = false, ?WithXlsforms $owner = null)`. Task 4 passes `owner: $this->team` from the Livewire component.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/SurveyLanguages/TranslationExportTest.php`:

```php
<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Tests\Support\TranslationFixture;

test('reference columns are limited to the default languages selected by the owner team', function () {
    $fixture = TranslationFixture::make();

    $export = new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: true, owner: $fixture->team);

    expect($export->headings())->toBe([
        'row type', 'choice_list_id', 'entry_id', 'name', 'translation type',
        'English (default)', 'French (default)', 'Abkhazian (test)',
    ]);
});

test('all default template languages are included when no owner is given', function () {
    $fixture = TranslationFixture::make();

    $export = new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: true);

    expect($export->headings())->toBe([
        'row type', 'choice_list_id', 'entry_id', 'name', 'translation type',
        'English (default)', 'French (default)', 'Spanish (default)', 'Abkhazian (test)',
    ]);
});

test('the empty template leaves the translation column blank even when strings exist', function () {
    $fixture = TranslationFixture::make();

    LanguageString::create([
        'locale_id' => $fixture->abkhazianLocale->id,
        'language_string_type_id' => LanguageStringType::where('name', 'label')->firstOrFail()->id,
        'linked_entry_id' => $fixture->surveyRows->first()->id,
        'linked_entry_type' => SurveyRow::class,
        'text' => 'Existing AB text',
    ]);

    $emptyExport = new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: false, owner: $fixture->team);

    $emptyExport->collection()->each(fn (Collection $row) => expect($row->last())->toBe(''));

    $filledExport = new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: true, owner: $fixture->team);

    expect($filledExport->collection()->map(fn (Collection $row) => $row->last()))
        ->toContain('Existing AB text');
});

test('the exported file locks the identifier columns and unlocks the translation columns', function () {
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    Excel::store(
        new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: false, owner: $fixture->team),
        'export.xlsx',
        'local',
    );

    $sheet = IOFactory::load(Storage::disk('local')->path('export.xlsx'))->getSheetByName('translations');

    expect($sheet->getProtection()->getSheet())->toBeTrue()
        ->and($sheet->getStyle('A2')->getProtection()->getLocked())->not->toBe(Protection::PROTECTION_UNPROTECTED)
        ->and($sheet->getStyle('C2')->getProtection()->getLocked())->not->toBe(Protection::PROTECTION_UNPROTECTED)
        ->and($sheet->getStyle('A1')->getProtection()->getLocked())->not->toBe(Protection::PROTECTION_UNPROTECTED)
        ->and($sheet->getStyle('H2')->getProtection()->getLocked())->toBe(Protection::PROTECTION_UNPROTECTED);
});
```

(Column `H` is the target column here: 5 fixed columns + English + French + target = 8 columns under the owner filter.)

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/SurveyLanguages/TranslationExportTest.php`
Expected: FAIL — tests 1, 3 and 4 error with `Unknown named parameter $owner`; test 2 passes already (it pins current behaviour so the refactor can't break it).

- [ ] **Step 3: Implement the export changes**

In `packages/filament-odk-link/src/Exports/XlsformTemplateTranslationsExport.php`:

1. Constructor — add the `$owner` parameter and filter:

```php
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;

public function __construct(
    public XlsformTemplate $template,
    public Locale $currentLocale,
    public bool $withExistingStrings = false,
    public ?WithXlsforms $owner = null,
) {
    $this->locales = $template->locales
        ->filter(fn (Locale $locale): bool => $locale->is_default && $this->ownerHasSelectedLanguage($locale))
        ->values();

    $this->allLanguageStringTypes = LanguageStringType::all();
}

private function ownerHasSelectedLanguage(Locale $locale): bool
{
    if (! $this->owner) {
        return true;
    }

    return $this->owner->languages->contains('id', $locale->language_id);
}
```

2. In `processEntry()`, initialise the variable that is currently only set conditionally (removes the undefined-variable reliance on `??`):

```php
$currentStringForLanguage = null;

if ($this->withExistingStrings) {
    $currentStringForLanguage = $strings->firstWhere('locale_id', $this->currentLocale->id);
}
```

Also fix the stale comment above it — it says "unless $empty is false" which is backwards; replace with nothing (the code is now self-explanatory).

3. At the end of `styles()`, enable sheet protection. Cells default to locked when sheet protection is on, so unlock the translation columns (F onward, data rows only) and leave columns A–E and the whole header row locked:

```php
use PhpOffice\PhpSpreadsheet\Style\Protection;

$sheet->getProtection()->setSheet(true);

if ($rowCount >= 2) {
    $lastColumn = Coordinate::stringFromColumnIndex($lastColumnIndex);
    $sheet->getStyle("F2:{$lastColumn}{$rowCount}")
        ->getProtection()
        ->setLocked(Protection::PROTECTION_UNPROTECTED);
}
```

(`$lastColumnIndex` and `$rowCount` already exist in `styles()`.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/SurveyLanguages/TranslationExportTest.php`
Expected: PASS (4 tests). Also run `./vendor/bin/pest tests/Feature/SurveyLanguages/TranslationImportTest.php` — still PASS (the import task did not depend on export changes).

- [ ] **Step 5: Run the package test suite**

Run: `cd packages/filament-odk-link && composer test; cd ../..`
Expected: no NEW failures versus a run on the base branch. If the package suite has pre-existing failures unrelated to the export, note them in the final change log rather than fixing them here.

- [ ] **Step 6: Commit**

```bash
git add packages/filament-odk-link/src/Exports/XlsformTemplateTranslationsExport.php tests/Feature/SurveyLanguages/TranslationExportTest.php
git commit -m "fix: scope translation export columns to the owner's selected languages and protect identifier columns

The export previously included every default template language
regardless of team selection. It now accepts an optional owner and
filters the reference columns to that owner's selected languages.
The identifier columns (A-E) and header row are locked via sheet
protection so uploads keep matching the template."
```

---

### Task 4: Edit-form component — distinct download actions, real empty template, validated uploads, team-scoped submit (review fixes 3, 5, 7)

**Files:**
- Modify: `app/Livewire/SurveyLanguages/TeamTranslationReviewEditForm.php`
- Test: `tests/Feature/SurveyLanguages/TranslationEditFormTest.php`

**Interfaces:**
- Consumes: `TranslationUploadInspector` and the 4-arg `XlsformTemplateLanguageImport` from Task 2; the `owner:` export parameter from Task 3.
- Produces: actions named `download_existing_{templateId}` and `download_empty_{templateId}` (blade templates do not reference action names, so nothing else changes).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/SurveyLanguages/TranslationEditFormTest.php`:

```php
<?php

use App\Livewire\SurveyLanguages\TeamTranslationReviewEditForm;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Stats4sd\FilamentOdkLink\Exports\XlsformTemplateTranslationsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Tests\Support\ArrayUpload;
use Tests\Support\TranslationFixture;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

function translationEditForm(TranslationFixture $fixture)
{
    $user = createAppUser($fixture->team);
    $user->givePermissionTo('maintain survey translations');

    actingAs($user);
    withAppTenant($fixture->team);

    return livewire(TeamTranslationReviewEditForm::class, [
        'locale' => $fixture->abkhazianLocale,
        'team' => $fixture->team,
        'canMaintain' => true,
    ]);
}

test('the two download actions are distinct and the empty template excludes existing strings', function () {
    $fixture = TranslationFixture::make();

    Excel::fake();

    translationEditForm($fixture)->callAction("download_empty_{$fixture->template->id}");

    Excel::assertDownloaded(
        "Translation Test Form translation template - Abkhazian (test).xlsx",
        fn (XlsformTemplateTranslationsExport $export) => $export->withExistingStrings === false
            && $export->owner !== null
            && $export->owner->is($fixture->team),
    );
});

test('the existing-translations download includes existing strings', function () {
    $fixture = TranslationFixture::make();

    Excel::fake();

    translationEditForm($fixture)->callAction("download_existing_{$fixture->template->id}");

    Excel::assertDownloaded(
        "Translation Test Form translation - Abkhazian (test).xlsx",
        fn (XlsformTemplateTranslationsExport $export) => $export->withExistingStrings === true
            && $export->owner !== null
            && $export->owner->is($fixture->team),
    );
});

test('submitting an exported template filled in by a translator imports the correct column (issue #36 round trip)', function () {
    Notification::fake();

    config()->set('filament-odk-link.storage.xlsforms', 'local');
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    Excel::store(
        new XlsformTemplateTranslationsExport($fixture->template, $fixture->abkhazianLocale, withExistingStrings: false, owner: $fixture->team),
        'template.xlsx',
        'local',
    );

    $path = Storage::disk('local')->path('template.xlsx');

    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getSheetByName('translations');
    $lastColumn = $sheet->getHighestColumn();

    foreach (range(2, (int) $sheet->getHighestRow()) as $rowNumber) {
        $entryName = $sheet->getCell("D{$rowNumber}")->getValue();
        $sheet->setCellValue("{$lastColumn}{$rowNumber}", "AB Test; {$entryName}");
    }

    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

    $fixture->abkhazianLocale
        ->addMedia($path)
        ->preservingOriginal()
        ->withCustomProperties(['xlsform_template_id' => $fixture->template->id])
        ->toMediaCollection('xlsform_template_translation_files');

    translationEditForm($fixture)->call('submit');

    $importedTexts = LanguageString::where('locale_id', $fixture->abkhazianLocale->id)->pluck('text');

    expect($importedTexts)->toHaveCount(4);

    $importedTexts->each(fn (string $text) => expect($text)->toStartWith('AB Test; '));

    expect($importedTexts->contains(fn (string $text) => str_contains($text, 'French')))->toBeFalse();
});

test('a translation file with wrong headers is rejected, removed, and nothing is imported', function () {
    Notification::fake();

    config()->set('filament-odk-link.storage.xlsforms', 'local');
    Storage::fake('local');

    $fixture = TranslationFixture::make();

    Excel::store(new ArrayUpload([['wrong', 'headers', 'entirely']]), 'bad-upload.xlsx', 'local');

    $fixture->abkhazianLocale
        ->addMedia(Storage::disk('local')->path('bad-upload.xlsx'))
        ->preservingOriginal()
        ->withCustomProperties(['xlsform_template_id' => $fixture->template->id])
        ->toMediaCollection('xlsform_template_translation_files');

    translationEditForm($fixture)->call('submit')->assertNotified();

    expect($fixture->abkhazianLocale->fresh()->getMedia('xlsform_template_translation_files'))->toHaveCount(0)
        ->and(LanguageString::where('locale_id', $fixture->abkhazianLocale->id)->count())->toBe(0);
});
```

Note for the executor: `callAction('name')` is the standard Filament test helper for components using `InteractsWithActions`. If Filament cannot resolve these actions because they are nested inside a schema `Actions::make()` component, switch to `->callAction(\Filament\Actions\Testing\Fixtures\TestAction::make("download_empty_{$fixture->template->id}"))` per the Filament action-testing docs — do not weaken the assertions.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/SurveyLanguages/TranslationEditFormTest.php`
Expected: FAIL — test 1 fails because no action named `download_empty_…` exists (both are currently `download_{id}`); test 3 imports French text into the Abkhazian locale (the current `submit()` builds the import without a column index — PHP silently ignores nothing here because the old constructor is still called with 3 args… after Task 2 the old call site is now missing the required 4th argument, so this fails with `ArgumentCountError`, which is equally RED); test 4 fails because no validation removes the bad file.

- [ ] **Step 3: Rewrite the two download actions**

In `TeamTranslationReviewEditForm::form()`, replace the two `Action::make('download_' . $xlsformTemplate->id)` definitions with:

```php
Action::make("download_existing_{$xlsformTemplate->id}")
    ->label(t('Download existing translations'))
    ->extraAttributes(['class' => 'buttona w-full'])
    ->action(fn () => Excel::download(
        new XlsformTemplateTranslationsExport($xlsformTemplate, $this->locale, withExistingStrings: true, owner: $this->team),
        "{$xlsformTemplate->title} translation - {$this->locale->language_label}.xlsx",
    )),

Action::make("download_empty_{$xlsformTemplate->id}")
    ->label(t('Download empty translation template'))
    ->extraAttributes(['class' => 'buttona w-full'])
    ->visible(fn () => $this->locale->is_editable)
    ->action(fn () => Excel::download(
        new XlsformTemplateTranslationsExport($xlsformTemplate, $this->locale, withExistingStrings: false, owner: $this->team),
        "{$xlsformTemplate->title} translation template - {$this->locale->language_label}.xlsx",
    )),
```

- [ ] **Step 4: Rewrite `submit()`**

Replace the whole method (loops the team's templates instead of `XlsformTemplate::all()`, validates each stored file with the inspector, deletes and reports invalid files, and passes the resolved column index to the import):

```php
public function submit(): void
{
    if (! auth()->user()->can('maintain survey translations')) {
        abort(403);
    }

    $this->form->getState();
    $this->form->saveRelationships();

    $this->locale->refresh();

    $xlsformTemplates = $this->team->xlsforms
        ->map(fn (Xlsform $xlsform) => $xlsform->xlsformTemplate)
        ->unique('id');

    foreach ($xlsformTemplates as $xlsformTemplate) {
        $file = $this->locale->getMedia('xlsform_template_translation_files', function (Media $media) use ($xlsformTemplate) {
            return isset($media->custom_properties['xlsform_template_id']) && $media->custom_properties['xlsform_template_id'] === $xlsformTemplate->id;
        })->first();

        if (! $file) {
            continue;
        }

        $inspector = new TranslationUploadInspector($file->getPath());
        $errors = $inspector->validate($this->locale, $xlsformTemplate);

        if ($errors !== []) {
            $file->delete();

            Notification::make()
                ->danger()
                ->title(t('The translation file could not be processed'))
                ->body(implode('<br/>', $errors))
                ->persistent()
                ->send();

            continue;
        }

        $this->locale->processing_count++;
        $this->locale->save();

        Excel::queueImport(new XlsformTemplateLanguageImport(
            $this->locale,
            $xlsformTemplate,
            auth()->user(),
            $inspector->textColumnIndex($this->locale),
        ), $file->getPath())
            ->chain([
                new NotifyUserThatLanguageImportIsComplete($this->locale, $xlsformTemplate, request()->user()),
            ]);
    }

    $this->dispatch('closeModal');
}
```

Add imports: `use App\Imports\TranslationUploadInspector;` and `use Filament\Notifications\Notification;`. Remove the now-unused `use phpDocumentor\Reflection\Types\Boolean;` and `use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;` / `XlsformModuleVersion` imports if nothing else in the file uses them.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/SurveyLanguages/TranslationEditFormTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/SurveyLanguages/TeamTranslationReviewEditForm.php tests/Feature/SurveyLanguages/TranslationEditFormTest.php
git commit -m "fix: separate the empty-template and existing-translations downloads and validate uploads before import

Both download actions shared one Filament action name and both passed
withExistingStrings: true, so the empty-template button returned the
existing translations. Uploads are now validated against the template
before the import is queued (headers, target column, entry ids), and
submit() only processes the team's own templates."
```

---

### Task 5: Locale visibility scoping + small component cleanups (review finding 4 and parts of 9)

**Files:**
- Modify: `app/Livewire/SurveyLanguages/TeamTranslationEntry.php`
- Test: `tests/Feature/SurveyLanguages/TranslationEntryTableTest.php`

**Interfaces:**
- Consumes: `TranslationFixture` from Task 1.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing test**

`tests/Feature/SurveyLanguages/TranslationEntryTableTest.php`:

```php
<?php

use App\Livewire\SurveyLanguages\TeamTranslationEntry;
use App\Models\Team;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Tests\Support\TranslationFixture;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

test('teams only see default locales and locales they created themselves', function () {
    $fixture = TranslationFixture::make();

    $otherTeam = Team::withoutEvents(fn () => Team::factory()->create());

    $abkhazianLanguageId = $fixture->abkhazianLocale->language_id;

    $defaultAbkhazianLocale = Locale::withoutEvents(fn () => Locale::create([
        'language_id' => $abkhazianLanguageId,
        'is_default' => true,
    ]));

    $otherTeamsLocale = Locale::withoutEvents(fn () => Locale::create([
        'language_id' => $abkhazianLanguageId,
        'is_default' => false,
        'creator_id' => $otherTeam->id,
        'description' => 'Abkhazian (other team)',
    ]));

    $user = createAppUser($fixture->team);
    actingAs($user);
    withAppTenant($fixture->team);

    $language = $fixture->team->languages->firstWhere('iso_alpha2', 'ab');

    livewire(TeamTranslationEntry::class, [
        'language' => $language,
        'team' => $fixture->team,
        'expanded' => true,
    ])
        ->assertCanSeeTableRecords([$fixture->abkhazianLocale, $defaultAbkhazianLocale])
        ->assertCanNotSeeTableRecords([$otherTeamsLocale]);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/SurveyLanguages/TranslationEntryTableTest.php`
Expected: FAIL on `assertCanNotSeeTableRecords` — the other team's locale is currently listed.

- [ ] **Step 3: Apply the component changes**

In `app/Livewire/SurveyLanguages/TeamTranslationEntry.php`:

1. Scope the table relationship (add `use Illuminate\Database\Eloquent\Builder;`):

```php
->relationship(
    fn () => $this->language
        ->locales()
        ->where(fn (Builder $query) => $query
            ->where('is_default', true)
            ->orWhere('creator_id', $this->team->id)
        )
)
```

2. Remove the unused `Locale $locale` mount parameter:

```php
public function mount(): void
{
    $this->selectedLocale = Locale::find($this->language->pivot->locale_id);
}
```

3. Fix the view name containing a literal newline (line ~145):

```php
->modalContent(fn (Locale $record) => view('team-translation-review', [
    'locale' => $record,
    'team' => $this->team,
    'canMaintain' => auth()->user()->can('maintain survey translations'),
]))
```

4. Delete the entire dead `validateFileUpload()` method (its job is now done by `TranslationUploadInspector`), then remove the imports it alone used (`Closure`, `Maatwebsite\Excel\Facades\Excel`, `XlsformTemplateTranslationsExport`, `ChoiceListEntry`, `SurveyRow`, `XlsformTemplate`, `Illuminate\Support\Collection` — verify each is otherwise unused before removing).

- [ ] **Step 4: Run the test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/SurveyLanguages/TranslationEntryTableTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/SurveyLanguages/TeamTranslationEntry.php tests/Feature/SurveyLanguages/TranslationEntryTableTest.php
git commit -m "fix: only show default locales and the team's own locales in the translations table

Locales created by any team were visible to every team that selected
the language. Cross-team sharing is intentionally disabled until a
structured sharing/review flow exists. Also removes the dead
validateFileUpload method, an unused mount parameter, and a stray
newline in a view name."
```

---

### Task 6: Delete the dead `TeamTranslationReview` component + package typo fix (review findings 8 and 9)

**Files:**
- Delete: `app/Livewire/SurveyLanguages/TeamTranslationReview.php`
- Delete: `resources/views/livewire/survey-languages/team-translation-review.blade.php`
- Modify: `packages/filament-odk-link/src/Models/OdkLink/XlsformLanguages/Locale.php:116-120`

**Interfaces:** none produced; nothing else references the deleted component.

- [ ] **Step 1: Confirm the component is unreferenced**

```bash
grep -rn "survey-languages.team-translation-review'" app/ resources/ --include='*.php' --include='*.blade.php'
grep -rn "livewire:survey-languages.team-translation-review[^-]" resources/
grep -rn "TeamTranslationReview[^E]" app/ resources/ tests/
```

Expected: the only hits are the component class itself and its own `render()`/view file. **KEEP** `resources/views/team-translation-review.blade.php` (root-level) — that is the modal view used by `TeamTranslationEntry` and it embeds `TeamTranslationReviewEditForm`, not this component. If any unexpected reference appears, stop and re-assess instead of deleting.

- [ ] **Step 2: Delete the dead files**

```bash
git rm app/Livewire/SurveyLanguages/TeamTranslationReview.php resources/views/livewire/survey-languages/team-translation-review.blade.php
```

- [ ] **Step 3: Fix the pivot typo in `Locale::owners()`**

In `packages/filament-odk-link/src/Models/OdkLink/XlsformLanguages/Locale.php`, the pivot column name is misspelled and the relation method call is mis-cased:

```php
public function owners(): BelongsToMany
{
    return $this->belongsToMany(config('filament-odk-link.models.form_owner'), 'language_owner', 'locale_id', 'owner_id')
        ->withPivot(['language_id']);
}
```

- [ ] **Step 4: Run the full test suite**

Run: `./vendor/bin/pest`
Expected: PASS (all tests, including all Task 1–5 tests). Any failure here means a hidden reference to the deleted component — investigate before proceeding.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: remove dead TeamTranslationReview component and fix locale pivot column typo

The component was not embedded anywhere, hardcoded first/last form
selection, and always produced empty exports under a household-survey
filename. The Locale::owners() pivot referenced 'langauge_id', so the
pivot attribute was always null."
```

---

### Task 7: Follow-up issue notes, quality gates, change log, plan status

**Files:**
- Create: `docs/issues/2026-07-16-structured-locale-sharing.md`
- Create: `docs/issues/2026-07-16-locale-delete-action.md`
- Create: `docs/change-logs/2026-07-16-translation-uploader-fixes.md`
- Modify: this plan file (Status line)

- [ ] **Step 1: Capture the deferred follow-ups**

`docs/issues/2026-07-16-structured-locale-sharing.md`:

```markdown
# Structured cross-team sharing of translation locales

**Status**: Deferred — out of scope for the issue #36 fixes (see docs/plans/2026-07-16-translation-uploader-fixes.md).

The original platform intended locales/translations to be shareable across teams, but the implementation that shipped exposed every team-created locale to every team with the language selected — an unstructured leftover of an unfinished attempt. As part of the issue #36 fixes, visibility was restricted to default locales plus the team's own locales.

A future, structured approach could let a team mark a locale as "complete and shareable", with a review/approval step in the admin or program panels before it becomes visible to other teams. Design questions: who approves (Program Admin vs Super Admin), whether shared locales are copied or referenced (edits by the owner team after sharing), and how "Needs updating" status propagates to consumers of a shared locale.
```

`docs/issues/2026-07-16-locale-delete-action.md`:

```markdown
# No delete action for team-created translation locales

**Status**: Open — reported in a comment on GitHub issue #36 (a duplicated locale could not be removed by the user).

The translations table (`TeamTranslationEntry`) offers only "Select" and "View / Edit" record actions. A team that duplicates or creates a locale by mistake cannot remove it. With visibility now scoped to the owning team (2026-07-16), the blast radius is smaller, but the mistaken locale still clutters the team's own list forever.

Suggested shape: a delete action visible only when the locale is editable by the current team (`is_editable`) and not `is_default`, blocked when the locale is the team's currently selected locale for the language, deleting its `LanguageString` records with it.
```

- [ ] **Step 2: Run all quality gates**

```bash
./vendor/bin/pest
./vendor/bin/phpstan analyse
./vendor/bin/pint --dirty
cd packages/filament-odk-link && composer test && composer analyse; cd ../..
```

Expected: pest PASS; phpstan no NEW errors versus the base branch (compare against `git stash` + rerun if unsure); pint may reformat — re-run pest afterwards if it changed files. Package gates: no new failures versus base.

- [ ] **Step 3: Write the change log**

`docs/change-logs/2026-07-16-translation-uploader-fixes.md` — summarise what shipped, referencing the plan (`docs/plans/2026-07-16-translation-uploader-fixes.md`) and the review (`docs/code-reviews/2026-07-16-translation-uploader-review.md`). Cover: header-resolved import column + inspector validation, per-chunk full-row validation, owner-filtered export columns, real empty template + renamed actions, sheet protection, team-scoped submit, locale visibility scoping, dead component removal, pivot typo, and the two deferred issues (with links). List the new test files.

- [ ] **Step 4: Update this plan's status**

Change the `**Status:**` line at the top of this file to `Completed — see [change log](../change-logs/2026-07-16-translation-uploader-fixes.md)`.

- [ ] **Step 5: Final commit**

```bash
git add docs/
git commit -m "docs: change log and deferred follow-ups for translation uploader fixes"
```

---

## Self-review notes

- Spec coverage: fix 1 → Task 2; fix 2 → Task 3; fix 3 (both stacked bugs) → Task 4; finding 4 (visibility only, per user decision) → Task 5; finding 5 → Tasks 2+4; finding 6 → Task 2; finding 7 → Task 4; finding 8 → Task 6; finding 9 (protection, newline, mount param, typo) → Tasks 3, 5, 6; delete-button and structured sharing explicitly deferred → Task 7; tests-before-fixes → every behavioural task runs RED before implementing.
- Type consistency: `XlsformTemplateLanguageImport` 4th arg `int $textColumnIndex` (Task 2) matches the `submit()` call in Task 4; export `owner:` named arg (Task 3) matches Task 4 call sites; action names `download_existing_{id}`/`download_empty_{id}` match between Task 4 code and tests.
- Known execution risks called out inline: Filament `callAction` resolution for schema-nested actions (fallback given in Task 4 Step 1), `language_label` seeder-name dependency (Task 1 Step 5), and package-suite pre-existing failures (Task 3 Step 5).
