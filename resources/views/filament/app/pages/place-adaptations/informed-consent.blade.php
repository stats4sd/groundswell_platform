@php $instructions = tfile('informed-consent'); @endphp

<x-filament-panels::page>

    <x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                {{ t('Save consent text') }}
            </x-filament::button>
        </div>
    </form>

</x-filament-panels::page>
