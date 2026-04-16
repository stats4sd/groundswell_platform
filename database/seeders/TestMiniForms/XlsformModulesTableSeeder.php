<?php

namespace Database\Seeders\TestMiniForms;

use DB;
use Illuminate\Database\Seeder;

class XlsformModulesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {


        DB::table('xlsform_modules')->delete();

        DB::table('xlsform_modules')->insert(array (
            0 =>
            array (
                'id' => 1,
                'xlsform_template_id' => 1,
                'label' => 'metadata',
                'name' => 'metadata',
                'created_at' => '2025-01-07 16:56:14',
                'updated_at' => '2025-01-07 16:56:14',
            ),
            1 =>
            array (
                'id' => 2,
                'xlsform_template_id' => 1,
                'label' => 'context',
                'name' => 'context',
                'created_at' => '2025-01-07 16:56:14',
                'updated_at' => '2025-01-07 16:56:14',
            ),
            2 =>
            array (
                'id' => 3,
                'xlsform_template_id' => 1,
                'label' => 'coffee',
                'name' => 'coffee',
                'created_at' => '2025-01-07 16:56:14',
                'updated_at' => '2025-01-07 16:56:14',
            ),
            3 =>
            array (
                'id' => 4,
                'xlsform_template_id' => 1,
                'label' => 'diet_quality',
                'name' => 'diet_quality',
                'created_at' => '2025-01-07 16:56:14',
                'updated_at' => '2025-01-07 16:56:14',
            ),
            4 =>
            array (
                'id' => 5,
                'xlsform_template_id' => 1,
                'label' => 'end',
                'name' => 'end',
                'created_at' => '2025-01-07 16:56:14',
                'updated_at' => '2025-01-07 16:56:14',
            ),
            5 =>
            array (
                'id' => 6,
                'xlsform_template_id' => 2,
                'label' => 'metadata_fieldwork',
                'name' => 'metadata_fieldwork',
                'created_at' => '2025-01-07 17:00:40',
                'updated_at' => '2025-01-07 17:00:40',
            ),
            6 =>
            array (
                'id' => 7,
                'xlsform_template_id' => 2,
                'label' => 'context_fieldwork',
                'name' => 'context_fieldwork',
                'created_at' => '2025-01-07 17:00:40',
                'updated_at' => '2025-01-07 17:00:40',
            ),
            7 =>
            array (
                'id' => 8,
                'xlsform_template_id' => 2,
                'label' => 'fieldwork',
                'name' => 'fieldwork',
                'created_at' => '2025-01-07 17:00:40',
                'updated_at' => '2025-01-07 17:00:40',
            ),
            8 =>
            array (
                'id' => 9,
                'xlsform_template_id' => 2,
                'label' => 'end_fieldwork',
                'name' => 'end_fieldwork',
                'created_at' => '2025-01-07 17:00:40',
                'updated_at' => '2025-01-07 17:00:40',
            ),
        ));


    }
}
