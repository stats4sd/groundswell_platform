<?php

namespace App\Filament\App\Clusters\LocationLevels\Resources\ImportResource\Widgets;

use App\Filament\Tables\ImportsTable;
use App\Models\Import;
use App\Services\HelperService;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * ImportResource is where you go to investigate a failed import; this is what tells you there
 * is anything to investigate, on the pages the import flows already drop the user back onto.
 * Hidden entirely until the team has imported something, so it costs a first-time team nothing.
 */
class RecentImportsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return HelperService::getCurrentOwner()?->imports()->exists() ?? false;
    }

    public function table(Table $table): Table
    {
        return ImportsTable::configure($table)
            ->query(fn (): Builder => Import::query()
                ->whereBelongsTo(HelperService::getCurrentOwner(), 'team')
                ->with(['media', 'user'])
                ->limit(5)
            )
            ->heading(fn () => t('Recent imports'))
            ->description(fn () => t('Select an import to see what happened to it.'))
            ->paginated(false);
    }
}
