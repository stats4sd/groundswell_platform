<div>

    <div class="text-base font-semibold ">
        Local indicators
    </div>

    <div class="pt-2 pb-4 text-sm font-normal h-20">
        Select an indicator to start looking for matches.
    </div>

    <div class=" rounded-xl">
        @forelse ($indicators as $indicator)
            <div class=" p-4 lispboxbase cursor-pointer {{ $selectedLocalIndicator?->id === $indicator->id ? ' lispboxselected ' : 'lispboxinactive text-current' }}"
                 wire:click="selectIndicator({{ $indicator }})">
                @if ($indicator->global_indicator_id)
                    <div class="flex items-center   {{ $selectedLocalIndicator?->id === $indicator->id ? ' text-white': 'text-green' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5  mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span class="text-sm  font-semibold">MATCHED</span>
                    </div>
                @endif
                <p class="ps-6 ml-1">
                    {{ $indicator->name }}
                </p>
            </div>
        @empty
            <div>No local indicators have been uploaded.</div>
        @endforelse
    </div>

    <div class="flex justify-center gap-4 py-4">
        {{ $this->resetAction }}
    </div>

    <x-filament-actions::modals />

</div>
