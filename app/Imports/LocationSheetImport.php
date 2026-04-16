<?php

namespace App\Imports;

use App\Models\SampleFrame\Location;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class LocationSheetImport implements ShouldQueue, SkipsEmptyRows, ToCollection, WithCalculatedFormulas, WithChunkReading, WithHeadingRow, WithStrictNullComparison
{
    protected Collection $parentIds;

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

    public function collection(Collection $rows): array
    {
        $locationLevel = $this->data['level'];

        $importedLocations = [];

        foreach ($rows as $row) {

            $currentParent = null;

            // go through parents in order from highest to lowest. Ensure all parents exist in the database (and create them if they do not)
            foreach ($this->parentIds as $parentId) {

                // upsert() requires columns specified in "uniqueBy" with "primary" or "unique" index in database level.
                // we removed the unique constraint of column locations.code. This column does not have unique index now.
                // modify program to check record existence, create new record if it is not existed.
                // do not update anything if location is existed. As it is possible to have two different locations with same location code accidentally.

                // check if location is already existed for this team
                $noOfRecords = Location::where('owner_id', $this->data['owner_id'])
                                ->where('code', $row[$this->data["parent_{$parentId}_code_column"]])
                                ->get()
                                ->count();

                // create new location record if it is not existed for this team
                if ($noOfRecords == 0) {
                    Location::create([
                        'owner_id' => $this->data['owner_id'],
                        'code' => $row[$this->data["parent_{$parentId}_code_column"]],
                        'name' => $row[$this->data["parent_{$parentId}_name_column"]],
                        'location_level_id' => $parentId,
                        'parent_id' => $currentParent?->id,
                    ]);
                }

                $currentParent = Location::where('code', $row[$this->data["parent_{$parentId}_code_column"]])->first();
            }

            
            // check if location is already existed for this team
            $noOfLocation = Location::where('owner_id', $this->data['owner_id'])
                                ->where('code', $row[$this->data['code_column']])
                                ->get()
                                ->count();

            // create new location record if it is not existed for this team
            if ($noOfLocation == 0) {
                Location::create([
                    'owner_id' => $this->data['owner_id'],
                    'location_level_id' => $locationLevel->id,
                    'parent_id' => $currentParent?->id ?? null,
                    'code' => $row[$this->data['code_column']],
                    'name' => $row[$this->data['name_column']],
                ]);
            }


            $currentLocation = Location::where('code', $row[$this->data['code_column']])->first();

            $importedLocations[] = $currentLocation;
        }

        return $importedLocations;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
