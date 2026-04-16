<?php

namespace App\Filament\App\Pages\Pilot;

use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\Shared\WithCompletionStatusBar;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Livewire\Attributes\Url;

class PilotIndex extends Page implements HasActions, HasForms
{

    use InteractsWithActions;
    use InteractsWithForms;

    use WithCompletionStatusBar;
    public string $completionProp = 'pilot_complete';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.app.pages.pilot.pilot-index';

    protected ?string $heading = 'Survey Testing - Pilot and Enumerator Training';

    #[Url]
    public string $tab = 'xlsforms';
    public Team $team;

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            PilotIndex::getUrl() => 'Localisation: Pilot',
        ];
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
    }

    public function markPilotCompleteAction(): Action
    {
        return Action::make('markPilotComplete')
            ->color('success')
            ->label('Switch to live data collection')
            ->action(function () {
                $this->team->pilot_complete = true;
                $this->team->save();

                $this->team->refresh();
            });
    }

    public function markPilotIncompleteAction(): Action
    {
        return Action::make('markPilotIncomplete')
            ->button()
            ->label('Return to pilot testing mode')
            ->color('warning')
            ->modalHeading('Are you sure?')
            ->modalDescription('Any data collected while the pilot is in progress will be marked as "test" data, and not included in your final dataset by default')
            ->modalSubmitActionLabel('Yes, return to pilot test')
            ->action(function () {
                $this->team->pilot_complete = false;
                $this->team->save();

                $this->team->refresh();
            });
    }

}
