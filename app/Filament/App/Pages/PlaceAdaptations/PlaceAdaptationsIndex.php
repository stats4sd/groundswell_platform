<?php

namespace App\Filament\App\Pages\PlaceAdaptations;

use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\Shared\WithCompletionStatusBar;
use App\Services\HelperService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class PlaceAdaptationsIndex extends Page
{
    use WithCompletionStatusBar;

    public string $completionProp = 'pba_complete';

    protected static string $view = 'filament.app.pages.place-adaptations.place-adaptations-index';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Localisation: Place-based adaptations';

    protected ?string $summary = 'Customise details for questions and answer options to ensure the survey is relevant and suitable for use in the intended location.';

    protected $listeners = ['refreshPage' => '$refresh'];

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            static::getUrl() => static::getTitle(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
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
}
