<?php

namespace App\Imports;

use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource;
use App\Imports\Concerns\FormatsImportFailures;
use App\Models\Import;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Throwable;

/**
 * Imports a location hierarchy from the FIRST worksheet of an uploaded spreadsheet.
 *
 * WithMultipleSheets + sheets() => [0 => $this] restricts the import to the first worksheet
 * only (the file may contain any number of others) while keeping all row-handling logic on
 * this single class, delegating the sheet back to itself. This mirrors the pattern used by
 * Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformModuleImport.
 *
 * This single-class shape is also what makes CSV files work. The PhpSpreadsheet Csv reader
 * does not expose listWorksheetNames(), so maatwebsite/excel bypasses sheets() for CSV and
 * applies THIS object as the row handler directly (see the "Csv doesn't have worksheets"
 * branch of vendor/maatwebsite/excel/src/Reader.php::getWorksheets). Because collection() and
 * the validation rules live here, CSV and Excel are handled by the same code.
 */
class LocationImport implements ShouldQueue, SkipsEmptyRows, ToCollection, WithCalculatedFormulas, WithChunkReading, WithEvents, WithHeadingRow, WithMultipleSheets, WithStrictNullComparison, WithValidation
{
    use FormatsImportFailures;

    protected Collection $parentIds;

    /**
     * Every location already owned by this team, keyed by locationKey(), so a chunk resolves
     * parents and existing rows in memory instead of two queries per level per row.
     *
     * Populated lazily from collection() and never from the constructor: each chunk is a separate
     * queued ReadChunk job with this object serialized into its payload (Maatwebsite\Excel\ChunkReader::read),
     * so building it up front would write the whole hierarchy into every chunk's payload.
     *
     * @var ?array<string, Location>
     */
    private ?array $existingLocations = null;

    public function __construct(public array $data)
    {
        $data['code_column'] = $data['header_columns'][$data['code_column']];
        $data['name_column'] = $data['header_columns'][$data['name_column']];

        $keys = collect(array_keys($data));

        $parentColumns = $keys->filter(fn ($key) => str_starts_with($key, 'parent_'));

        foreach ($parentColumns as $parentColumn) {
            $data[$parentColumn] = $data['header_columns'][$data[$parentColumn]] ?? null;
        }

        $this->parentIds = $parentColumns->filter(fn ($key) => str_contains($key, '_code'))
            ->map(fn ($key) => str_replace(['parent_', '_code_column'], '', $key));

        $this->data = $data;
    }

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    /**
     * Maatwebsite discards whatever this returns (Maatwebsite\Excel\Sheet::import calls
     * `$import->collection($rows)` and drops the result), so nothing is collected here.
     *
     * A location is matched on owner + level + code only, never on its parent: two locations under
     * different parents may legitimately share a code, and the unique index on locations.code was
     * dropped in 2025_11_24_112030_drop_unique_from_locations_table. Existing rows are never
     * updated, only reused.
     */
    public function collection(Collection $rows): void
    {
        $locationLevel = $this->data['level'];

        $createdAnyLocation = false;
        $ancestorIdsToTouch = [];

        Location::withoutFlaggingOwner(function () use ($rows, $locationLevel, &$createdAnyLocation, &$ancestorIdsToTouch): void {
            foreach ($rows as $row) {
                $currentParent = null;
                $ancestorIds = [];

                // highest parent level down to the lowest, so each level's parent already exists
                foreach ($this->parentIds as $parentId) {
                    ['location' => $parent, 'created' => $created] = $this->findOrCreateLocation(
                        (int) $parentId,
                        $row[$this->data["parent_{$parentId}_code_column"]],
                        $row[$this->data["parent_{$parentId}_name_column"]],
                        $currentParent,
                    );

                    if ($created) {
                        $createdAnyLocation = true;
                        $ancestorIdsToTouch = [...$ancestorIdsToTouch, ...$ancestorIds];
                    }

                    $ancestorIds[] = $parent->id;
                    $currentParent = $parent;
                }

                ['created' => $created] = $this->findOrCreateLocation(
                    $locationLevel->id,
                    $row[$this->data['code_column']],
                    $row[$this->data['name_column']],
                    $currentParent,
                );

                if ($created) {
                    $createdAnyLocation = true;
                    $ancestorIdsToTouch = [...$ancestorIdsToTouch, ...$ancestorIds];
                }
            }
        });

        if (! $createdAnyLocation) {
            return;
        }

        $this->touchAncestors($ancestorIdsToTouch);

        Location::flagOwner(Team::findOrFail($this->data['owner_id']));
    }

    /**
     * @return array{
     *     location: Location,
     *     created: bool
     * }
     */
    private function findOrCreateLocation(int $levelId, mixed $code, mixed $name, ?Location $parent): array
    {
        $key = $this->locationKey($levelId, $code);

        $existing = $this->existingLocations()[$key] ?? null;

        if ($existing !== null) {
            return ['location' => $existing, 'created' => false];
        }

        $location = new Location([
            'owner_id' => $this->data['owner_id'],
            'location_level_id' => $levelId,
            'parent_id' => $parent?->id,
            'code' => $code,
            'name' => $name,
        ]);

        // `touch => false` skips Model::touchOwners(), which would lazily load the parent, update
        // it, re-fire `saved` on it and then recurse to the grandparent — the whole ancestor chain
        // per created row. touchAncestors() does the same job in one query per chunk.
        $location->save(['touch' => false]);

        $this->existingLocations[$key] = $location;

        return ['location' => $location, 'created' => true];
    }

    /** @return array<string, Location> */
    private function existingLocations(): array
    {
        if ($this->existingLocations !== null) {
            return $this->existingLocations;
        }

        $this->existingLocations = [];

        Location::query()
            ->where('owner_id', $this->data['owner_id'])
            ->orderBy('id')
            ->get(['id', 'owner_id', 'code', 'location_level_id', 'parent_id'])
            ->each(function (Location $location): void {
                // oldest wins, matching the unordered first() this index replaced
                $this->existingLocations[$this->locationKey($location->location_level_id, $location->code)] ??= $location;
            });

        return $this->existingLocations;
    }

    /**
     * MySQL's collation on locations.code matches case-insensitively and ignores trailing
     * whitespace, and comparing a numeric spreadsheet cell against the varchar column is a loose
     * comparison. An array lookup does none of that, so both sides are normalised the same way —
     * otherwise a row that used to reuse an existing location would silently insert a duplicate,
     * and there is no longer a unique index to stop it.
     *
     * Note that SQLite, which the test suite runs on, is case-sensitive here and so agrees with
     * the raw array lookup rather than with production.
     */
    private function locationKey(int $levelId, mixed $code): string
    {
        return $levelId.'|'.mb_strtolower(trim((string) $code));
    }

    /**
     * @param  array<int, int>  $ancestorIds
     */
    private function touchAncestors(array $ancestorIds): void
    {
        $uniqueAncestorIds = array_unique($ancestorIds);

        if ($uniqueAncestorIds === []) {
            return;
        }

        Location::whereIn('id', $uniqueAncestorIds)->update(['updated_at' => now()]);
    }

    /**
     * locations.code and locations.name are both NOT NULL, and a whole chunk is imported inside
     * one transaction, so a single blank cell in a mapped column used to abort the entire import
     * with a bare "Column 'code' cannot be null" and no indication of which row was at fault.
     * Validating up front turns that into a failure naming the row and the column.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return collect($this->requiredColumns())
            ->map(fn () => ['required'])
            ->all();
    }

    /** @return array<string, string> */
    public function customValidationMessages(): array
    {
        return collect($this->requiredColumns())
            ->mapWithKeys(fn (string $description, string $column) => [
                "{$column}.required" => "The '{$column}' column is empty. You mapped this column to the {$description}, and every row must have a value in it.",
            ])
            ->all();
    }

    /**
     * Spreadsheet column name => the mapping the user chose for it in the import form, ordered
     * from the highest parent level down to the level being imported.
     *
     * @return array<string, string>
     */
    private function requiredColumns(): array
    {
        $parentNames = LocationLevel::whereIn('id', $this->parentIds)->pluck('name', 'id');

        $columns = [];

        foreach ($this->parentIds as $parentId) {
            $parentName = $parentNames->get((int) $parentId, "location level {$parentId}");

            $columns[$this->data["parent_{$parentId}_code_column"]] = "{$parentName} unique code";
            $columns[$this->data["parent_{$parentId}_name_column"]] = "{$parentName} name";
        }

        $levelName = $this->data['level']->name;

        $columns[$this->data['code_column']] = "{$levelName} unique code";
        $columns[$this->data['name_column']] = "{$levelName} name";

        return $columns;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * When this import is the first half of the combined locations+farms wizard, the farm
     * import is appended to this import's own job chain (see
     * ImportLocationsAndFarmEntities::save()), so a failure here aborts the chain and the
     * farm import never runs. Without this its Import record would sit empty, reading as
     * "nothing happened" rather than "skipped". The key is absent when LocationImport is
     * used standalone from LocationLevelResource\Pages\ViewLocationLevel.
     */
    protected function failDependentImport(ImportFailed $event): void
    {
        $dependentImportId = $this->data['dependent_import_id'] ?? null;

        if ($dependentImportId === null) {
            return;
        }

        $dependentImport = Import::find($dependentImportId);

        if ($dependentImport === null) {
            return;
        }

        $dependentImport->appendErrorLines([
            [
                'row' => null,
                'attribute' => null,
                'errors' => [
                    'The farm import was skipped because the location import it depends on failed: '
                        .$this->describeFailure($event->getException()),
                ],
            ],
        ]);

        $dependentImport->update(['finished_at' => now()]);
    }

    /**
     * Without this a failed location import was completely silent: the wizard reported that the
     * file was being processed, the errors were written to imports.errors, and nothing surfaced
     * them. Chaining the farm import behind this one made that worse, because FarmEntityImport's
     * own failure notification no longer fires when this half fails.
     */
    protected function notifyFailure(Throwable $exception): void
    {
        $recipient = User::find($this->data['user_id']);

        if ($recipient === null) {
            return;
        }

        Notification::make()
            ->title('Import of Location Data Failed')
            // plain text, never an HtmlString: this is built from spreadsheet cell contents,
            // and the full per-row list belongs on the page this links to, not in a toast
            ->body(Str::limit($this->describeFailure($exception), 200))
            ->danger()
            ->actions($this->viewImportActions())
            ->sendToDatabase($recipient, isEventDispatched: true)
            ->broadcast($recipient);
    }

    /** @return array<int, Action> */
    protected function viewImportActions(): array
    {
        $team = Team::find($this->data['owner_id']);

        if ($team === null) {
            return [];
        }

        return [
            Action::make('view_errors')
                ->label('See what went wrong')
                // no Filament tenant is set inside a queued job, so getUrl() cannot infer it
                ->url(ImportResource::getUrl('view', ['record' => $this->data['import_id']], tenant: $team))
                ->markAsRead(),
        ];
    }

    public function registerEvents(): array
    {
        return [
            ImportFailed::class => function (ImportFailed $event) {
                $exception = $event->getException();

                $import = Import::find($this->data['import_id']);

                if ($import !== null) {
                    $import->appendErrorLines($this->formatFailures($exception));
                    $import->update(['finished_at' => now()]);
                }

                $this->failDependentImport($event);

                $this->notifyFailure($exception);
            },
            AfterImport::class => function (AfterImport $event) {
                Import::find($this->data['import_id'])?->update([
                    'success' => true,
                    'finished_at' => now(),
                ]);

                $recipient = User::find($this->data['user_id']);

                if ($recipient === null) {
                    return;
                }

                Notification::make()
                    ->title('Import Complete')
                    ->success()
                    ->sendToDatabase($recipient, isEventDispatched: true)
                    ->broadcast($recipient);
            },
        ];
    }
}
