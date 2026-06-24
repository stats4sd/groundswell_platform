<?php

    $instructions = tfile('location-levels');

?>

<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>

<x-instructions-sidebar>
        <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
        <x-slot:instructions>
            {!! \Illuminate\Support\Str::markdown($instructions) !!}
        </x-slot:instructions>
    </x-instructions-sidebar>

    <div class="container">

        <div class="flex flex-col gap-y-6">
            {{ $this->content }}
        </div>

    </div>
</x-filament-panels::page>
