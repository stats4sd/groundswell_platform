<?php

namespace App\Filament\App\Pages\SurveyLanguages;

use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\Shared\WithCompletionStatusBar;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class SurveyLanguagesIndex extends Page
{
    use WithCompletionStatusBar;
    public string $completionProp = 'languages_complete';

    protected static string $view = 'filament.app.pages.survey-languages.survey-languages-index';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Survey Languages';

    protected ?string $summary = 'Select the country, language or languages in which you plan to run the survey and either select an existing translation of the tool or create your own using a provided template.';

    protected $listeners = ['refreshPage' => '$refresh'];

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            static::getUrl() => static::getTitle(),
        ];
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('components.small-header', [
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'actions' => $this->getHeaderActions(),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'summary' => $this->summary,
        ]);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

}
