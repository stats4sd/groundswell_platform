<?php

namespace Database\Seeders\TestLocations;

use Illuminate\Database\Seeder;

class LocationsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('locations')->delete();

        \DB::table('locations')->insert(array (
            0 =>
            array (
                'id' => 1,
                'owner_id' => 1,
                'location_level_id' => 1,
                'parent_id' => NULL,
                'code' => '1',
                'name' => 'District One',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            1 =>
            array (
                'id' => 2,
                'owner_id' => 1,
                'location_level_id' => 2,
                'parent_id' => 1,
                'code' => '3',
                'name' => 'Sub 1.1',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            2 =>
            array (
                'id' => 3,
                'owner_id' => 1,
                'location_level_id' => 3,
                'parent_id' => 2,
                'code' => '7',
                'name' => 'Seven',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            3 =>
            array (
                'id' => 9,
                'owner_id' => 1,
                'location_level_id' => 3,
                'parent_id' => 2,
                'code' => '8',
                'name' => 'Eight',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            4 =>
            array (
                'id' => 14,
                'owner_id' => 1,
                'location_level_id' => 2,
                'parent_id' => 1,
                'code' => '4',
                'name' => 'Sub 1.2',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            5 =>
            array (
                'id' => 15,
                'owner_id' => 1,
                'location_level_id' => 3,
                'parent_id' => 14,
                'code' => '9',
                'name' => 'Nine',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            6 =>
            array (
                'id' => 21,
                'owner_id' => 1,
                'location_level_id' => 3,
                'parent_id' => 14,
                'code' => '10',
                'name' => 'Ten',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            7 =>
            array (
                'id' => 25,
                'owner_id' => 1,
                'location_level_id' => 1,
                'parent_id' => NULL,
                'code' => '2',
                'name' => 'District Two',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            8 =>
            array (
                'id' => 26,
                'owner_id' => 1,
                'location_level_id' => 2,
                'parent_id' => 25,
                'code' => '5',
                'name' => 'Sub 2.1',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            9 =>
            array (
                'id' => 27,
                'owner_id' => 1,
                'location_level_id' => 3,
                'parent_id' => 26,
                'code' => '11',
                'name' => 'Eleven',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            10 =>
            array (
                'id' => 33,
                'owner_id' => 1,
                'location_level_id' => 3,
                'parent_id' => 26,
                'code' => '12',
                'name' => 'Twelve',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            11 =>
            array (
                'id' => 38,
                'owner_id' => 1,
                'location_level_id' => 2,
                'parent_id' => 25,
                'code' => '6',
                'name' => 'Sub 2.2',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            12 =>
            array (
                'id' => 39,
                'owner_id' => 1,
                'location_level_id' => 3,
                'parent_id' => 38,
                'code' => '13',
                'name' => 'Thirteen',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
            13 =>
            array (
                'id' => 45,
                'owner_id' => 1,
                'location_level_id' => 3,
                'parent_id' => 38,
                'code' => '14',
                'name' => 'Fourteen',
                'description' => NULL,
                'created_at' => '2025-04-17 14:21:17',
                'updated_at' => '2025-04-17 14:21:17',
            ),
        ));


    }
}
