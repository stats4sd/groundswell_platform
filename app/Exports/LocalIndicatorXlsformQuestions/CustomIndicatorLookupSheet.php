<?php

namespace App\Exports\LocalIndicatorXlsformQuestions;

use App\Models\Team;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CustomIndicatorLookupSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public Team $team) {}

    public function headings(): array
    {
        return [
            ['NOTE: please do not edit this sheet. It was created from the HOLPA database and allows you to match questions you add on the "survey" sheet with your locally defined indicators.'],
            ['indicator_id', 'name'],
        ];
    }

    public function collection()
    {
        return $this->team->localIndicators()
            ->whereDoesntHave('globalIndicator')
            ->get()
            ->map(fn ($indicator) => [
                'id' => $indicator->id,
                'name' => $indicator->name,
            ]);
    }

    public function title(): string
    {
        return 'indicators';
    }
}
