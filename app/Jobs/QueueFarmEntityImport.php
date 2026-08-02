<?php

namespace App\Jobs;

use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource;
use App\Imports\FarmEntityImport;
use App\Models\Import;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Starts the farm half of the combined locations+farms import, appended to the tail of the
 * job chain maatwebsite/excel built for the location import (see
 * ImportLocationsAndFarmEntities::save()). Farm rows validate their location column with
 * Rule::exists('locations', 'code'), so this must not begin until every location chunk has
 * committed - which is exactly what running as the last link of that chain guarantees.
 */
class QueueFarmEntityImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @param array<string, mixed> $data */
    public function __construct(public array $data, public int $importId) {}

    public function handle(): void
    {
        $import = Import::findOrFail($this->importId);

        Excel::import(new FarmEntityImport($this->data), $import->getFirstMediaPath());
    }

    /**
     * Only fires if the farm import could not even be started - once Excel::import() has
     * queued its own chunk jobs, FarmEntityImport's own ImportFailed handler owns any
     * later failure. Nothing else reports this case, so it notifies as well as recording.
     */
    public function failed(Throwable $exception): void
    {
        $import = Import::find($this->importId);

        if ($import === null) {
            return;
        }

        $import->appendErrorLines([
            [
                'row' => null,
                'attribute' => null,
                'errors' => [$exception->getMessage()],
            ],
        ]);

        $import->update(['finished_at' => now()]);

        $recipient = User::find($this->data['user_id'] ?? null);

        if ($recipient === null) {
            return;
        }

        $team = Team::find($this->data['owner_id'] ?? null);

        Notification::make()
            ->title('Import of Farm Data Failed')
            ->body(Str::limit($exception->getMessage(), 200))
            ->danger()
            ->actions($team === null ? [] : [
                Action::make('view_errors')
                    ->label('See what went wrong')
                    // no Filament tenant is set inside a queued job, so getUrl() cannot infer it
                    ->url(ImportResource::getUrl('view', ['record' => $this->importId], tenant: $team))
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipient, isEventDispatched: true)
            ->broadcast($recipient);
    }
}
