<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\ImportResource\Pages;

use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource;
use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\App\Pages\SurveyLocations\SurveyLocationsIndex;
use Filament\Resources\Pages\ViewRecord;

class ViewImport extends ViewRecord
{
    protected static string $resource = ImportResource::class;

    public function getHeading(): string
    {
        return t('Import').' #'.$this->getRecord()->getKey();
    }

    public function getBreadcrumbs(): array
    {
        $record = $this->getRecord();

        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            SurveyLocationsIndex::getUrl() => t('Survey locations'),
            ImportResource::getUrl('index') => t('Past Imports'),
            static::getUrl(['record' => $record]) => '#'.$record->getKey(),
        ];
    }
}
