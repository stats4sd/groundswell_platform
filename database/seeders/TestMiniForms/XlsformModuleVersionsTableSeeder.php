<?php

namespace Database\Seeders\TestMiniForms;

use DB;
use Illuminate\Database\Seeder;

class XlsformModuleVersionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {


        DB::table('xlsform_module_versions')->delete();

        DB::table('xlsform_module_versions')->insert(array (
            0 =>
            array (
                'id' => 1,
                'xlsform_module_id' => 1,
                'name' => 'Global metadata',
                'is_default' => 1,
                'created_at' => '2025-01-07 16:56:14',
                'updated_at' => '2025-01-07 16:56:14',
            ),
            1 =>
            array (
                'id' => 2,
                'xlsform_module_id' => 2,
                'name' => 'Global context',
                'is_default' => 1,
                'created_at' => '2025-01-07 16:56:14',
                'updated_at' => '2025-01-07 16:56:14',
            ),
            2 =>
            array (
                'id' => 3,
                'xlsform_module_id' => 3,
                'name' => 'Global coffee',
                'is_default' => 1,
                'created_at' => '2025-01-07 16:56:14',
                'updated_at' => '2025-01-07 16:56:14',
            ),
            3 =>
            array (
                'id' => 4,
                'xlsform_module_id' => 4,
                'name' => 'Global diet_quality',
                'is_default' => 1,
                'created_at' => '2025-01-07 16:56:14',
                'updated_at' => '2025-01-07 16:56:14',
            ),
            4 =>
            array (
                'id' => 5,
                'xlsform_module_id' => 5,
                'name' => 'Global end',
                'is_default' => 1,
                'created_at' => '2025-01-07 16:56:14',
                'updated_at' => '2025-01-07 16:56:14',
            ),
            5 =>
            array (
                'id' => 6,
                'xlsform_module_id' => 6,
                'name' => 'Global metadata_fieldwork',
                'is_default' => 1,
                'created_at' => '2025-01-07 17:00:40',
                'updated_at' => '2025-01-07 17:00:40',
            ),
            6 =>
            array (
                'id' => 7,
                'xlsform_module_id' => 7,
                'name' => 'Global context_fieldwork',
                'is_default' => 1,
                'created_at' => '2025-01-07 17:00:40',
                'updated_at' => '2025-01-07 17:00:40',
            ),
            7 =>
            array (
                'id' => 8,
                'xlsform_module_id' => 8,
                'name' => 'Global fieldwork',
                'is_default' => 1,
                'created_at' => '2025-01-07 17:00:40',
                'updated_at' => '2025-01-07 17:00:40',
            ),
            8 =>
            array (
                'id' => 9,
                'xlsform_module_id' => 9,
                'name' => 'Global end_fieldwork',
                'is_default' => 1,
                'created_at' => '2025-01-07 17:00:40',
                'updated_at' => '2025-01-07 17:00:40',
            ),
            9 =>
            array (
                'id' => 10,
                'xlsform_module_id' => 10,
                'name' => 'Global P1 Test Team custom module',
                'is_default' => 1,
                'created_at' => '2025-01-07 17:15:45',
                'updated_at' => '2025-01-07 17:15:45',
            ),
            10 =>
            array (
                'id' => 11,
                'xlsform_module_id' => 10,
                'name' => 'custom',
                'is_default' => 0,
                'created_at' => '2025-01-07 17:15:45',
                'updated_at' => '2025-01-07 17:15:45',
            ),
            11 =>
            array (
                'id' => 12,
                'xlsform_module_id' => 11,
                'name' => 'Global P1 Test Team 2 custom module',
                'is_default' => 1,
                'created_at' => '2025-01-07 17:15:45',
                'updated_at' => '2025-01-07 17:15:45',
            ),
            12 =>
            array (
                'id' => 13,
                'xlsform_module_id' => 11,
                'name' => 'custom',
                'is_default' => 0,
                'created_at' => '2025-01-07 17:15:45',
                'updated_at' => '2025-01-07 17:15:45',
            ),
            13 =>
            array (
                'id' => 14,
                'xlsform_module_id' => 12,
                'name' => 'Global Non Program Test Team custom module',
                'is_default' => 1,
                'created_at' => '2025-01-07 17:15:45',
                'updated_at' => '2025-01-07 17:15:45',
            ),
            14 =>
            array (
                'id' => 15,
                'xlsform_module_id' => 12,
                'name' => 'custom',
                'is_default' => 0,
                'created_at' => '2025-01-07 17:15:45',
                'updated_at' => '2025-01-07 17:15:45',
            ),
            15 =>
            array (
                'id' => 16,
                'xlsform_module_id' => 13,
                'name' => 'Global P1 Test Team custom module',
                'is_default' => 1,
                'created_at' => '2025-01-07 17:15:46',
                'updated_at' => '2025-01-07 17:15:46',
            ),
            16 =>
            array (
                'id' => 17,
                'xlsform_module_id' => 13,
                'name' => 'custom',
                'is_default' => 0,
                'created_at' => '2025-01-07 17:15:46',
                'updated_at' => '2025-01-07 17:15:46',
            ),
            17 =>
            array (
                'id' => 18,
                'xlsform_module_id' => 14,
                'name' => 'Global P1 Test Team 2 custom module',
                'is_default' => 1,
                'created_at' => '2025-01-07 17:15:46',
                'updated_at' => '2025-01-07 17:15:46',
            ),
            18 =>
            array (
                'id' => 19,
                'xlsform_module_id' => 14,
                'name' => 'custom',
                'is_default' => 0,
                'created_at' => '2025-01-07 17:15:46',
                'updated_at' => '2025-01-07 17:15:46',
            ),
            19 =>
            array (
                'id' => 20,
                'xlsform_module_id' => 15,
                'name' => 'Global Non Program Test Team custom module',
                'is_default' => 1,
                'created_at' => '2025-01-07 17:15:46',
                'updated_at' => '2025-01-07 17:15:46',
            ),
            20 =>
            array (
                'id' => 21,
                'xlsform_module_id' => 15,
                'name' => 'custom',
                'is_default' => 0,
                'created_at' => '2025-01-07 17:15:46',
                'updated_at' => '2025-01-07 17:15:46',
            ),
        ));


    }
}
