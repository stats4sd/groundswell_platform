<?php

use App\Exports\DataExport\FarmSurveyDataExport;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

// The submissions export is driven entirely by seeded Dataset rows looked up *by name*
// ('Farm Survey Data' plus its 'Crops'/'Livestock'/... children) and generic Entity /
// EntityValue rows - never by the purged App\Models\SurveyData model classes. This pins
// that: the export must still build after those models and their `entity_model` pointers
// were removed. See docs/plans/farm-crud-cutover-and-import-chaining.md.

beforeEach(function () {
    Http::fake();
    $this->team = Team::factory()->create();
});

test('submissions export builds for a team with no submissions', function () {
    $sheets = (new FarmSurveyDataExport($this->team))->sheets();

    expect($sheets)->not->toBeEmpty();

    $raw = Excel::raw(new FarmSurveyDataExport($this->team), ExcelWriter::XLSX);

    expect($raw)->not->toBeEmpty();
});
