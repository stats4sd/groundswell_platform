<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class CalculateIndicators extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-indicators
        {teams.id : The teams.id to calculate indicators for}
        {odk_central_project_id : ODK central project ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the external R script that calculates indicators for an ODK project, from data collected via its xlsforms';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $team = Team::find($this->argument('teams.id'));

        if (! $team || ! $team->odkProject) {
            $this->error("No odk_projects record found for teams.id {$this->argument('teams.id')}.");

            return self::FAILURE;
        }

        $odkProject = $team->odkProject;

        $xlsformOdkIds = $team->xlsforms()
            ->orderBy('id')
            ->pluck('odk_id')
            ->filter()
            ->values();

        if ($xlsformOdkIds->isEmpty()) {
            $this->error("No published xlsforms found for odk_projects record {$odkProject->id}.");

            return self::FAILURE;
        }

        $rscriptPath = config('services.R.rscript_path');
        $scriptPath = config('services.R.indicator_calculation_script_path');

        if (! $scriptPath) {
            $this->error('No indicator calculation script path configured. Set INDICATOR_CALCULATION_SCRIPT_PATH in .env.');

            return self::FAILURE;
        }

        $this->info("Calculating indicators for ODK project {$odkProject->id}...");

        $result = Process::timeout(0)->run([
            $rscriptPath,
            $scriptPath,
            (string) $team->id,
            (string) $this->argument('odk_central_project_id'),
            ...$xlsformOdkIds->map(fn ($id) => (string) $id)->all(),
        ], function (string $type, string $output): void {
            $this->output->write($output);
        });

        if ($result->failed()) {
            $this->error("Indicator calculation failed for ODK project {$odkProject->id}.");

            return self::FAILURE;
        }

        $this->info("Indicator calculation complete for ODK project {$odkProject->id}.");

        return self::SUCCESS;
    }
}
