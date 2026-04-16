<?php

namespace Database\Seeders\TestRealForms;

use Illuminate\Database\Seeder;

class OdkProjectsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('odk_projects')->delete();

        \DB::table('odk_projects')->insert(array (
            0 =>
            array (
                'id' => 1654,
                'owner_type' => 'Stats4sd\\FilamentOdkLink\\Models\\OdkLink\\Platform',
                'owner_id' => 1,
                'name' => 'HOLPA- HOLPA Platform.php1',
                'description' => NULL,
                'archived' => NULL,
                'created_at' => '2025-06-16 17:24:35',
                'updated_at' => '2025-06-16 17:24:35',
            ),
            1 =>
            array (
                'id' => 2728,
                'owner_type' => 'App\\Models\\Team',
                'owner_id' => 3,
                'name' => 'HOLPA Data Platform LOCAL- P1 Test Team',
                'description' => NULL,
                'archived' => NULL,
                'created_at' => '2025-06-18 13:14:12',
                'updated_at' => '2025-06-18 13:14:12',
            ),
            2 =>
            array (
                'id' => 2729,
                'owner_type' => 'App\\Models\\Team',
                'owner_id' => 4,
                'name' => 'HOLPA Data Platform LOCAL- P1 Test Team 2',
                'description' => NULL,
                'archived' => NULL,
                'created_at' => '2025-06-18 13:14:13',
                'updated_at' => '2025-06-18 13:14:13',
            ),
            3 =>
            array (
                'id' => 2730,
                'owner_type' => 'App\\Models\\Team',
                'owner_id' => 5,
                'name' => 'HOLPA Data Platform LOCAL- Non Program Test Team',
                'description' => NULL,
                'archived' => NULL,
                'created_at' => '2025-06-18 13:14:22',
                'updated_at' => '2025-06-18 13:14:22',
            ),
        ));


    }
}
