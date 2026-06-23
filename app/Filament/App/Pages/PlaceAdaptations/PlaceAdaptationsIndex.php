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

    public static function canAccess(): bool
    {
        return auth()->user()->can('view place-based adaptations');
    }

    protected $listeners = ['refreshPage' => '$refresh'];

    public function getTitle(): string
    {
        return t('Localisation: Place-based adaptations');
    }

    public function getSummary(): string
    {
        return t('Customise details for questions and answer options to ensure the survey is relevant and suitable for use in the intended location.');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
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
            'summary' => $this->getSummary(),
        ]);
    }
}
