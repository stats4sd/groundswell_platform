<?php

namespace App\Filament\App\Pages\DataCollection;

use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\Shared\WithCompletionStatusBar;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\View\View;

class DataCollectionIndex extends Page
{
    use WithCompletionStatusBar;
    public string $completionProp = 'data_collection_complete';

    protected static string $view = 'filament.app.pages.data-collection.data-collection-index';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Data Collection';

    protected ?string $summary = 'View and manage the survey and incoming data.';

    public Team $team;

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            static::getUrl() => static::getTitle(),
        ];
    }

    public function getHeader(): ?View
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
