<?php

namespace App\Imports;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
