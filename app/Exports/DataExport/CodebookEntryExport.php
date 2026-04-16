<?php

namespace App\Exports\DataExport;

use App\Models\CodeBookEntry;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class CodebookEntryExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct() {}

    public function query(): Builder
    {
        return CodeBookEntry::query();
    }

    public function map($row): array
    {
        return [
            $row['list_name'],
            $row['name'],
            $row['label_en'],
            $row['indicator_value'],
            $row['climate_pillar'],
        ];

    }

    public function headings(): array
    {
        return
            [
                'list_name',
                'name',
                'label_en',
                'indicator_value',
                'climate_pillar',
            ];
    }

    public function title(): string
    {
        return 'Codebook';
    }
}
