<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// TODO: replace the placeholder project/xlsform arguments below with the real odk_projects.id
// and the three xlsforms.odk_id values before enabling this in production.
Schedule::command('app:calculate-indicators', [
    'project' => 1,
    'xlsform_1' => 'xlsform_1',
    'xlsform_2' => 'xlsform_2',
    'xlsform_3' => 'xlsform_3',
])->hourly()->withoutOverlapping();
