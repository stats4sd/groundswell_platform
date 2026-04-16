<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedTestLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed-test-locations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates 3 location levels to match the test forms and adds 16 farms to team 1';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->call('db:seed', ['class' => 'TestLocationsSeeder']);
    }
}
