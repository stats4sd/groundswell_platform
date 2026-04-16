<?php

namespace App\Filament\App\Pages\SurveyLocations;

use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\Shared\WithCompletionStatusBar;
use App\Services\HelperService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Laravel\Sail\Console\Concerns\InteractsWithDockerComposeServices;

class SurveyLocationsIndex extends Page
{

    use WithCompletionStatusBar;

    public string $completionProp = 'sampling_complete';

    protected static string $view = 'filament.app.pages.survey-locations.survey-locations-index';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Survey Locations';

    protected ?string $summary = 'Add the details of the farms you will visit, to allow the enumerators to carry out data collection.';

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
