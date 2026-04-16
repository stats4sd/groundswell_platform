<?php

namespace App\Filament\App\Pages\SurveyLanguages;

use App\Filament\App\Pages\SurveyDashboard;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;

class SurveyTranslations extends Page
{
    protected static string $view = 'filament.app.pages.survey-languages.survey-translations';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Context: Survey Translations';

    protected $listeners = ['refreshPage' => '$refresh'];

    public Team $team;

    /** @var Collection<Language> */
    public Collection $languages;

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            SurveyLanguagesIndex::getUrl() => 'Survey Languages',
            static::getUrl() => static::getTitle(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
        $this->languages = $this->team->languages;
    }

    public function markCompleteAction(): Action
    {
        return Action::make('markComplete')
            ->label('MARK AS COMPLETE')
            ->extraAttributes(['class' => 'buttona mx-4 inline-block'])
            ->action(function () {
                HelperService::getCurrentOwner()->update([
                    'languages_complete' => 1,
                ]);

                $this->dispatch('refreshPage');
            });
    }

    public function markIncompleteAction(): Action
    {
        return Action::make('markIncomplete')
            ->label('MARK AS INCOMPLETE')
            ->extraAttributes(['class' => 'buttona mx-4 inline-block'])
            ->action(function () {
                HelperService::getCurrentOwner()->update([
                    'languages_complete' => 0,
                ]);

                $this->dispatch('refreshPage');
            });
    }
}
