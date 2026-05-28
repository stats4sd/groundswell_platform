<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\UserResource\Pages\EditUser;

class UserResource extends \Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\UserResource
{
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
