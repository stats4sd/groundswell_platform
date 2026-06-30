<?php

namespace App\Filament\App\Pages\Pilot;

use Filament\Support\Enums\Width;
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
use Livewire\Attributes\Url;

class PilotIndex extends Page implements HasActions, HasForms
{

    use InteractsWithActions;
    use InteractsWithForms;

    use WithCompletionStatusBar;
    public string $completionProp = 'pilot_complete';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.app.pages.pilot.pilot-index';

    public static function canAccess(): bool
    {
        return auth()->user()->can('view pilot');
    }

    #[Url]
    public string $tab = 'xlsforms';
    public Team $team;

    public function getTitle(): string
    {
        return t('Survey Testing - Pilot and Enumerator Training');
    }

    public function getHeading(): string
    {
        return t('Survey Testing - Pilot and Enumerator Training');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            PilotIndex::getUrl() => t('Localisation: Pilot'),
        ];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
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
            ->label(fn () => t('Switch to live data collection'))
            ->visible(fn () => auth()->user()->can('maintain pilot'))
            ->action(function () {
                if (!auth()->user()->can('maintain pilot')) {
                    abort(403);
                }

                $this->team->pilot_complete = true;
                $this->team->save();

                $this->team->refresh();
            });
    }

    public function markPilotIncompleteAction(): Action
    {
        return Action::make('markPilotIncomplete')
            ->button()
            ->label(fn () => t('Return to pilot testing mode'))
            ->color('warning')
            ->modalHeading(fn () => t('Are you sure?'))
            ->modalDescription(fn () => t('Any data collected while the pilot is in progress will be marked as "test" data, and not included in your final dataset by default'))
            ->modalSubmitActionLabel(fn () => t('Yes, return to pilot test'))
            ->visible(fn () => auth()->user()->can('maintain pilot'))
            ->action(function () {
                if (!auth()->user()->can('maintain pilot')) {
                    abort(403);
                }

                $this->team->pilot_complete = false;
                $this->team->save();

                $this->team->refresh();
            });
    }

}
