<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class DataCollectedWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Data collected';

    protected function getStats(): array
    {
        $result = [];

        // find total number of submissions for each xlsform template
        $xlsformTemplates = XlsformTemplate::all();

        foreach ($xlsformTemplates as $xlsformTemplate) {
            $total = 0;
            foreach ($xlsformTemplate->xlsforms as $xlsform) {
                foreach ($xlsform->xlsformVersions as $xlsformVersion) {
                    $total = $total + $xlsformVersion->submissions->count();
                }
            }

            array_push($result, Stat::make(new HtmlString('Submissions for Xlsform Template -<br/>'.$xlsformTemplate->title), $total));
        }

        return $result;
    }
}
