<?php

namespace App\Filament\Program\Pages;

class Dashboard extends \Stats4sd\FilamentTeamManagement\Filament\Program\Pages\Dashboard
{
    public static function canAccess(): bool
    {
        return auth()->user()->can('view program admin panel dashboard');
    }
}
