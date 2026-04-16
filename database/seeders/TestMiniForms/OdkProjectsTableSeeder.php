<?php

namespace Database\Seeders\TestMiniForms;

use DB;
use Illuminate\Database\Seeder;

class OdkProjectsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {


        DB::table('odk_projects')->delete();

        DB::table('odk_projects')->insert(array (
            0 =>
            array (
                'id' => 1390,
                'owner_id' => 1,
                'owner_type' => 'Stats4sd\\FilamentOdkLink\\Models\\OdkLink\\Platform',
                'name' => 'HOLPA Data Platform LOCAL Platform',
                'description' => NULL,
                'archived' => NULL,
                'created_at' => '2025-01-08 11:32:15',
                'updated_at' => '2025-01-08 11:32:15',
            ),
            1 =>
            array (
                'id' => 1447,
                'owner_id' => 3,
                'owner_type' => 'App\\Models\\Team',
                'name' => 'HOLPA Data Platform LOCAL- P1 Test Team',
                'description' => NULL,
                'archived' => NULL,
                'created_at' => '2025-01-08 11:32:16',
                'updated_at' => '2025-01-08 11:32:16',
            ),
            2 =>
            array (
                'id' => 1448,
                'owner_id' => 4,
                'owner_type' => 'App\\Models\\Team',
                'name' => 'HOLPA Data Platform LOCAL- P1 Test Team 2',
                'description' => NULL,
                'archived' => NULL,
                'created_at' => '2025-01-08 11:32:17',
                'updated_at' => '2025-01-08 11:32:17',
            ),
            3 =>
            array (
                'id' => 1449,
                'owner_id' => 5,
                'owner_type' => 'App\\Models\\Team',
                'name' => 'HOLPA Data Platform LOCAL- Non Program Test Team',
                'description' => NULL,
                'archived' => NULL,
                'created_at' => '2025-01-08 11:32:17',
                'updated_at' => '2025-01-08 11:32:17',
            ),
        ));


    }
}
