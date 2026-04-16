<div>

    <div class="text-lg font-bold text-green pb-4 uppercase">
        Place custom questions in survey
    </div>

    <div class="text-black pb-4 mb-9">
        To add your custom questions into the survey, drag and drop each item into the correct place in the list on the right. Updates to the module ordering will be saved automatically. Once you have finished arranging your custom questions, click "Confirm Ordering" to finalize the order and add your custom questions to the surveys for testing. If you need to reset the order at any time, click "Reset Ordering" to revert to the default ordering (this will remove your local indicator modules from the survey forms).
    </div>
    <div class="grid grid-cols-2 gap-4">

        <div
            class="space-y-2 py-4 pr-2"
            x-data
            x-sortable-source

        >

            <span class="text-base font-semibold ">Local Indicator Questions</span>
            <br/>

            <div x-data
                 x-data
                 x-sortable-source
                 class="!mb-8 overflow-y-scroll max-h-[40vh] inset-shadow-sm/50 p-4"

            >

                @foreach($localIndicators as $indicator)

                    <x-filament::section
                        :heading="$indicator->name"
                        x-sortable-item="{{ $indicator->xlsformModuleVersion->id }}"
                        class="draggable indicator rounded-none border my-2"
                        collapsible
                        collapsed
                    >

                        @foreach($indicator->xlsformModuleVersion->surveyRows as $surveyRow)

                            <div class="flex items-center indicator_questions">{{ $surveyRow->name }}
                                <br>( {{ $surveyRow->type }} )
                            </div>

                        @endforeach


                    </x-filament::section>

                @endforeach
            </div>
        </div>

        <div
            class="p-4 py-4 pl-2 space-y-2"
        >
            @foreach($xlsforms as $xlsform)

                <span class="text-base font-semibold  ">{{ $xlsform->title }}</span>
                <span>( {{ $xlsform->xlsformModuleVersions->count() }} modules )</span>
                <br/>
                @if($xlsform->xlsformModuleVersions->count() > 111)
                    <div class="text-sm italic text-gray-600 pb-2">
                        (Tip: This form has many modules. Scroll the list below to see them all.)
                    </div>
                @endif

                <div x-data
                     x-sortable-target
                     x-on:sorted="$wire.updateOrder($event.detail, '{{ $xlsform->id }}')"
                     class="!mb-8 overflow-y-scroll max-h-[40vh] inset-shadow-sm/50 p-4"

                >

                    @foreach($xlsform->xlsformModuleVersions as $xlsformModuleVersion)
                        <x-filament::section
                            :heading="$xlsformModuleVersion->name"
                            collapsible
                            collapsed
                            x-sortable-item="{{ $xlsformModuleVersion->id }}"
                            class=""
                            @class([
                            "rounded-none border indicator my-2",
                            "bg-gray-200 border-gray-500 global-module indicator" => $xlsformModuleVersion->owner?->id !== $team->id,
                            'bg-slate-200 border-slate-900 indicator draggable my-2' => $xlsformModuleVersion->owner?->id === $team->id,
                            ])
                        >

                            @foreach($xlsformModuleVersion->surveyRows as $surveyRow)
                                <div class="flex items-center indicator_questions">{{ $surveyRow->name }}
                                    <br>( {{ $surveyRow->type }} )
                                </div>
                            @endforeach

                        </x-filament::section>

                    @endforeach
                </div>
            @endforeach
        </div>

    </div>
    <div class="w-100 text-center">
        <x-filament::button
            wire:click="resetOrdering"
            class="mt-4"
            color="warning"
        >
            Reset Ordering
        </x-filament::button>

        <x-filament::button
            wire:click="confirmOrdering"
            class="mt-4"
            color="success"
        >
            Confirm Ordering
        </x-filament::button>
    </div>
</div>
