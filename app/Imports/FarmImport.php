<?php

namespace App\Imports;

use App\Models\Import;
use App\Models\SampleFrame\Farm;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
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
use Maatwebsite\Excel\Validators\ValidationException;

/**
 * Imports farms (into the local farms table) from the FIRST worksheet of an uploaded
 * spreadsheet.
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
class FarmImport implements ShouldQueue, SkipsEmptyRows, ToCollection, WithCalculatedFormulas, WithChunkReading, WithEvents, WithHeadingRow, WithMultipleSheets, WithStrictNullComparison, WithValidation
{
    // The $data array is the data that is passed from the ImportFarmsAction form
    public function __construct(public array $data) {}

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function collection(Collection $rows): array
    {
        $importedFarms = [];

        foreach ($rows as $row) {
            $headers = $this->data['header_columns'];

            $farmCodeColumn = $headers[$this->data['farm_code_column']];
            $locationLevel = LocationLevel::find($this->data['location_level_id']);
            $locationCodeColumn = $headers[$this->data['location_code_column']];
            $location = Location::where('code', $row[$locationCodeColumn])
                ->where('location_level_id', $locationLevel->id)
                ->first();

            // Find the identifier columns;
            $identifierColumns = collect($this->data['farm_identifiers'])->map(fn ($identifier) => $headers[$identifier]);
            // Get the data from those columns;
            $identifierData = $identifierColumns->mapWithKeys(fn ($column) => [$column => $row[$column]]);

            // Find the property columns;
            $propertyColumns = collect($this->data['farm_properties'])->map(fn ($property) => $headers[$property]);
            // Get the data from those columns;
            $propertyData = $propertyColumns->mapWithKeys(fn ($column) => [$column => $row[$column]]);

            // check if farm with unique code existed in this team.
            // it is not advised to use upsert here. The old farm and new farm with unique code could be two different farms.
            // human intervention is required to handle this sitation.
            // If they are two different farms, this can be resolved by assigning a new unique code to the new farm.
            $noOfRecords = Farm::where('owner_id', $this->data['owner_id'])->where('team_code', $row[$farmCodeColumn])->count();

            // only create farms record if unique code is not existed for this team
            if ($noOfRecords == 0) {
                // Create the farm
                $farm = new Farm([
                    'owner_id' => $this->data['owner_id'],
                    'location_id' => $location->id,
                    'team_code' => $row[$farmCodeColumn],
                    'identifiers' => $identifierData,
                    'properties' => $propertyData,
                ]);
                $farm->save();

                $importedFarms[] = $farm;
            }
        }

        return $importedFarms;
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

    public function registerEvents(): array
    {
        return [
            ImportFailed::class => function (ImportFailed $event) {

                // check if exception is a validation exception, and get the failures from it
                if ($event->getException() instanceof ValidationException) {
                    $failures = collect($event->getException()->failures());

                    Import::find($this->data['import_id'])
                        ->update([
                            'errors' => $failures->map(function ($failure) {
                                return [
                                    'location' => [
                                        'row' => $failure->row(),
                                        'column' => $failure->attribute(),
                                    ],
                                    'errors' => $failure->errors(),
                                ];
                            })->toArray(),
                        ]);
                } else {
                    Import::find($this->data['import_id'])
                        ->update([
                            'errors' => [
                                [
                                    'row' => null,
                                    'attribute' => null,
                                    'errors' => [$event->getException()->getMessage()],
                                ],
                            ],
                        ]);
                }

                $recipient = User::find($this->data['user_id']);

                Notification::make()
                    ->title('Import of Farm Data Failed')
                    ->body(fn (): HtmlString => new HtmlString(
                        'The import of farm data failed with the following errors:<br/><br/>'
                            .$event->getException()->getMessage()
                    ))
                    ->danger()
                    ->sendToDatabase($recipient, isEventDispatched: true)
                    ->broadcast($recipient);
            },
            AfterImport::class => function (AfterImport $event) {

                $recipient = User::find($this->data['user_id']);

                // send notification
                Notification::make()
                    ->title('Import of Farm Data Complete')
                    ->success()
                    ->sendToDatabase($recipient, isEventDispatched: true)
                    ->broadcast($recipient);
            },
        ];
    }
}
