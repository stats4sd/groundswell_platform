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
            <x-shiny-loader::shiny-iframe shiny-app-url="http://127.0.0.1:7009" :post-data="['foo' => 'bar']" />
        </div>
    </div>

    <!-- Footer -->
    <!-- Footer -->
    <x-complete-section-status-bar :completion-prop="$completionProp">
        <x-slot:markCompleteAction>
            {{ $this->markCompleteAction() }}
        </x-slot:markCompleteAction>
        <x-slot:markIncompleteAction>
            {{ $this->markIncompleteAction() }}
        </x-slot:markIncompleteAction>
    </x-complete-section-status-bar>
</x-filament-panels::page>
