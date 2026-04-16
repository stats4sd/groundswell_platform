<?php

namespace Database\Seeders\TestMiniForms;

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
        // add to existing datasets table

        \DB::table('datasets')->insert(array (
            15 =>
            array (
                'id' => 16,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Household Mini Test Data',
                'parent_id' => NULL,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => NULL,
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => '2025-02-12 14:52:08',
                'updated_at' => '2025-02-12 14:52:09',
                'deleted_at' => NULL,
            ),
            16 =>
            array (
                'id' => 17,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Drinks Data',
                'parent_id' => 16,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => NULL,
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            17 =>
            array (
                'id' => 18,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Drink Comments Data',
                'parent_id' => 16,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => NULL,
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            18 =>
            array (
                'id' => 19,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Fieldwork Mini Test Data',
                'parent_id' => NULL,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => NULL,
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            19 =>
            array (
                'id' => 20,
                'model_id' => NULL,
                'model_type' => NULL,
                'name' => 'Fieldwork Repeat Group',
                'parent_id' => 20,
                'primary_key' => 'id',
                'description' => NULL,
                'entity_model' => NULL,
                'external_file' => 0,
                'lookup_table' => 0,
                'is_universal' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
        ));


    }
}
