@php $instructions = tfile('farm-list'); @endphp

<x-filament-widgets::widget>
<x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>
</x-filament-widgets::widget>
