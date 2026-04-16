<div class="{{ $expanded ? '' : '' }} ">
    <div class="py-6 px-8 border-b border-gray-300 flex justify-between items-center space-x-8">
        <div class="w-full flex justify-start">
        <h6 class="w-full ">{{ $localIndicator->name }}</h6>

        </div>
        <div class="self-end ">

            <button class="text-black text-nowrap flex items-center justify-between" wire:click="$toggle('expanded')">
                @if($expanded)
                <x-heroicon-o-chevron-up class="h-6 font-bold text-lg pe-4" />Hide Questions
                @else
                <x-heroicon-o-chevron-down class="h-6 font-bold text-lg pe-4" />Show Questions
                @endif
            </button>
        </div>
    </div>

    <div class="p-4 transition ease-in-out delay-150 {{ $expanded ? 'visible' : 'hidden' }}">
        {{ $this->table }}
    </div>

</div>
