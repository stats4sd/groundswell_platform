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

    protected static string $view = 'filament.app.clusters.location-levels.resources.location-level-resource.pages.list-location-levels';

    public function getHeading(): string
    {
        return t('Survey locations');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            SurveyLocationsIndex::getUrl() => t('Survey Locations'),
            static::getUrl() => t('Location Levels'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
