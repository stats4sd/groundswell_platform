<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Filament\Admin\Resources\UserResource\Pages;

class UserResource extends \Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\Users\UserResource
{
    public static function getPages(): array
    {
        // The package no longer ships full-page Create/Edit user pages — users are
        // created via the "invite users" action on the list page and edited inline.
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
