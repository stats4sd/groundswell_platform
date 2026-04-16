<?php

namespace App\Filament\App\Clusters;

use Filament\Clusters\Cluster;

class Localisations extends Cluster
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

}
