<?php

namespace App\Filament\Admin\Resources\ProgramResource\Pages;

use App\Filament\Admin\Resources\ProgramResource;
use Filament\Actions;

class ListPrograms extends \Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\ProgramResource\Pages\ListPrograms
{
    protected static string $resource = ProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
