<?php

namespace App\Filament\App\Resources\TeamResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\App\Resources\TeamResource;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription(fn () => t('WARNING: Please do not delete when there is actual survey data collected, as deletion is irreversible. Are you sure you would like to do this?')),
        ];
    }
}
