<?php

namespace App\Livewire\Lisp;

use App\Models\Holpa\LocalIndicator;
use App\Services\HelperService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Livewire\Component;

class LocalIndicators extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    /** @var Collection<LocalIndicator> */
    public Collection $indicators;

    public ?LocalIndicator $selectedLocalIndicator = null;

    protected $listeners = ['refreshLocalIndicators'];

    public function mount(): void
    {
        $this->getLocalIndicators();
    }

    public function getLocalIndicators(): void
    {
        $this->indicators = HelperService::getCurrentOwner()->localIndicators;
    }

    public function selectIndicator(LocalIndicator $indicator): void
    {
        $this->selectedLocalIndicator = $indicator;
        $this->dispatch('localIndicatorSelected', $this->selectedLocalIndicator);
    }

    public function resetAction(): Action
    {
        return Action::make('reset')
            ->requiresConfirmation()
            ->modalHeading('Confirm Reset')
            ->modalDescription('Are you sure you want to reset all matches? This action cannot be undone.')
            ->modalSubmitActionLabel('Yes, reset')
            ->color('orange')
            ->extraAttributes(['class' => 'py-2 px-6 hover-effect'])
            ->action(fn () => $this->resetIndicators());
    }

    public function resetIndicators(): void
    {
        foreach ($this->indicators as $indicator) {
            $indicator->globalIndicator()->dissociate();
            $indicator->save();
        }

        $this->selectedLocalIndicator = null;
        $this->dispatch('resetGlobalIndicators');

        Notification::make()
            ->title('Success')
            ->body('All matches have been removed!')
            ->success()
            ->send();
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\View|\Illuminate\View\View|null
    {
        return view('livewire.lisp.local-indicators', [
            'indicators' => $this->indicators,
        ]);
    }
}
