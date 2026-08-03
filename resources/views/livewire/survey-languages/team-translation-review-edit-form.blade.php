<div>

    {{ $this->form }}

    <div class="my-4 flex justify-end">
        <button
            class="buttonb disabled:bg-gray-200! disabled:text-gray-600! disabled:cursor-not-allowed"
            wire:click="cancel"
            wire:loading.attr="disabled"
            wire:target="duplicate,submit"
        >{{ t("Cancel") }}</button>

        @can('maintain survey translations')
            <button
                class="buttona inline-flex items-center gap-2 disabled:bg-gray-200! disabled:text-gray-600! disabled:cursor-not-allowed"
                wire:click="duplicate"
                wire:loading.attr="disabled"
                wire:target="duplicate,submit"
            >
                <x-filament::loading-indicator
                    class="h-4! w-4!"
                    wire:loading
                    wire:target="duplicate"
                />
                {{ t("Duplicate") }}
            </button>

            <button
                class="buttona disabled:bg-gray-200! disabled:text-gray-600! disabled:cursor-not-allowed"
                wire:click="submit"
                wire:loading.attr="disabled"
                wire:target="duplicate,submit"
                @disabled(! $canSave)
            >{{ t("Submit") }}</button>
        @endcan
    </div>

</div>
