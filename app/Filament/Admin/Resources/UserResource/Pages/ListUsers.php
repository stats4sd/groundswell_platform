<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\UserResource\Pages\ListUsers as BaseListUsers;

class ListUsers extends BaseListUsers
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return collect(parent::getHeaderActions())
            ->map(function ($action) {
                if ($action->getName() === 'invite users') {
                    $action->visible(fn () => auth()->user()->can('maintain users'));
                }

                return $action;
            })
            ->all();
    }
}
