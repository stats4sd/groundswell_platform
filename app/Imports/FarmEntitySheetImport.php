<?php

namespace App\Imports;

use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithValidation;

// ODK-Entities-backed counterpart to FarmSheetImport - same column-mapping/validation
// rules, but rows are pushed to ODK Central (via OdkFarmEntityService::bulkCreateFarms())
// instead of inserted directly into a local farms table.
class FarmEntitySheetImport implements ShouldQueue, SkipsEmptyRows, ToCollection, WithCalculatedFormulas, WithChunkReading, WithHeadingRow, WithStrictNullComparison, WithValidation
{
    // The $data array is the data that is passed from the ImportFarmsAction form
    public function __construct(public array $data) {}

    public function collection(Collection $rows): array
    {
        $headers = $this->data['header_columns'];

        $farmCodeColumn = $headers[$this->data['farm_code_column']];
        $locationLevel = LocationLevel::find($this->data['location_level_id']);
        $locationCodeColumn = $headers[$this->data['location_code_column']];

        $identifierColumns = collect($this->data['farm_identifiers'])->map(fn ($identifier) => $headers[$identifier]);
        $propertyColumns = collect($this->data['farm_properties'])->map(fn ($property) => $headers[$property]);

        $preparedRows = $rows
            ->map(function ($row) use ($farmCodeColumn, $locationLevel, $locationCodeColumn, $identifierColumns, $propertyColumns) {
                $location = Location::where('code', $row[$locationCodeColumn])
                    ->where('location_level_id', $locationLevel->id)
                    ->first();

                return [
                    'locationId' => $location?->id,
                    'teamCode' => (string) $row[$farmCodeColumn],
                    'identifiers' => $identifierColumns->mapWithKeys(fn ($column) => [(string) $column => (string) $row[$column]])->all(),
                    'properties' => $propertyColumns->mapWithKeys(fn ($column) => [(string) $column => (string) $row[$column]])->all(),
                ];
            })
            // the location code was already validated by rules(), but a row could still
            // slip through with no match if two location levels share a code
            ->filter(fn ($row) => $row['locationId'] !== null)
            ->map(fn ($row) => [...$row, 'locationId' => (int) $row['locationId']])
            ->values();

        // The queued job runs outside any Filament panel/tenancy context, so the team
        // must come from data captured at form-submission time, not HelperService.
        $team = Team::findOrFail($this->data['owner_id']);

        $sourceName = isset($this->data['upload']) ? basename($this->data['upload']) : null;

        return app(OdkFarmEntityService::class)->bulkCreateFarms($team, $preparedRows, $sourceName)->all();
    }

    public function rules(): array
    {
        $headers = $this->data['header_columns'];
        $locationCodeColumn = $headers[$this->data['location_code_column']];
        $farmCodeColumn = $headers[$this->data['farm_code_column']];

        return [
            $locationCodeColumn => 'required|exists:locations,code',
            $farmCodeColumn => 'required',
        ];
    }

    public function customValidationMessages(): array
    {
        $headers = $this->data['header_columns'];
        $locationCodeColumn = $headers[$this->data['location_code_column']];
        $farmCodeColumn = $headers[$this->data['farm_code_column']];

        return [
            "$locationCodeColumn.required" => "The $locationCodeColumn cannot be empty.",
            "$locationCodeColumn.exists" => 'The location with this code does not exist in the database.',
            "$farmCodeColumn.required" => 'The farm code cannot be empty.',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
