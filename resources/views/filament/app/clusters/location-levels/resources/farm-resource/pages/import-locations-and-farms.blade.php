<x-filament-panels::page>

    <div class="container">

        <div class="flex flex-col gap-y-6">

            <form wire:submit="save">

                {{ $this->form }}

                <div class="fi-form-actions mt-6">
                    <div class="flex flex-wrap items-center gap-3">
                        @foreach ($this->getFormActions() as $action)
                            {{ $action }}
                        @endforeach
                    </div>
                </div>

            </form>

        </div>

    </div>

</x-filament-panels::page>