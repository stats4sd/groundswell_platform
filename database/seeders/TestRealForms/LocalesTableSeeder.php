<?php

namespace Database\Seeders\TestRealForms;

use Illuminate\Database\Seeder;

class LocalesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('locales')->delete();

        \DB::table('locales')->insert(array (
            0 =>
            array (
                'id' => 1,
                'language_id' => 41,
                'creator_id' => NULL,
                'is_default' => 1,
                'description' => NULL,
                'processing_count' => 0,
                'created_at' => '2025-06-16 17:24:39',
                'updated_at' => '2025-06-16 17:24:39',
            ),
        ));


    }
}
