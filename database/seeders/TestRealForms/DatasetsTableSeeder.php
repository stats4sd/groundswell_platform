<?php

namespace Database\Seeders\TestRealForms;

use Illuminate\Database\Seeder;

class DatasetsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('datasets')->delete();

        \DB::table('datasets')->insert(array (
            0 =>
            array (
                'id' => 1,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Farm Survey Data',
                'parent_id' => NULL,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SurveyData\\FarmSurveyData',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            1 =>
            array (
                'id' => 2,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Crops',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SurveyData\\Crop',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            2 =>
            array (
                'id' => 3,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Ecological Practices',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SurveyData\\EcologicalPractice',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            3 =>
            array (
                'id' => 4,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Farms',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SampleFrame\\Farm',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            4 =>
            array (
                'id' => 5,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Fieldwork Sites',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SurveyData\\FieldworkSite',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            5 =>
            array (
                'id' => 6,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Fish',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SurveyData\\Fish',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            6 =>
            array (
                'id' => 7,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Fish Uses',
                'parent_id' => 6,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SurveyData\\FishUse',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            7 =>
            array (
                'id' => 8,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Livestock',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SurveyData\\Livestock',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            8 =>
            array (
                'id' => 9,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Livestock Uses',
                'parent_id' => 8,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SurveyData\\LivestockUse',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            9 =>
            array (
                'id' => 10,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Locations',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SampleFrame\\Location',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            10 =>
            array (
                'id' => 11,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Permanent Workers',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SurveyData\\PermanentWorker',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            11 =>
            array (
                'id' => 12,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Products',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SurveyData\\Product',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            12 =>
            array (
                'id' => 13,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Seasonal Workers in a Season',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => 'App\\Models\\SurveyData\\SeasonalWorkerSeason',
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            13 =>
            array (
                'id' => 14,
                'model_id' => NULL,
                'model_type' => NULL,
            'name' => 'Growing Seasons (Irrigation)',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => NULL,
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
            14 =>
            array (
                'id' => 15,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Sites',
                'parent_id' => 1,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => NULL,
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
                'deleted_at' => NULL,
            ),
        ));


    }
}
