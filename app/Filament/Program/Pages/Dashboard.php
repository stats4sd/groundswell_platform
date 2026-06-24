<?php

namespace App\Filament\Program\Pages;

class Dashboard extends \Filament\Pages\Dashboard
{
    public static function canAccess(): bool
    {
        return auth()->user()->can('view program admin panel dashboard');
    }
}
