<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages;

use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource;
use App\Models\SampleFrame\FarmEntity;
use App\Services\OdkFarmEntityService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditFarmEntity extends EditRecord
{
    protected static string $resource = FarmEntityResource::class;

    // getEntityData() does its own live single-entity fetch, so no whole-team refresh
    // is needed here - splits the farm's current entity data back into the
    // identifiers/properties KeyValue fields before the form is filled.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...app(OdkFarmEntityService::class)->getEntityData($this->getFarmEntity())];
    }

    // Overridden because updating a farm means pushing to ODK Central (with optimistic
    // concurrency), not a plain `$record->update($data)`.
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(OdkFarmEntityService::class)->updateFarm(
            farmEntity: $this->asFarmEntity($record),
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

    protected function getFarmEntity(): FarmEntity
    {
        return $this->asFarmEntity($this->getRecord());
    }

    protected function asFarmEntity(Model $record): FarmEntity
    {
        if (! $record instanceof FarmEntity) {
            throw new \RuntimeException('Expected a FarmEntity record on '.static::class);
        }

        return $record;
    }
}
