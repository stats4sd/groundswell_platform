<div class="{{ $expanded ? '' : '' }}">
    <div class="py-6 md:px-6 border-b border-gray-300 sm:flex justify-between items-center md:space-x-6 ">
        <div class="sm:w-2/3 md:flex ">
            <h6 class="w-full md:w-1/2 lg:w-1/4 ">{{ $language->language_label }}</h6>
            <h5 class="w-full mt-0 md:w-1/2 lg:w-3/4 md:flex  md:ml-8 font-medium capitalize">
                <span class="mr-2">Selected Translation: </span>
                <span class="{{ $selectedLocale ? 'text-green' : 'text-dark-orange' }}">{{ $selectedLocale ? $selectedLocale->languageLabel : 'none' }}</span>
            </h5>

        </div>
        <div class="md:self-end  md:w-1/3 md:flex justify-end mt-5 sm:mt-0">

            <button class=" text-nowrap flex text-black  items-center !text-sm justify-between " wire:click="$toggle('expanded')">
                Select Translation
                @if($expanded)
                <x-heroicon-o-chevron-up class="h-6 ml-4 font-bold text-lg " />
                @else
                <x-heroicon-o-chevron-down class="h-6 ml-4 font-bold text-lg " />
                @endif
            </button>
        </div>
    </div>

    <div class="px-6 pb-6 border-b-2 border-gray-300 transition ease-in-out delay-150 {{ $expanded ? 'visible' : 'hidden' }}">
        {{ $this->table }}
    </div>
</div>