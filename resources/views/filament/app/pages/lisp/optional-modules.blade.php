<x-filament-panels::page class="px-12 h-full">
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    <div class="pb-24">
        {{ $this->table }}
    </div>

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
