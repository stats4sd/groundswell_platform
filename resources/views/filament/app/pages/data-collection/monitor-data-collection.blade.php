<x-filament-panels::page class="px-10 h-full">
    <x-instructions-sidebar>
        <x-slot:heading>Instructions</x-slot:heading>
        <x-slot:instructions>
            <div class="mx-12 mb-4">
                <h5>Monitor data collection</h5>
                <p class="my-2">
                    This page lets you see incoming data. You can track progress and review submissions for quality assurance purposes.
                </p>
                <p class="my-2">
                    At the top of the page, you will see a general summary of the data that has been collected; this includes the number of submissions for each form and number of farms surveyed.
                    Beneath that, you can browse the submissions. Use the tabs to view submissions by locations at different levels, or to simply view all of them.
                </p>
                <p class="my-2">
                    There is also an option to download the raw data from submissions. Note that this will be unprocessed, and will not include calculated indicators; to obtain survey data ready for analysis, use the "data analysis" section.
                </p>
                <p class="my-2">
                    If you find you need to correct an error in the data, you can directly edit a submission. This should be used sparingly, only where it has been confirmed with an enumerator that something was inputted incorrectly.
                </p>
            </div>
        </x-slot:instructions>
    </x-instructions-sidebar>
    <div class="container mx-auto xl:px-12">
        <div class="surveyblocks p-8 md:p-16 results-summary">
            @if ($team->locationLevels->where('has_farms', true)->count() === 0 || $team->farms()->count() === 0)
                <div class="bg-red-500 text-white p-4">
                    It looks like your team has not yet added locations and farms to conduct the survey. Please make sure you do so before proceeding.
                </div>
            @else

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
                    <x-filament::section heading="Data Collected">
                        <div class="grid grid-cols-2">
                            <div>Household Submissions:</div>
                            <div class="font-semibold">{{ $householdSubmissionCount }}</div>
                            <div>Fieldwork Submissions:</div>
                            <div class="font-semibold">{{ $fieldworkSubmissionCount }}</div>
                        </div>
                    </x-filament::section>

                    <x-filament::section heading="Summary">
                        <div class="grid grid-cols-2">
                            <div>Farms Fully Surveyed:</div>
                            <div class="font-semibold">{{ $completeFarmCount }}</div>
                            <div>Non-consenting Farms:</div>
                            <div class="font-semibold">{{ $nonConsentingFarmCount }}</div>
                        </div>
                    </x-filament::section>

                </div>
                {{-- Possible downlaod button for data --}}
                <div class="mb-8 ">
                    <h3>Download raw data</h3>
                    <p class="mt-2 mb-6">
                        If needed, for example for quality assurance monitoring, you may download the raw data from submissions. Note that this will be unprocessed, and will not include calculated indicators; to obtain survey data ready for analysis, use the "data analysis" section.
                    </p>
                    <x-download-section :url="url('#')">
                        <x-slot:heading>Raw survey submission data</x-slot:heading>
                        <x-slot:description>Form response data for all survey submissions that have been received.</x-slot:description>
                        <x-slot:buttonLabel>Download .csv</x-slot:buttonLabel>
                    </x-download-section>

                </div>

                <h3>Submissions By Location</h3>
                <div class="w-min mx-auto py-4">
                    <x-filament::tabs>

                        @foreach ($team->locationLevels as $level)
                            <x-filament::tabs.item :active="$level->id === $this->locationLevelId" wire:click="showTable('location_level', {{$level->id}})" :key="'location-level-'.$level->id.'-tab'" class="cursor-pointer">
                                {{ \Illuminate\Support\Str::plural($level->name) }}
                            </x-filament::tabs.item>
                        @endforeach

                        <x-filament::tabs.item :active="$table==='farms'" wire:click="showTable('farms')" :key="'farms-tab'" class="cursor-pointer">
                            Farms
                        </x-filament::tabs.item>
                        <x-filament::tabs.item :active="$table==='submissions'" wire:click="showTable('submissions')" :key="'submissions-tab'" class="cursor-pointer">
                            Submissions
                        </x-filament::tabs.item>
                    </x-filament::tabs>
                </div>
                @foreach($team->locationLevels as $level)
                    <livewire:data-collection.data-collection-by-location :location-level-id="$level->id" :visible="$table === 'location_level' && $locationLevelId === $level->id" :key="'location-level-'.$level->id"/>
                @endforeach

                <livewire:data-collection.data-collection-by-farm :visible="$table === 'farms'" :team="$team"/>
                <livewire:submissions-table-view :test="false" :visible="$table === 'submissions'" :team="$team"/>

            @endif
        </div>
    </div>
</x-filament-panels::page>
