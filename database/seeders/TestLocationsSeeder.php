<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class TestLocationsSeeder extends Seeder
{
    public function run(): void
    {

        // set foreign key constraints to 0
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach (glob(database_path('seeders/TestLocations/*.php')) as $file) {
            $class = 'Database\\Seeders\\TestLocations\\' . pathinfo($file, PATHINFO_FILENAME);
            $this->call($class);
        }

        // reset foreign key constraints
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
