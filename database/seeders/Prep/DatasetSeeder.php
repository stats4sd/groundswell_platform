<?php

namespace Database\Seeders\Prep;

use App\Models\Dataset;
use App\Models\SampleFrame\Farm;
use App\Models\SampleFrame\Location;
use App\Models\SurveyData\Crop;
use App\Models\SurveyData\FarmSurveyData;
use App\Models\SurveyData\Livestock;
use DB;
use Illuminate\Database\Seeder;

class DatasetSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {


        DB::table('datasets')->delete();

        // datasets mentioned in Data Structure excel file
        $farmSurveyDataset = Dataset::create(['name' => 'Farm Survey Data', 'parent_id' => NULL, 'primary_key' => 'id', 'entity_model' => FarmSurveyData::class]);
        Dataset::create(['name' => 'Crops', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => Crop::class]);
        Dataset::create(['name' => 'Farms', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => Farm::class]);
        Dataset::create(['name' => 'Livestock', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => Livestock::class]);
        Dataset::create(['name' => 'Locations', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => Location::class]);

    }
}
