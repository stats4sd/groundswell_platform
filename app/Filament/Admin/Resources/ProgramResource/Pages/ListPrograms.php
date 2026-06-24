<?php

namespace App\Filament\Admin\Resources\ProgramResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Admin\Resources\ProgramResource;
use Filament\Actions;

class ListPrograms extends \Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\Programs\Pages\ListPrograms
{
    protected static string $resource = ProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
