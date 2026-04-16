<?php

namespace App\Exports\LocalIndicatorXlsformQuestions;

use App\Models\Team;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CustomIndicatorExport implements WithMultipleSheets
{
    protected Team $team;

    public function __construct($team)
    {
        $this->team = $team;
    }

    public function sheets(): array
    {
        return [
            new CustomIndicatorSurveySheet($this->team),
            new CustomIndicatorChoicesSheet($this->team),
            new CustomIndicatorLookupSheet($this->team),
        ];
    }
}
