<?php

namespace App\Console\Commands;

use App\Events\LanguageImportIsComplete;
use App\Models\Team;
use App\Models\User;
use App\Services\LocationSectionBuilder;
use Illuminate\Console\Command;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

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
       LocationSectionBuilder::createCustomLocationModuleVersion(Team::find(3));
    }
}
