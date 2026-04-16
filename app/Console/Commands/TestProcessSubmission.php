<?php

namespace App\Console\Commands;

use App\Http\Controllers\SubmissionController;
use Illuminate\Console\Command;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class TestProcessSubmission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-process-submission {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tests the reprocessing of a specific submission';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $submission = Submission::find($this->argument('id'));

        SubmissionController::process($submission);


    }
}
