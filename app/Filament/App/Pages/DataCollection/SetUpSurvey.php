<?php

namespace App\Filament\App\Pages\DataCollection;

use App\Filament\App\Pages\SurveyDashboard;
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


class SetUpSurvey extends Page implements HasActions, HasForms
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.app.pages.data-collection.set-up-survey';

    protected ?string $heading = 'Set up the survey';

    public Team $team;

    #[Url]
    public string $tab = 'xlsforms';

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            DatacollectionIndex::getUrl() => 'Data Collection',
            static::getUrl() => static::getTitle(),
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

    public function getRecord(): Team
    {
        return HelperService::getCurrentOwner();
    }

    public function markPilotCompleteAction(): Action
    {
        return Action::make('markPilotComplete')
            ->extraAttributes(['class' => 'buttona'])
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
            ->extraAttributes(['class' => 'buttonb'])
            ->label('Return to pilot testing mode')
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
