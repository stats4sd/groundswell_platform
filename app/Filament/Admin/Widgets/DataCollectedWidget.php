<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Widgets\StatsOverviewWidget\Stat;
use App\Models\SampleFrame\Farm;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\HtmlString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class DataCollectedWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Data collected';

    protected function getStats(): array
    {
        $result = [];

        // find number of farms that completed both household form and fieldwork form
        $farmsSurveyed = Farm::where('household_form_completed', true)->where('fieldwork_form_completed', true)->count();
        // $farmsSurveyed = Role::count();

        array_push($result, Stat::make(new HtmlString('Farms surveyed'), $farmsSurveyed));

        // find total number of submissions for each xlsform template
        $xlsformTemplates = XlsFormTemplate::all();

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
