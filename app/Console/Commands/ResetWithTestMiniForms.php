<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ResetWithTestMiniForms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-mini';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs the commands from the readme to reset the app database and setup with the test mini HOLPA forms';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $this->call('migrate:fresh');
        $this->call('db:seed');
        $this->call('db:seed', ['class' => 'TestWithMiniForms']);
        $this->call('app:copy-media-test', ['programatic' => 1]); // add programatic argument to automate
        $this->call('app:update-xlsform-versions-from-odk-central');

    }
}
