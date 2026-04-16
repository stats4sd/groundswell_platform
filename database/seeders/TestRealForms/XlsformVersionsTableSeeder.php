<?php

namespace Database\Seeders\TestRealForms;

use Illuminate\Database\Seeder;

class XlsformVersionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('xlsform_versions')->delete();

        \DB::table('xlsform_versions')->insert(array (
            0 =>
            array (
                'id' => 5,
                'xlsform_id' => 6,
                'version' => '',
                'odk_version' => '',
                'schema' => NULL,
                'active' => 1,
                'is_draft' => 1,
                'created_at' => '2025-06-18 13:17:37',
                'updated_at' => '2025-06-18 13:17:37',
            ),
            1 =>
            array (
                'id' => 6,
                'xlsform_id' => 8,
                'version' => '',
                'odk_version' => '',
                'schema' => NULL,
                'active' => 1,
                'is_draft' => 1,
                'created_at' => '2025-06-18 13:17:42',
                'updated_at' => '2025-06-18 13:17:42',
            ),
            2 =>
            array (
                'id' => 7,
                'xlsform_id' => 10,
                'version' => '',
                'odk_version' => '',
                'schema' => NULL,
                'active' => 1,
                'is_draft' => 1,
                'created_at' => '2025-06-18 13:17:48',
                'updated_at' => '2025-06-18 13:17:48',
            ),
            3 =>
            array (
                'id' => 8,
                'xlsform_id' => 5,
                'version' => '',
                'odk_version' => '',
                'schema' => NULL,
                'active' => 1,
                'is_draft' => 1,
                'created_at' => '2025-06-18 13:18:07',
                'updated_at' => '2025-06-18 13:18:07',
            ),
            4 =>
            array (
                'id' => 9,
                'xlsform_id' => 7,
                'version' => '',
                'odk_version' => '',
                'schema' => NULL,
                'active' => 1,
                'is_draft' => 1,
                'created_at' => '2025-06-18 13:18:18',
                'updated_at' => '2025-06-18 13:18:18',
            ),
            5 =>
            array (
                'id' => 10,
                'xlsform_id' => 9,
                'version' => '',
                'odk_version' => '',
                'schema' => NULL,
                'active' => 1,
                'is_draft' => 1,
                'created_at' => '2025-06-18 13:18:33',
                'updated_at' => '2025-06-18 13:18:33',
            ),
        ));


    }
}
