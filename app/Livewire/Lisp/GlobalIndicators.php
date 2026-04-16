<?php

namespace App\Livewire\Lisp;

use App\Models\Holpa\Domain;
use App\Models\Holpa\GlobalIndicator;
use App\Models\Holpa\LocalIndicator;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Livewire\Component;

class GlobalIndicators extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?Domain $selectedDomain = null;

    public ?GlobalIndicator $selectedGlobalIndicator = null;

    public ?LocalIndicator $selectedLocalIndicator = null;

    public Team $team;

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
    }

    #[On('localIndicatorSelected')]
    public function localIndicatorSelected(?LocalIndicator $indicator): void
    {
        $this->selectedLocalIndicator = $indicator;
        $this->selectedDomain = $indicator->domain;
        $this->selectedGlobalIndicator = $indicator->globalIndicator;
        $this->resetTable();
    }

    #[On('resetGlobalIndicators')]
    public function resetGlobalIndicators(): void
    {
        $this->reset('selectedDomain');
        $this->reset('selectedLocalIndicator');
        $this->reset('selectedGlobalIndicator');
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        // When no local indicator selected show no global indicators
        if (! $this->selectedDomain) {
            return $table
                ->query(GlobalIndicator::query()->where('id', '=', 0))
                ->emptyStateHeading('Select a local indicator to see corresponding global indicators with the same theme');
        }

        // When a local indicator is selected show filtered global indicators based on theme domain
        $query = $this->selectedDomain->globalIndicators();

        return $table
            ->relationship(fn () => $query)
            ->columns([
                TextColumn::make('name')
                    ->label('')
                    ->wrap(),
            ])
            ->recordClasses(fn (GlobalIndicator $record): string => $this->selectedLocalIndicator->globalIndicator?->id === $record->id ? 'font-bold bg-lightgreen' : '')
            ->actions([
                Action::make('select_match')
                    ->button()
                    ->color('blue')
                    ->hidden(function (GlobalIndicator $record) {
                        return $this->selectedLocalIndicator?->globalIndicator?->id === $record->id || $record->localIndicators()->where('team_id', $this->team->id)->exists();
                    })
                    ->action(function (GlobalIndicator $record) {

                        $this->selectedLocalIndicator->globalIndicator()->associate($record);
                        $this->selectedLocalIndicator->save();

                        $this->dispatch('refreshLocalIndicators');

                        Notification::make()
                            ->title('Success')
                            ->body($this->selectedLocalIndicator->name.' has been matched with '.$this->selectedLocalIndicator->globalIndicator->name.'!')
                            ->success()
                            ->send();
                    }),

                Action::make('already_matched')
                    ->disabled()
                    ->label(fn (GlobalIndicator $record) => 'Matched to '.$this->team->localIndicators->where('global_indicator_id', $record->id)->first()->name)
                    ->visible(fn (GlobalIndicator $record) => $record->localIndicators()->where('team_id', $this->team->id)->exists() && $this->selectedLocalIndicator->globalIndicator?->id !== $record->id),

                Action::make('remove_match')
                    ->button()
                    ->color('orange')
                    ->hidden(function (GlobalIndicator $record) {
                        return $this->selectedLocalIndicator?->globalIndicator?->id !== $record->id;
                    })
                    ->action(function () {

                        $this->selectedLocalIndicator->globalIndicator()->dissociate();
                        $this->selectedLocalIndicator->save();

                        $this->dispatch('refreshLocalIndicators');

                        Notification::make()
                            ->title('Success')
                            ->body('Match removed!')
                            ->success()
                            ->send();
                    }),

                Action::make('already_matched')
                    ->label('Already Matched')
                    ->color('gray')
                    ->hidden(function ($record) {
                        if (! $this->selectedGlobalIndicator) {
                            return true;
                        }

                        $isAlreadyMatched = $record->localIndicators()
                            ->where('team_id', $this->selectedGlobalIndicator->team_id)
                            ->where('id', '!=', $this->selectedGlobalIndicator->id)
                            ->exists();

                        return ! $isAlreadyMatched;
                    }),
            ])
            ->paginated(false);
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\View|\Illuminate\View\View|null
    {
        return view('livewire.lisp.global-indicators');
    }
}
