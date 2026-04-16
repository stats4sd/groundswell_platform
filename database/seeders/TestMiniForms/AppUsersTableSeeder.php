<?php

namespace Database\Seeders\TestMiniForms;

use DB;
use Illuminate\Database\Seeder;

class AppUsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {


        DB::table('app_users')->delete();

        DB::table('app_users')->insert(array (
            0 =>
            array (
                'id' => 1708,
                'odk_project_id' => 1447,
                'display_name' => 'All HOLPA Data Platform LOCAL- P1 Test Team 1',
                'type' => 'field_key',
                'token' => 'N4VeOlN6NNbr2XMqOSR9FqPYkCF6cUwKlZonJsCLSxwfStnv8p17SWDVgWuPyF1G',
                'can_access_all_forms' => 1,
                'created_at' => '2025-01-08 11:32:17',
                'updated_at' => '2025-01-08 11:32:17',
            ),
            1 =>
            array (
                'id' => 1709,
                'odk_project_id' => 1448,
                'display_name' => 'All HOLPA Data Platform LOCAL- P1 Test Team 2 1',
                'type' => 'field_key',
                'token' => 'Es3$OEeh6T0JOsWTfMc9i5tGXyCp8gFv6TnTnwmP6p2fayQLnnPxt5S$FIbt2$QH',
                'can_access_all_forms' => 1,
                'created_at' => '2025-01-08 11:32:17',
                'updated_at' => '2025-01-08 11:32:17',
            ),
            2 =>
            array (
                'id' => 1710,
                'odk_project_id' => 1449,
                'display_name' => 'All HOLPA Data Platform LOCAL- Non Program Test Team 1',
                'type' => 'field_key',
                'token' => 'c05cX7eDvI$maVCKIlOCNPA7oVsQRDi8y6GcI3e6P0Smaih7LYx9Z1d1PuVvR!a7',
                'can_access_all_forms' => 1,
                'created_at' => '2025-01-08 11:32:17',
                'updated_at' => '2025-01-08 11:32:17',
            ),
        ));


    }
}
