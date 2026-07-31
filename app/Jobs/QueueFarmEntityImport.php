<?php

namespace App\Jobs;

use App\Imports\FarmEntityImport;
use App\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
     * later failure. Written in the same shape so the imports table renders it the same way.
     */
    public function failed(Throwable $exception): void
    {
        Import::find($this->importId)?->update([
            'errors' => [
                [
                    'row' => null,
                    'attribute' => null,
                    'errors' => [$exception->getMessage()],
                ],
            ],
        ]);
    }
}
