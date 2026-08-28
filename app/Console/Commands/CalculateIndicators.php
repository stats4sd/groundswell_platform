<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;

class CalculateIndicators extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-indicators
        {project : The odk_projects.id to calculate indicators for}
        {xlsform_1 : The odk_id of the first xlsform required for the calculation}
        {xlsform_2 : The odk_id of the second xlsform required for the calculation}
        {xlsform_3 : The odk_id of the third xlsform required for the calculation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the external R script that calculates indicators for a project, from data collected via three xlsforms';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $project = OdkProject::find($this->argument('project'));

        if (! $project) {
            $this->error("No odk_projects record found with id {$this->argument('project')}.");

            return self::FAILURE;
        }

        $xlsformOdkId1 = $this->argument('xlsform_1');
        $xlsformOdkId2 = $this->argument('xlsform_2');
        $xlsformOdkId3 = $this->argument('xlsform_3');

        foreach (['xlsform_1' => $xlsformOdkId1, 'xlsform_2' => $xlsformOdkId2, 'xlsform_3' => $xlsformOdkId3] as $label => $odkId) {
            if (! Xlsform::where('odk_id', $odkId)->exists()) {
                $this->error("No xlsforms record found with odk_id \"{$odkId}\" (argument: {$label}).");

                return self::FAILURE;
            }
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
            (string) $xlsformOdkId1,
            (string) $xlsformOdkId2,
            (string) $xlsformOdkId3,
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
