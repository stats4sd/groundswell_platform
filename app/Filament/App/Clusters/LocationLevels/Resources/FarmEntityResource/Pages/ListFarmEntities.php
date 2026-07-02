<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages;

use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource;
use App\Services\HelperService;
use App\Services\OdkFarmEntityService;
use Filament\Resources\Pages\ListRecords;

class ListFarmEntities extends ListRecords
{
    protected static string $resource = FarmEntityResource::class;

    public function getHeading(): string
    {
        return t('Farms (ODK Entities - preview)');
    }

    // Read-through refresh: pull the current state from ODK Central's live feed once per
    // page load, rather than trusting whatever is already in the local EntityValue rows.
    public function mount(): void
    {
        parent::mount();

        app(OdkFarmEntityService::class)->refreshFromCentral(HelperService::getCurrentOwner());
    }
}
