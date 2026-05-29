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

    public function getTitle(): string
    {
        return t('Context: Survey Translations');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('view survey translations');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            SurveyLanguagesIndex::getUrl() => t('Survey Languages'),
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
            ->label(fn () => t('MARK AS COMPLETE'))
            ->extraAttributes(['class' => 'buttonbrown mx-4 inline-block'])
            ->visible(fn () => auth()->user()->can('maintain survey translations'))
            ->action(function () {
                if (!auth()->user()->can('maintain survey translations')) {
                    abort(403);
                }

                HelperService::getCurrentOwner()->update([
                    'languages_complete' => 1,
                ]);

                $this->dispatch('refreshPage');
            });
    }

    public function markIncompleteAction(): Action
    {
        return Action::make('markIncomplete')
            ->label(fn () => t('MARK AS INCOMPLETE'))
            ->extraAttributes(['class' => 'buttonbrown mx-4 inline-block'])
            ->visible(fn () => auth()->user()->can('maintain survey translations'))
            ->action(function () {
                if (!auth()->user()->can('maintain survey translations')) {
                    abort(403);
                }

                HelperService::getCurrentOwner()->update([
                    'languages_complete' => 0,
                ]);

                $this->dispatch('refreshPage');
            });
    }
}
