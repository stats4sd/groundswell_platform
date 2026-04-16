<?php

namespace Database\Seeders\Prep;

use App\Models\Dataset;
use App\Models\SampleFrame\Farm;
use App\Models\SampleFrame\Location;
use App\Models\SurveyData\Crop;
use App\Models\SurveyData\EcologicalPractice;
use App\Models\SurveyData\FarmSurveyData;
use App\Models\SurveyData\FieldworkSite;
use App\Models\SurveyData\Fish;
use App\Models\SurveyData\FishUse;
use App\Models\SurveyData\Livestock;
use App\Models\SurveyData\LivestockUse;
use App\Models\SurveyData\PermanentWorker;
use App\Models\SurveyData\Product;
use App\Models\SurveyData\SeasonalWorkerSeason;
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
        Dataset::create(['name' => 'Ecological Practices', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => EcologicalPractice::class]);
        Dataset::create(['name' => 'Farms', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => Farm::class]);
        Dataset::create(['name' => 'Fieldwork Sites', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => FieldworkSite::class]);
        $fish = Dataset::create(['name' => 'Fish', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => Fish::class]);
        Dataset::create(['name' => 'Fish Uses', 'parent_id' => $fish->id, 'primary_key' => 'id', 'entity_model' => FishUse::class]);
        $livestock = Dataset::create(['name' => 'Livestock', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => Livestock::class]);
        Dataset::create(['name' => 'Livestock Uses', 'parent_id' => $livestock->id, 'primary_key' => 'id', 'entity_model' => LivestockUse::class]);
        Dataset::create(['name' => 'Locations', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => Location::class]);
        Dataset::create(['name' => 'Permanent Workers', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => PermanentWorker::class]);
        Dataset::create(['name' => 'Products', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => Product::class]);
        Dataset::create(['name' => 'Seasonal Workers in a Season', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id', 'entity_model' => SeasonalWorkerSeason::class]);

        // datasets not mentioned in Data Structure Excel file
        Dataset::create(['name' => 'Growing Seasons (Irrigation)', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id']);
        Dataset::create(['name' => 'Sites', 'parent_id' => $farmSurveyDataset->id, 'primary_key' => 'id']);
    }
}
