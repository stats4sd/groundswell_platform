<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// TODO: replace the placeholder odk_projects.id below with the real project id
// before enabling this in production.
Schedule::command('app:calculate-indicators', [
    'odk_projects.id' => 1,
])->hourly()->withoutOverlapping();
