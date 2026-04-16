<?php

namespace Database\Seeders\TestMiniForms;

use DB;
use Illuminate\Database\Seeder;

class XlsformModuleVersionLocaleTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {


        DB::table('xlsform_module_version_locale')->delete();

        DB::table('xlsform_module_version_locale')->insert(array (
            0 =>
            array (
                'id' => 1,
                'xlsform_module_version_id' => 3,
                'locale_id' => 1,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            1 =>
            array (
                'id' => 2,
                'xlsform_module_version_id' => 2,
                'locale_id' => 1,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            2 =>
            array (
                'id' => 3,
                'xlsform_module_version_id' => 4,
                'locale_id' => 1,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            3 =>
            array (
                'id' => 4,
                'xlsform_module_version_id' => 5,
                'locale_id' => 1,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            4 =>
            array (
                'id' => 5,
                'xlsform_module_version_id' => 1,
                'locale_id' => 1,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            5 =>
            array (
                'id' => 6,
                'xlsform_module_version_id' => 7,
                'locale_id' => 1,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            6 =>
            array (
                'id' => 7,
                'xlsform_module_version_id' => 7,
                'locale_id' => 2,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            7 =>
            array (
                'id' => 8,
                'xlsform_module_version_id' => 9,
                'locale_id' => 1,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            8 =>
            array (
                'id' => 9,
                'xlsform_module_version_id' => 9,
                'locale_id' => 2,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            9 =>
            array (
                'id' => 10,
                'xlsform_module_version_id' => 8,
                'locale_id' => 1,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            10 =>
            array (
                'id' => 11,
                'xlsform_module_version_id' => 8,
                'locale_id' => 2,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            11 =>
            array (
                'id' => 12,
                'xlsform_module_version_id' => 6,
                'locale_id' => 1,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            12 =>
            array (
                'id' => 13,
                'xlsform_module_version_id' => 6,
                'locale_id' => 2,
                'needs_update' => 0,
                'updated_during_import' => 0,
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));


    }
}
