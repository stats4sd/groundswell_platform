<?php

use App\Filament\App\Pages\SurveyDashboard;

$surveyDashboardUrl = SurveyDashboard::getUrl();

$instructions = tfile('data-analysis');
?>

<x-filament-panels::page class="h-full">

    <x-instructions-sidebar>
        <x-slot:heading>{{ t('Instructions') }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>
    <div class="mx-0 xl:px-4" style="margin-top:-50px">
        <div class="surveyblocks">
            <x-shiny-loader::shiny-iframe shiny-app-url="{{ config('shiny-loader.analysis-app-url') }}" :post-data="['foo' => 'bar']" />
        </div>
    </div>

</x-filament-panels::page>
