<?php

namespace App\Services;

use App\Models\Team;
use Filament\Facades\Filament;

class HelperService
{
    // Get the current team with the correct namespacing so phpstan doesn't complain whenever we get the current team
    public static function getCurrentOwner(): ?Team
    {
        if (Filament::hasTenancy() && is_a(Filament::getTenant(), Team::class)) {

            /** @var Team $team */
            $team = Filament::getTenant();

            return $team;
        }

        return null;
    }
}
