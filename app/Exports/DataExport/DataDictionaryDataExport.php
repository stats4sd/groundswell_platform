<?php

namespace App\Exports\DataExport;

use App\Models\DataDictionaryEntry;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DataDictionaryDataExport implements FromQuery, ShouldAutoSize, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct() {}

    public function query(): Builder
    {
        return DataDictionaryEntry::query()
            ->where('for_indicators', 0);
    }

    public function map($row): array
    {

        $data = [];

        foreach ($this->headings() as $heading) {
            $data[$heading] = $row[$heading];
        }

        return $data;

    }

    public function headings(): array
    {
        return [
            'worksheet',
            'variable',
            'survey_section',
            'question_or_definition',
            'type',
            'code_list',
            'multiple_choice_option_label',
        ];
    }

    public function title(): string
    {
        return 'data_dictionary_data';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 30,
            'C' => 35,
            'D' => 50,
            'E' => 13,
            'F' => 20,
            'G' => 24,
        ];
    }

    /**
     * @throws Exception
     */
    public function styles(Worksheet $sheet): void
    {
        $headingStyle = ['font' => ['bold' => true]];

        $sheet->getStyle('1:1')->applyFromArray($headingStyle);
    }
}
