<?php

namespace App\Filament\Admin\Resources\DietDiversityModuleVersionResource\Pages;

use App\Filament\Admin\Resources\DietDiversityModuleVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDietDiversityModuleVersions extends ListRecords
{
    protected static string $resource = DietDiversityModuleVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
