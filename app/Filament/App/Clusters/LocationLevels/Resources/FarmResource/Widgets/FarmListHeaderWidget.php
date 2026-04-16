<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\FarmResource\Widgets;

use Filament\Widgets\Widget;
use App\Services\HelperService;

class FarmListHeaderWidget extends Widget
{
    protected static string $view = 'filament.app.resources.farm-resource.widgets.farm-list-header-widget';

    protected int|string|array $columnSpan = 'full';

    // check if there are locations at the correct admin level for farms to be linked.
    public function hasLocations(): bool
    {
        return HelperService::getCurrentOwner()
            ->locationLevels()
            ->where('has_farms', true)
            ->whereHas('locations')
            ->exists();
    }
}
