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
            <x-shiny-loader::shiny-iframe shiny-app-url="{{ config('shiny-loader.monitoring-app-url') }}" :post-data="['foo' => 'bar']" />
        </div>

    </div>
</x-filament-panels::page>
