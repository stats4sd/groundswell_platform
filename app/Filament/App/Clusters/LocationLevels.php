<?php

namespace App\Filament\App\Clusters;

use Filament\Clusters\Cluster;

class LocationLevels extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
