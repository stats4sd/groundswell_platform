<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// TODO: replace the placeholder teams.id and odk_central_project_id below
// with the real values before enabling this in production.
Schedule::command('app:calculate-indicators', [
    'teams.id' => 1,
    'odk_central_project_id' => 1,
])->hourly()->withoutOverlapping();
