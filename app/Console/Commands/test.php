<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\XlsformModules\FarmInfoModuleBuilder;
use App\Services\XlsformModules\LocationsModuleBuilder;
use Illuminate\Console\Command;

class test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-notice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        LocationsModuleBuilder::populate(Team::find(3));
        FarmInfoModuleBuilder::populate(Team::find(3));
    }
}
