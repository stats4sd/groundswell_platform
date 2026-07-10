<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages;

use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource;
use App\Services\HelperService;
use App\Services\OdkFarmEntityService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFarmEntity extends CreateRecord
{
    protected static string $resource = FarmEntityResource::class;

    // Overridden because creating a farm means pushing to ODK Central and creating the
    // linked Entity/EntityValue rows, not a plain `FarmEntity::create($data)`.
    protected function handleRecordCreation(array $data): Model
    {
        return app(OdkFarmEntityService::class)->createFarm(
            team: HelperService::getCurrentOwner(),
            locationId: (int) $data['location_id'],
            teamCode: $data['team_code'],
            identifiers: $data['identifiers'] ?? [],
            properties: $data['properties'] ?? [],
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            altitude: isset($data['altitude']) ? (int) $data['altitude'] : null,
            accuracy: isset($data['accuracy']) ? (float) $data['accuracy'] : null,
        );
    }
}
