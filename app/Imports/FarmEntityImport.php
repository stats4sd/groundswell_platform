<?php

namespace App\Imports;

use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource;
use App\Imports\Concerns\FormatsImportFailures;
use App\Models\Import;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Models\User;
use App\Services\OdkFarmEntityService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

/**
 * Imports a farm list spreadsheet by pushing rows to ODK Central as entities (via
 * OdkFarmEntityService::bulkCreateFarms()); only the FarmEntity stub rows are stored locally.
 *
 * WithMultipleSheets + sheets() => [0 => $this] restricts the import to the first worksheet
 * only while keeping all row-handling logic on this single class, delegating the sheet back
 * to itself (mirrors Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformModuleImport).
 *
 * This single-class shape is also what makes CSV files work. The PhpSpreadsheet Csv reader
 * does not expose listWorksheetNames(), so maatwebsite/excel bypasses sheets() for CSV and
 * applies THIS object as the row handler directly (see the "Csv doesn't have worksheets"
 * branch of vendor/maatwebsite/excel/src/Reader.php::getWorksheets). Because collection() and
 * the validation rules live here, CSV and Excel are handled by the same code.
 */
class FarmEntityImport implements ShouldQueue, SkipsEmptyRows, ToCollection, WithCalculatedFormulas, WithChunkReading, WithEvents, WithHeadingRow, WithMultipleSheets, WithStrictNullComparison, WithValidation
{
    use FormatsImportFailures;

    // The $data array is the data that is passed from the import form
    public function __construct(public array $data) {}

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function collection(Collection $rows): array
    {
        $headers = $this->data['header_columns'];

        $farmCodeColumn = $headers[$this->data['farm_code_column']];
        $locationLevel = LocationLevel::find($this->data['location_level_id']);
        $locationCodeColumn = $headers[$this->data['location_code_column']];

        $identifierColumns = collect($this->data['farm_identifiers'])->map(fn ($identifier) => $headers[$identifier]);
        $propertyColumns = collect($this->data['farm_properties'])->map(fn ($property) => $headers[$property]);

        $gpsColumnsByName = $this->gpsColumnsByName();

        $latitudeColumn = $gpsColumnsByName->get('latitude');
        $longitudeColumn = $gpsColumnsByName->get('longitude');
        $altitudeColumn = $gpsColumnsByName->get('altitude');
        $accuracyColumn = $gpsColumnsByName->get('accuracy');

        $identifierColumns = $identifierColumns->reject(fn ($column) => $gpsColumnsByName->contains($column))->values();
        $propertyColumns = $propertyColumns->reject(fn ($column) => $gpsColumnsByName->contains($column))->values();

        $toFloat = fn (mixed $value): ?float => $value !== null ? (float) $value : null;
        $toInt = fn (mixed $value): ?int => $value !== null ? (int) $value : null;

        // The queued job runs outside any Filament panel/tenancy context, so the team
        // must come from data captured at form-submission time, not HelperService.
        $team = Team::findOrFail($this->data['owner_id']);

        $preparedRows = $rows
            ->map(function ($row) use ($team, $farmCodeColumn, $locationLevel, $locationCodeColumn, $identifierColumns, $propertyColumns, $latitudeColumn, $longitudeColumn, $altitudeColumn, $accuracyColumn, $toFloat, $toInt) {
                $location = Location::where('code', $row[$locationCodeColumn])
                    ->where('location_level_id', $locationLevel->id)
                    ->where('owner_id', $team->id)
                    ->first();

                return [
                    'locationId' => $location?->id,
                    'teamCode' => (string) $row[$farmCodeColumn],
                    'identifiers' => $identifierColumns->mapWithKeys(fn ($column) => [(string) $column => (string) $row[$column]])->all(),
                    'properties' => $propertyColumns->mapWithKeys(fn ($column) => [(string) $column => (string) $row[$column]])->all(),
                    'latitude' => $latitudeColumn !== null ? $toFloat($row[$latitudeColumn]) : null,
                    'longitude' => $longitudeColumn !== null ? $toFloat($row[$longitudeColumn]) : null,
                    'altitude' => $altitudeColumn !== null ? $toInt($row[$altitudeColumn]) : null,
                    'accuracy' => $accuracyColumn !== null ? $toFloat($row[$accuracyColumn]) : null,
                ];
            })
            // the location code was already validated by rules(), but a row could still
            // slip through with no match if two location levels share a code
            ->filter(fn ($row) => $row['locationId'] !== null)
            ->map(fn ($row) => [...$row, 'locationId' => (int) $row['locationId']])
            ->values();

        $sourceName = isset($this->data['upload']) ? basename($this->data['upload']) : null;

        return app(OdkFarmEntityService::class)->bulkCreateFarms($team, $preparedRows, $sourceName)->all();
    }

    public function rules(): array
    {
        $headers = $this->data['header_columns'];
        $locationCodeColumn = $headers[$this->data['location_code_column']];
        $farmCodeColumn = $headers[$this->data['farm_code_column']];

        $rules = [
            $locationCodeColumn => [
                'required',
                Rule::exists('locations', 'code')
                    ->where('owner_id', $this->data['owner_id'])
                    ->where('location_level_id', $this->data['location_level_id']),
            ],
            $farmCodeColumn => ['required'],
        ];

        // Auto-detected GPS columns get the same numeric/range validation as the manual
        // farm form (FarmEntityResource), so non-numeric ("unknown") or out-of-range cells
        // surface as import errors instead of being silently (float)-cast to 0.0 or pushed
        // to Central as an invalid coordinate.
        foreach ($this->gpsColumnsByName() as $field => $column) {
            $rules[$column] = ['nullable', 'numeric', ...$this->gpsRangeRules($field)];
        }

        return $rules;
    }

    /**
     * Maps each GPS field (latitude/longitude/altitude/accuracy) to the mapped spreadsheet
     * column header, for identifier/property columns whose header matches by name
     * (case-insensitive). No dedicated GPS column-mapping step exists - a selected
     * identifier/property column whose header matches a GPS field is pulled out as GPS
     * instead, so it isn't also treated as a generic identifier/property. Matches how GPS
     * is detected by name elsewhere in this app (see OdkFarmEntityService::GPS_FIELDS).
     *
     * @return Collection<string, string>
     */
    private function gpsColumnsByName(): Collection
    {
        $headers = $this->data['header_columns'];

        return collect($this->data['farm_identifiers'])
            ->merge($this->data['farm_properties'])
            ->map(fn ($column) => $headers[$column])
            ->filter(fn ($column) => in_array(Str::lower($column), OdkFarmEntityService::GPS_FIELDS, true))
            ->mapWithKeys(fn ($column) => [Str::lower($column) => $column]);
    }

    /**
     * Range rules matching the manual farm form (FarmEntityResource GPS section). Accuracy
     * carries no range there, so it is validated as numeric only.
     *
     * @return array<int, string>
     */
    private function gpsRangeRules(string $field): array
    {
        return match ($field) {
            'latitude' => ['between:-90,90'],
            'longitude' => ['between:-180,180'],
            'altitude' => ['between:-1240,60000'],
            default => [],
        };
    }

    public function customValidationMessages(): array
    {
        $headers = $this->data['header_columns'];
        $locationCodeColumn = $headers[$this->data['location_code_column']];
        $farmCodeColumn = $headers[$this->data['farm_code_column']];

        return [
            "$locationCodeColumn.required" => "The $locationCodeColumn cannot be empty.",
            "$locationCodeColumn.exists" => 'The location with this code does not exist for your team at the selected level.',
            "$farmCodeColumn.required" => 'The farm code cannot be empty.',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * The notification body is deliberately a summary - the full per-row list lives on the
     * import's own page, which is what this links to.
     *
     * @return array<int, Action>
     */
    private function viewImportActions(): array
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

                $recipient = User::find($this->data['user_id']);

                if ($recipient === null) {
                    return;
                }

                Notification::make()
                    ->title('Import of Farm Data Failed')
                    ->body(Str::limit($this->describeFailure($exception), 200))
                    ->danger()
                    ->actions($this->viewImportActions())
                    ->sendToDatabase($recipient, isEventDispatched: true)
                    ->broadcast($recipient);
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
                    ->title('Import of Farm Data Complete')
                    ->success()
                    ->sendToDatabase($recipient, isEventDispatched: true)
                    ->broadcast($recipient);
            },
        ];
    }
}
