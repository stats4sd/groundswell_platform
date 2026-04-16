<?php

namespace App\Exports\LocalIndicatorXlsformQuestions;

use App\Models\Team;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;

class CustomIndicatorChoicesSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{

    public function __construct(public Team $team)
    {
    }

    public function collection(): Collection
    {
        $locales = $this->team->locales()->with('language')->get();

        $xlsformModuleVersionIds = $this->team->localIndicators->pluck('xlsform_module_version_id')->toArray();

        $records = collect();

        $choiceListEntries = ChoiceListEntry::whereHas('XlsformModuleVersion', fn($query) => $query->whereIn('xlsform_module_versions.id', $xlsformModuleVersionIds))
            ->with('choiceList')
            ->get();

        foreach ($choiceListEntries as $choiceListEntry) {

            $record = [];

            array_push($record, $choiceListEntry->choiceList->list_name);
            array_push($record, $choiceListEntry->name);

            foreach ($locales as $locale) {
                array_push($record, $choiceListEntry->getLanguageString('label', $locale));
            }

            array_push($record, $choiceListEntry->filter);

            $records->add($record);
        }

        return $records;
    }

    public function title(): string
    {
        return 'choices';
    }

    public function headings(): array
    {
        $locales = $this->team->locales()->with('language')->get();

        $headings = [
            'list_name',
            'name',
        ];

        foreach ($locales as $locale) {
            $headings[] = "label::$locale->odk_label";
        }

        return array_merge($headings, [
            'filter',
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        $h1 = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '5FA35A'],
            ],
        ];

        return [
            1 => $h1,
        ];
    }
}
