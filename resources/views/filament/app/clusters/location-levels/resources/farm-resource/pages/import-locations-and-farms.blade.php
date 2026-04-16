<x-filament-panels::page>

    <div class="container">

        <div class="flex flex-col gap-y-6">

            <x-filament-panels::form wire:submit="save">

                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getFormActions()" />

            </x-filament-panels::form>

        </div>

    </div>

</x-filament-panels::page>