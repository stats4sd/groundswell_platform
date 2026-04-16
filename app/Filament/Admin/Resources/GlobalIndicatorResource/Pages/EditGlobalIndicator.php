<?php

namespace App\Filament\Admin\Resources\GlobalIndicatorResource\Pages;

use App\Filament\Admin\Resources\GlobalIndicatorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGlobalIndicator extends EditRecord
{
    protected static string $resource = GlobalIndicatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
