<?php

namespace App\Services;

use App\Models\SampleFrame\Farm;
use App\Models\Team;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HelperService
{
    // Get the current team with the correct namespacing so phpstan doesn't complain whenever we get the current team
    public static function getCurrentOwner(): Team|null
    {
        if (Filament::hasTenancy() && is_a(Filament::getTenant(), Team::class)) {

            /** @var Team $team */
            $team = Filament::getTenant();
            return $team;
        }

        return null;
    }

    // find farm's full location details for data export to Excel file
    public static function findFarmLocationDetails(string $farmId): array
    {
        // find farm model
        $farm = Farm::find($farmId);

        // array for storing farm and location levels details
        $array = [];

        $array['farm_name'] = $farm->identifiers['name'];
        $array['farm_id'] = $farm->id;

        $tempLocation = $farm->location;

        // find all location levels until there is no more parent location
        do {
            // a location ID field name should be lowercase, with underscore instead of hypen, e.g. sub_district
            $locationIdFieldName = Str::lower(Str::replace('-', '_', $tempLocation->locationLevel->name));

            $array[$locationIdFieldName.'_name'] = $tempLocation->name;
            $array[$locationIdFieldName.'_id'] = $tempLocation->id;

            $tempLocation = $tempLocation->parent;
        } while ($tempLocation != null);

        return array_reverse($array);
    }
}
