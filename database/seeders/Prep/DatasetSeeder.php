<?php

namespace Database\Seeders\Prep;

use App\Models\Dataset;
use App\Models\SampleFrame\Location;
use DB;
use Illuminate\Database\Seeder;

class DatasetSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {

        DB::table('datasets')->delete();

        // datasets mentioned in Data Structure excel file
        $farmSurveyDataset = Dataset::create(['name' => 'Farm Survey Data', 'parent_id' => null, 'primary_key' => 'id']);
        Dataset::create(['name' => 'Crops', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id']);
        Dataset::create(['name' => 'Livestock', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id']);
        Dataset::create(['name' => 'Locations', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => Location::class]);

    }
}
