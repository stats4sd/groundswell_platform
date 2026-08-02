<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\ImportResource\Pages;

use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource;
use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\App\Pages\SurveyLocations\SurveyLocationsIndex;
use Filament\Resources\Pages\ListRecords;

class ListImports extends ListRecords
{
    protected static string $resource = ImportResource::class;

    public function getHeading(): string
    {
        return t('Past Imports');
    }

    public function getSubheading(): string
    {
        return t('Every location and farm spreadsheet this team has uploaded, and what happened to it.');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            SurveyLocationsIndex::getUrl() => t('Survey locations'),
            static::getUrl() => t('Past Imports'),
        ];
    }
}
