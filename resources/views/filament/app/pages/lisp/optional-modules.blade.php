<x-filament-panels::page class="px-12 h-full">

    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if ($this->data['xlsform_id'] ?? null)
        <div class="grid grid-cols-2 gap-6">

            {{-- LEFT: Available optional modules --}}
            <div>
                <span class="text-base font-semibold">Available Optional Modules</span>
                <p class="text-sm text-gray-500 mb-3 mt-1">Drag a module into the right column to add it to the survey.</p>

                <div x-data x-sortable-source
                     class="space-y-2 overflow-y-auto max-h-[60vh] p-3 border border-dashed border-gray-300 rounded-lg bg-gray-50">
                    @forelse ($this->availableModules as $module)
                        <x-filament::section
                            :heading="$module->name"
                            x-sortable-item="{{ $module->id }}"
                            class="cursor-grab rounded border border-gray-300 bg-white"
                            collapsible
                            collapsed
                        >
                            @foreach ($module->surveyRows as $surveyRow)
                                <div class="flex items-center gap-2 text-sm py-0.5">
                                    <span>{{ $surveyRow->name }}</span>
                                    <span class="text-gray-400">({{ $surveyRow->type }})</span>
                                </div>
                            @endforeach
                        </x-filament::section>
                    @empty
                        <p class="text-sm text-gray-400 italic py-4 text-center">All available modules have been added to this survey.</p>
                    @endforelse
                </div>
            </div>

            {{-- RIGHT: Current xlsform modules --}}
            <div>
                <span class="text-base font-semibold">Survey Form Modules</span>
                <p class="text-sm text-gray-500 mb-3 mt-1">Grey modules are fixed template modules. Drag optional modules to reorder them.</p>

                <div x-data x-sortable-target
                     x-on:sorted="$wire.updateOrder($event.detail)"
                     class="space-y-2 overflow-y-auto max-h-[60vh] p-3 border border-gray-300 rounded-lg bg-gray-50">
                    @forelse ($this->xlsformModules as $module)
                        <x-filament::section
                            :heading="$module->name"
                            x-sortable-item="{{ $module->id }}"
                            collapsible
                            collapsed
                            @class([
                                'rounded border my-1',
                                'bg-gray-200 border-gray-400 global-module cursor-not-allowed opacity-75' => $module->xlsform_module_id !== null,
                                'bg-white border-slate-400 cursor-grab' => $module->xlsform_module_id === null,
                            ])
                        >
                            @foreach ($module->surveyRows as $surveyRow)
                                <div class="flex items-center gap-2 text-sm py-0.5">
                                    <span>{{ $surveyRow->name }}</span>
                                    <span class="text-gray-400">({{ $surveyRow->type }})</span>
                                </div>
                            @endforeach

                            @if ($module->xlsform_module_id === null)
                                <div class="flex justify-end mt-2">
                                    <x-filament::button
                                        wire:click="removeModule({{ $module->id }})"
                                        color="danger"
                                        size="sm"
                                    >
                                        Remove
                                    </x-filament::button>
                                </div>
                            @endif
                        </x-filament::section>
                    @empty
                        <p class="text-sm text-gray-400 italic py-4 text-center">No modules in this form yet.</p>
                    @endforelse
                </div>
            </div>

        </div>

        <div class="w-full text-center mt-4 pb-24">
            <x-filament::button
                wire:click="confirmOrdering"
                color="success"
            >
                Confirm Ordering
            </x-filament::button>
        </div>
    @else
        <div class="py-8 pb-24 text-center text-gray-400 italic">
            Please select a survey form above.
        </div>
    @endif

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
