<?php

namespace App\Exports\LocalIndicatorXlsformQuestions;

use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;

class CustomIndicatorSurveySheet implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function __construct(public Team $team) {}

    public function collection(): Enumerable|Collection
    {
        $locales = $this->team->locales()->with('language')->get();

        // find related xlsform module version Ids
        $xlsformModuleVersionIds = $this->team->localIndicators->pluck('xlsform_module_version_id')->toArray();

        // create an empty collection for one excel sheet
        $records = collect();

        // find related custom questions
        $surveyRows = SurveyRow::whereIn('xlsform_module_version_id', $xlsformModuleVersionIds)
            ->get();

        // add custom questions to collection one by one
        foreach ($surveyRows as $surveyRow) {

            // create a new record for one row
            $record = [];

            // find local indicator name
            $localIndicator = $this->team->localIndicators->where('xlsform_module_version_id', $surveyRow->xlsform_module_version_id)->first();
            array_push($record, $localIndicator->name);

            array_push($record, $surveyRow->type_and_choice_list);
            array_push($record, $surveyRow->name);

            foreach ($locales as $locale) {
                array_push($record, $surveyRow->getLanguageString('label', $locale));
                array_push($record, $surveyRow->getLanguageString('hint', $locale));
            }

            array_push($record, $surveyRow->required);

            foreach ($locales as $locale) {
                array_push($record, $surveyRow->getLanguageString('required_message', $locale));
            }

            array_push($record, $surveyRow->calculation);
            array_push($record, $surveyRow->relevant);
            array_push($record, $surveyRow->appearance);
            array_push($record, $surveyRow->constraint);

            foreach ($locales as $locale) {
                array_push($record, $surveyRow->getLanguageString('constraint_message', $locale));
            }

            array_push($record, $surveyRow->choice_filter);
            array_push($record, $surveyRow->repeat_count);
            array_push($record, $surveyRow->default);
            array_push($record, $surveyRow->note);
            array_push($record, $surveyRow->trigger);

            foreach ($locales as $locale) {
                array_push($record, $surveyRow->getLanguageString('mediaimage', $locale));
            }

            $records->add($record);
        }

        return $records;
    }

    public function title(): string
    {
        return 'survey';
    }

    public function headings(): array
    {
        $locales = $this->team->locales()->with('language')->get();

        $headings = [
            'indicator',
            'type',
            'name',
        ];

        foreach ($locales as $locale) {
            $headings[] = "label::$locale->odk_label";
            $headings[] = "hint::$locale->odk_label";
        }

        $headings = array_merge($headings, [
            'required',
        ]);

        foreach ($locales as $locale) {
            $headings[] = "required_message::$locale->odk_label";
        }

        $headings = array_merge($headings, [
            'calculation',
            'relevant',
            'appearance',
            'constraint',
        ]);

        foreach ($locales as $locale) {
            $headings[] = "constraint_message::$locale->odk_label";
        }

        $headings = array_merge($headings, [
            'choice_filter',
            'repeat_count',
            'default',
            'note',
            'trigger',
        ]);

        foreach ($locales as $locale) {
            $headings[] = "media::image::$locale->odk_label";
        }

        return $headings;
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $count = $this->team->localIndicators()
                    ->whereDoesntHave('globalIndicator')->count();

                $sheet = $event->sheet->getDelegate();

                $indicatorLookupList = new DataValidation;
                $indicatorLookupList->setType(DataValidation::TYPE_LIST);
                $indicatorLookupList->setErrorStyle(DataValidation::STYLE_STOP);
                $indicatorLookupList->setAllowBlank(false);
                $indicatorLookupList->setShowInputMessage(true);
                $indicatorLookupList->setShowErrorMessage(true);
                $indicatorLookupList->setShowDropDown(true);
                $indicatorLookupList->setErrorTitle('Input error');
                $indicatorLookupList->setError('Please select an indicator from the list.');
                $indicatorLookupList->setPromptTitle('Pick an indicator');
                $indicatorLookupList->setFormula1('\'indicators\'!$B$3:$B$' . $count + 3);

                $sheet->setDataValidation('A:A', $indicatorLookupList);
            },
        ];
    }
}
