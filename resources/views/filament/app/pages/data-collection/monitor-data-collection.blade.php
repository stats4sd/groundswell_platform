@php $instructions = tfile('data-collection-monitor'); @endphp

<x-filament-panels::page class="px-10 h-full">
    <x-instructions-sidebar>
        <x-slot:heading>{{ t('Instructions') }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>
    <div class="mx-0 xl:px-4" style="margin-top:-50px">
        <div class="surveyblocks">

            <x-shiny-loader::shiny-iframe app="groundswell_monitor"
                :post-data="$shinyData->toArray()" />
        </div>

    </div>

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
