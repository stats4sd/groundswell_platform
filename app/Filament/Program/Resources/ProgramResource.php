<?php

namespace App\Filament\Program\Resources;

use App\Filament\Program\Resources\ProgramResource\Pages;
use App\Filament\Program\Resources\ProgramResource\RelationManagers\TeamsRelationManager;
use App\Filament\Program\Resources\ProgramResource\RelationManagers\UsersRelationManager;
use Stats4sd\FilamentTeamManagement\Filament\Program\Resources\ProgramResource\Pages\CreateProgram;
use Stats4sd\FilamentTeamManagement\Filament\Program\Resources\ProgramResource\Pages\ListPrograms;
use Stats4sd\FilamentTeamManagement\Filament\Program\Resources\ProgramResource\RelationManagers\InvitesRelationManager;

class ProgramResource extends \Stats4sd\FilamentTeamManagement\Filament\Program\Resources\ProgramResource
{
    public static function getRelations(): array
    {
        return [
            TeamsRelationManager::class,
            UsersRelationManager::class,
            InvitesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrograms::route('/'),
            'create' => CreateProgram::route('/create'),
            'edit' => Pages\EditProgram::route('/{record}/edit'),
            'view' => Pages\ViewProgram::route('/{record}'),
        ];
    }
}
