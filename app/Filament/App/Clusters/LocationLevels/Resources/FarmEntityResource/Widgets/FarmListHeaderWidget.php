<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Widgets;

use App\Services\HelperService;
use Filament\Widgets\Widget;

class FarmListHeaderWidget extends Widget
{
    protected string $view = 'filament.app.resources.farm-entity-resource.widgets.farm-list-header-widget';

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
