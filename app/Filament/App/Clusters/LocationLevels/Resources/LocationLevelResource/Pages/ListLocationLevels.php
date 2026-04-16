<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\Pages;

use App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource;
use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\App\Pages\SurveyLocations\SurveyLocationsIndex;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocationLevels extends ListRecords
{
    protected static string $resource = LocationLevelResource::class;

    protected ?string $heading = 'Survey locations';
    // protected ?string $subheading = 'Manage hierarchy';

    protected static string $view = 'filament.app.clusters.location-levels.resources.location-level-resource.pages.list-location-levels';

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            SurveyLocationsIndex::getUrl() => 'Survey Locations',
            static::getUrl() => static::getTitle(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
