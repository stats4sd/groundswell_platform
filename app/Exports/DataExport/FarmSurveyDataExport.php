<?php

namespace App\Exports\DataExport;

use App\Models\Dataset;
use App\Models\Team;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FarmSurveyDataExport implements WithMultipleSheets
{

    public function __construct(public Team $team)
    {

    }

    public function sheets(): array
    {
        $sheets = [];

       // $sheets[] = new DataDictionaryIndicatorExport;
       // $sheets[] = new DataDictionaryDataExport;
       // $sheets[] = new CodebookEntryExport;

        // $sheets[] = new CalculatedIndicatorExport();

        /** @var Dataset $farmDataset */
        $farmDataset = Dataset::firstWhere('name', 'Farm Survey Data');

        if(!$farmDataset) {
            abort(500, 'Farm Survey Data dataset not found; Please contact your system administrator to resolve this issue.');
        }

        $childDatasets = $farmDataset->children
            ->filter(function($childDataset) {
                // filter out Farms, Locations, Growing Seasons (Irrigation), Sites
                return in_array($childDataset->name, [
                    'Crops',
                    'Ecological Practices',
                    'Fish',
                    'Livestock',
                    'Permanent Workers',
                    'Seasonal Workers in a Season',
                    'Products',
                    'Fieldwork Sites',
                ]);
            });



        // farm survey data
        $sheets[] = new FarmSurveyDatasetExport(team: $this->team, dataset: $farmDataset);

        foreach($childDatasets as $childDataset) {
            $sheets[] = new DatasetExport(team: $this->team, dataset: $childDataset);
        }

        return $sheets;
    }

}
