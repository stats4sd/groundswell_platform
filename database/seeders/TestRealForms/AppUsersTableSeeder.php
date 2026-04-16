<?php

namespace Database\Seeders\TestRealForms;

use Illuminate\Database\Seeder;

class AppUsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('app_users')->delete();

        \DB::table('app_users')->insert(array (
            0 =>
            array (
                'id' => 15,
                'odk_project_id' => 1654,
                'display_name' => 'All HOLPA- HOLPA Platform.php1 1',
                'type' => 'field_key',
                'token' => '8kDXjihTFlOtLcCjbXOQmIYCcRgRdE0$aC8$bp220z0qRRj6$0gF4S12x$XHsZ8x',
                'can_access_all_forms' => 1,
                'created_at' => '2025-06-16 17:24:35',
                'updated_at' => '2025-06-16 17:24:35',
            ),
            1 =>
            array (
                'id' => 3431,
                'odk_project_id' => 2728,
                'display_name' => 'All HOLPA Data Platform LOCAL- P1 Test Team 1',
                'type' => 'field_key',
                'token' => '6MOQvU3kDvfIVXYg6YJAjBbprs$dvm6mGmlvMCwYgav2lfK2TLmqybc8VUN1CRoS',
                'can_access_all_forms' => 1,
                'created_at' => '2025-06-18 13:14:12',
                'updated_at' => '2025-06-18 13:14:12',
            ),
            2 =>
            array (
                'id' => 3432,
                'odk_project_id' => 2729,
                'display_name' => 'All HOLPA Data Platform LOCAL- P1 Test Team 2 1',
                'type' => 'field_key',
                'token' => '7rLZMWDEoTLh6bhkGBcsBHT$KdEg3V1CmH4LFvNAXlYbeA29rwSTOcpZPSH3zNI2',
                'can_access_all_forms' => 1,
                'created_at' => '2025-06-18 13:14:13',
                'updated_at' => '2025-06-18 13:14:13',
            ),
            3 =>
            array (
                'id' => 3433,
                'odk_project_id' => 2730,
                'display_name' => 'All HOLPA Data Platform LOCAL- Non Program Test Team 1',
                'type' => 'field_key',
                'token' => 'l9ZfslP4qSJy51I2DUQUX1TaHrCPAoyXYCX6reghbIb1UFbNfRRgY2qPLYfITHuj',
                'can_access_all_forms' => 1,
                'created_at' => '2025-06-18 13:14:22',
                'updated_at' => '2025-06-18 13:14:22',
            ),
        ));


    }
}
