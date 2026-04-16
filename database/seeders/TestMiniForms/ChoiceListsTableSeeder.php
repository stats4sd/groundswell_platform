<?php

namespace Database\Seeders\TestMiniForms;

use DB;
use Illuminate\Database\Seeder;

class ChoiceListsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {


        DB::table('choice_lists')->delete();

        DB::table('choice_lists')->insert(array (
            0 =>
            array (
                'id' => 1,
                'xlsform_module_version_id' => 3,
                'list_name' => 'yn',
                'description' => NULL,
                'is_localisable' => 0,
                'is_dataset' => 0,
                'can_be_hidden_from_context' => 0,
                'has_custom_handling' => 0,
                'properties' => NULL,
                'created_at' => '2025-01-07 16:56:17',
                'updated_at' => '2025-01-07 16:56:17',
            ),
            1 =>
            array (
                'id' => 2,
                'xlsform_module_version_id' => 4,
                'list_name' => 'drinks',
                'description' => NULL,
                'is_localisable' => 1,
                'is_dataset' => 0,
                'can_be_hidden_from_context' => 0,
                'has_custom_handling' => 0,
                'properties' => NULL,
                'created_at' => '2025-01-07 16:56:18',
                'updated_at' => '2025-01-07 16:56:18',
            ),
            2 =>
            array (
                'id' => 3,
                'xlsform_module_version_id' => 4,
                'list_name' => 'lk',
                'description' => NULL,
                'is_localisable' => 0,
                'is_dataset' => 0,
                'can_be_hidden_from_context' => 0,
                'has_custom_handling' => 0,
                'properties' => NULL,
                'created_at' => '2025-01-07 16:56:18',
                'updated_at' => '2025-01-07 16:56:18',
            ),
            3 =>
            array (
                'id' => 4,
                'xlsform_module_version_id' => 5,
                'list_name' => 'yn',
                'description' => NULL,
                'is_localisable' => 0,
                'is_dataset' => 0,
                'can_be_hidden_from_context' => 0,
                'has_custom_handling' => 0,
                'properties' => NULL,
                'created_at' => '2025-01-07 16:56:19',
                'updated_at' => '2025-01-07 16:56:19',
            ),
            4 =>
            array (
                'id' => 5,
                'xlsform_module_version_id' => 9,
                'list_name' => 'yn',
                'description' => NULL,
                'is_localisable' => 0,
                'is_dataset' => 0,
                'can_be_hidden_from_context' => 0,
                'has_custom_handling' => 0,
                'properties' => NULL,
                'created_at' => '2025-01-07 17:00:45',
                'updated_at' => '2025-01-07 17:00:45',
            ),
            5 =>
            array (
                'id' => 6,
                'xlsform_module_version_id' => 8,
                'list_name' => 'soils',
                'description' => NULL,
                'is_localisable' => 0,
                'is_dataset' => 0,
                'can_be_hidden_from_context' => 0,
                'has_custom_handling' => 0,
                'properties' => NULL,
                'created_at' => '2025-01-07 17:00:46',
                'updated_at' => '2025-01-07 17:00:46',
            ),
            6 =>
            array (
                'id' => 7,
                'xlsform_module_version_id' => 8,
                'list_name' => 'crops',
                'description' => NULL,
                'is_localisable' => 1,
                'is_dataset' => 0,
                'can_be_hidden_from_context' => 0,
                'has_custom_handling' => 0,
                'properties' => NULL,
                'created_at' => '2025-01-07 17:00:46',
                'updated_at' => '2025-01-07 17:00:46',
            ),
        ));


    }
}
