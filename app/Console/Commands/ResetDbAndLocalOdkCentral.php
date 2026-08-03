<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ResetDbAndLocalOdkCentral extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resets the database, resets the local ODK Central instance, runs migrations + seeders.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $centralResetPath = env('ODK_LOCAL_PATH', '');

        if (! $centralResetPath) {
            $this->error('No local ODK Central path set. Exiting');

            return;
        }

        $result = Process::path($centralResetPath)
            ->timeout(0)
            ->run('./odk-reset-db.sh -y', function (string $type, string $output): void {
                $this->output->write($output);
            });

        if ($result->failed()) {
            $this->error('The ODK Central reset failed. Leaving the app database untouched.');

            return;
        }

        $this->call('migrate:fresh', ['--seed' => true]);
    }
}
