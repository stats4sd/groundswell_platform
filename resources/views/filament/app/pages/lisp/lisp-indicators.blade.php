<x-filament-panels::page>
    <div class="container mx-auto xl:px-12 !mb-4">

        @php $instructions = tfile('lisp-indicators'); @endphp

        <x-instructions-sidebar>

            <x-slot:heading>{{ t("Instructions") }}</x-slot:heading>
            <x-slot:instructions>
                {!! \Illuminate\Support\Str::markdown($instructions) !!}
            </x-slot:instructions>

        </x-instructions-sidebar>
        <div class="surveyblocks pb-24 mb-32 pt-12 px-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4   h-max mb-12">
                <a wire:click="setActiveTab('local')" class="{{ $activeTab === 'local' ? 'tabbuttons' : '' }}  rounded-2xl cursor-pointer lisptabs bg-gray-100 ">
                    <x-rounded-square>
                        <x-slot:heading>Upload local indicators</x-slot:heading>
                        <x-slot:description>Upload the local indicators you identified in the LISP workshop.</x-slot:description>
                    </x-rounded-square>
                </a>
                <a wire:click="setActiveTab('match')" class="{{ $activeTab === 'match' ? 'tabbuttons' : '' }}  rounded-2xl cursor-pointer lisptabs bg-gray-100">
                    <x-rounded-square>
                        <x-slot:heading>Match with existing global indicators</x-slot:heading>
                        <x-slot:description>Browse the list of indicators already available in the HOLPA global survey, and match them to your identified local indicators.</x-slot:description>
                    </x-rounded-square>
                </a>
                <a wire:click="setActiveTab('custom')" class="{{ $activeTab === 'custom' ? 'tabbuttons' : '' }}  rounded-2xl cursor-pointer lisptabs bg-gray-100">
                    <x-rounded-square>
                        <x-slot:heading>Add custom survey questions</x-slot:heading>
                        <x-slot:description>The local indicators that are not matched to a HOLPA global indicator should be reviewed. For each indicator, you can add one or more questions to the survey to allow you to calculate the indicator.</x-slot:description>
                    </x-rounded-square>
                </a>
                <a wire:click="setActiveTab('ordering')" class="{{ $activeTab === 'ordering' ? 'tabbuttons' : '' }}  rounded-2xl cursor-pointer lisptabs bg-gray-100">
                    <x-rounded-square>
                        <x-slot:heading>Place custom questions in survey</x-slot:heading>
                        <x-slot:description>Once you have defined the questions to ask, you need to insert them into either the Household or Fieldwork survey.</x-slot:description>
                    </x-rounded-square>
                </a>
            </div>

            <!-- Content -->
            <div class="px-6">
                @if ($activeTab === 'local')

                    <livewire:lisp.upload-local-indicators/>
                @elseif ($activeTab === 'match')
                    @include('livewire.lisp.match-indicators')
                @elseif ($activeTab === 'custom')
                    <livewire:lisp.custom-module-versions/>
                @elseif ($activeTab === 'ordering')
                    <livewire:lisp.custom-module-ordering/>
                @else
                    <div class="mx-auto w-max">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-6 h-6 inline" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                        </svg>
                        <span class="ml-2 font-semibold">Select a tab above to begin </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
