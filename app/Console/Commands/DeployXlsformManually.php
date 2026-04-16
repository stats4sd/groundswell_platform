<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;

class DeployXlsformManually extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deploy-xlsform-manually';

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
        $xlsformId = $this->choice('Which xlsform would you like to deploy?', Xlsform::all()->pluck('id')->toArray());

        Xlsform::find($xlsformId)->deployDraft();
    }
}
