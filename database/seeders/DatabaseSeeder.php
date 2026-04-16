<?php

namespace Database\Seeders;

use Database\Seeders\TestRealForms\AppUsersTableSeeder;
use Database\Seeders\TestRealForms\ChoiceListEntriesTableSeeder;
use Database\Seeders\TestRealForms\ChoiceListsTableSeeder;
use Database\Seeders\TestRealForms\DatasetsTableSeeder;
use Database\Seeders\TestRealForms\LanguageStringsTableSeeder;
use Database\Seeders\TestRealForms\LocalesTableSeeder;
use Database\Seeders\TestRealForms\MediaTableSeeder;
use Database\Seeders\TestRealForms\OdkProjectsTableSeeder;
use Database\Seeders\TestRealForms\RequiredMediaTableSeeder;
use Database\Seeders\TestRealForms\SelectedXlsformModuleVersionsTableSeeder;
use Database\Seeders\TestRealForms\SurveyRowsTableSeeder;
use Database\Seeders\TestRealForms\XlsformModulesTableSeeder;
use Database\Seeders\TestRealForms\XlsformModuleVersionsTableSeeder;
use Database\Seeders\TestRealForms\XlsformsTableSeeder;
use Database\Seeders\TestRealForms\XlsformTemplateSectionsTableSeeder;
use Database\Seeders\TestRealForms\XlsformTemplatesTableSeeder;
use Database\Seeders\TestRealForms\XlsformVersionsTableSeeder;
use Illuminate\Database\Seeder;
use Stats4sd\FilamentOdkLink\Database\Seeders\PlatformSeeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ODK Platform setup
        $this->call(PlatformSeeder::class);
        $this->call(Un49LocationSeeder::class);

        // call the prep seeders always.
        foreach (glob(database_path('seeders/Prep/*.php')) as $file) {
            $class = 'Database\\Seeders\\Prep\\' . pathinfo($file, PATHINFO_FILENAME);
            $this->call($class);
    }

        // Call the test seeders locally
        if (app()->environment('local')) {
            foreach (glob(database_path('seeders/Test/*.php')) as $file) {
                $class = 'Database\\Seeders\\Test\\' . pathinfo($file, PATHINFO_FILENAME);
                $this->call($class);
            }
        }

        $this->call(TempResultSeeder::class);
    }
}
