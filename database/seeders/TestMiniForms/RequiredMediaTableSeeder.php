<?php

namespace Database\Seeders\TestMiniForms;

use DB;
use Illuminate\Database\Seeder;

class RequiredMediaTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {


        DB::table('required_media')->delete();

        DB::table('required_media')->insert(array (
            0 =>
            array (
                'id' => 1,
                'dataset_id' => NULL,
                'xlsform_template_id' => 1,
                'name' => 'coffee.png',
                'type' => 'image',
                'is_static' => 1,
                'exists_on_odk' => 0,
                'choice_list_id' => NULL,
                'updated_during_import' => 1,
                'created_at' => '2025-01-07 16:56:16',
                'updated_at' => '2025-01-07 16:56:16',
            ),
        ));


    }
}
