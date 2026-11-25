<div>

    {{ $this->form }}

    @if($overwriteWarnings !== [])
        <div class="my-4 rounded border border-yellow-500 bg-yellow-50 p-4 text-sm" role="alert">
            <p class="font-semibold">{{ t("Some blank cells in the uploaded files will erase translations that already exist.") }}</p>

            <ul class="my-2 list-disc pl-5">
                @foreach($overwriteWarnings as $overwriteWarning)
                    <li>{{ t(":template: :count blank cell(s) will erase an existing translation.", [':template' => $overwriteWarning['title'], ':count' => $overwriteWarning['count']]) }}</li>
                @endforeach
            </ul>

            <p>{{ t("Continue to replace the existing translations with the blank cells, or cancel to choose a different file.") }}</p>

            <div class="mt-3 flex justify-end">
                <button
                    class="buttonb"
                    wire:click="cancelOverwrite"
                    wire:loading.attr="disabled"
                    wire:target="confirmOverwrite"
                >{{ t("Go back") }}</button>

                <button
                    class="buttona"
                    wire:click="confirmOverwrite"
                    wire:loading.attr="disabled"
                    wire:target="confirmOverwrite"
                >{{ t("Continue") }}</button>
            </div>
        </div>
    @endif

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
                @disabled(! $canSave || $overwriteWarnings !== [])
            >{{ t("Submit") }}</button>
        @endcan
    </div>

</div>
