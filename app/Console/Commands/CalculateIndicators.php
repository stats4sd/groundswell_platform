<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;

class CalculateIndicators extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-indicators
        {odk_projects.id : The odk_projects.id to calculate indicators for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the external R script that calculates indicators for a project, from data collected via its xlsforms';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $project = OdkProject::find($this->argument('odk_projects.id'));

        if (! $project) {
            $this->error("No odk_projects record found with id {$this->argument('odk_projects.id')}.");

            return self::FAILURE;
        }

        $xlsformOdkIds = $project->owner->xlsforms()
            ->orderBy('id')
            ->pluck('odk_id')
            ->filter()
            ->values();

        if ($xlsformOdkIds->isEmpty()) {
            $this->error("No published xlsforms found for odk_projects record {$project->id}.");

            return self::FAILURE;
        }

        $rscriptPath = config('services.R.rscript_path');
        $scriptPath = config('services.R.indicator_calculation_script_path');

        if (! $scriptPath) {
            $this->error('No indicator calculation script path configured. Set INDICATOR_CALCULATION_SCRIPT_PATH in .env.');

            return self::FAILURE;
        }

        $this->info("Calculating indicators for project {$project->id}...");

        $result = Process::timeout(0)->run([
            $rscriptPath,
            $scriptPath,
            (string) $project->id,
            ...$xlsformOdkIds->map(fn ($id) => (string) $id)->all(),
        ], function (string $type, string $output): void {
            $this->output->write($output);
        });

        if ($result->failed()) {
            $this->error("Indicator calculation failed for project {$project->id}.");

            return self::FAILURE;
        }

        $this->info("Indicator calculation complete for project {$project->id}.");

        return self::SUCCESS;
    }
}
