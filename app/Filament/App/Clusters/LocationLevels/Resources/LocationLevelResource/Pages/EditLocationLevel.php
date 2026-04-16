<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource;

class EditLocationLevel extends EditRecord
{
    protected static string $resource = LocationLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
