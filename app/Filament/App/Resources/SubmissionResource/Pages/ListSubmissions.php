<?php

namespace App\Filament\App\Resources\SubmissionResource\Pages;

use App\Filament\App\Pages\PlaceAdaptations\InitialPilot;
use App\Filament\App\Pages\PlaceAdaptations\PlaceAdaptationsIndex;
use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\App\Resources\SubmissionResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;

class ListSubmissions extends ListRecords
{
    protected static string $resource = SubmissionResource::class;

    protected ?string $heading = 'Test Submissions';

    protected static string $view = 'filament.app.resources.submission-resource.pages.view-submission';

    /** @var Collection<SurveyRow> */
    public Collection $surveyRows;

    /** @var Collection<Collection> */
    public Collection $surveyRowData;

    public function mount(): void
    {
        parent::mount();

        // a quick temporary workaround to avoid error occurred
        // TODO: find actual data for surveyRows and surveyRowData
        $this->surveyRows = collect();
        $this->surveyRowData = collect();
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            PlaceAdaptationsIndex::getUrl() => 'Place Adaptations',
            InitialPilot::getUrl() => 'Initial Pilot',
            static::getUrl() => static::getTitle(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
