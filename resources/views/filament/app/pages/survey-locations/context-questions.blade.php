@php $instructions = tfile('context-questions'); @endphp

<x-filament-panels::page>

    <x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>


{{ $this->table }}



</x-filament-panels::page>
