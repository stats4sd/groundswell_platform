<?php

namespace App\Imports;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class TranslationUploadInspector
{
    public Collection $headings;

    public Collection $rows;

    public function __construct(UploadedFile|string $file)
    {
        // Read the file directly rather than via Excel::toCollection: the Excel facade caches a
        // shared Reader whose spreadsheet property gets unset during garbage collection, which
        // then breaks serialization of the queued import chain that runs right after inspection.
        $path = is_string($file) ? $file : $file->getRealPath();

        $sheetRows = collect(IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false))
            ->map(fn (array $row): Collection => collect($row));

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

    // Cells that would erase a translation: the text cell is blank while the locale already holds text for that entry and string type.
    public function blankOverExistingCount(Locale $locale, XlsformTemplate $template): int
    {
        $textColumnIndex = $this->textColumnIndex($locale);

        if ($textColumnIndex === null) {
            return 0;
        }

        $groupEndRowIds = $template->surveyRows()->groupEnds()->pluck('survey_rows.id');
        $languageStringTypeIds = LanguageStringType::pluck('id', 'name');

        $blankCells = $this->rows
            ->filter(fn (Collection $row) => filled($row[2] ?? null) && filled($row[3] ?? null))
            ->filter(fn (Collection $row) => in_array($row[0] ?? null, ['survey', 'choices'], true))
            ->filter(fn (Collection $row) => trim((string) ($row[$textColumnIndex] ?? '')) === '')
            ->reject(fn (Collection $row) => $row[0] === 'survey' && $groupEndRowIds->contains((int) $row[2]))
            ->filter(fn (Collection $row) => $languageStringTypeIds->has($row[4] ?? null))
            ->map(fn (Collection $row) => [
                'class' => $row[0] === 'survey' ? SurveyRow::class : ChoiceListEntry::class,
                'entryId' => (int) $row[2],
                'typeId' => (int) $languageStringTypeIds[$row[4]],
            ])
            ->unique(fn (array $cell) => "{$cell['class']}|{$cell['entryId']}|{$cell['typeId']}");

        return $blankCells
            ->groupBy('class')
            ->sum(fn (Collection $cells, string $class) => $cells
                ->pluck('entryId')
                ->unique()
                ->chunk(500)
                ->sum(fn (Collection $entryIds) => $this->existingTextCount($locale, $class, $entryIds, $cells)));
    }

    /**
     * @param  Collection<int, int>  $entryIds
     * @param  Collection<int, array{class: string, entryId: int, typeId: int}>  $cells
     */
    private function existingTextCount(Locale $locale, string $class, Collection $entryIds, Collection $cells): int
    {
        $existingKeys = LanguageString::query()
            ->where('locale_id', $locale->id)
            ->where('linked_entry_type', $class)
            ->whereIn('linked_entry_id', $entryIds->values())
            ->whereRaw("TRIM(language_strings.text) <> ''")
            ->get(['linked_entry_id', 'language_string_type_id'])
            ->map(fn (LanguageString $languageString) => "{$languageString->linked_entry_id}|{$languageString->language_string_type_id}")
            ->flip();

        return $cells
            ->filter(fn (array $cell) => $entryIds->contains($cell['entryId']))
            ->filter(fn (array $cell) => $existingKeys->has("{$cell['entryId']}|{$cell['typeId']}"))
            ->count();
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
