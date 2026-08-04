<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class AddFarmDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-farm-details {submission? : Only process this submission ID, for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add farm details from the "farms" node in submissions.content to the root entity\'s entity_values';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $query = Submission::whereNotNull('content->farms')->with('rootEntity');

        if ($submissionId = $this->argument('submission')) {
            $query->where('id', $submissionId);
        }

        $submissions = $query->get();

        $this->info("Found {$submissions->count()} submission(s) with a farms node.");

        foreach ($submissions as $submission) {
            $this->info("Processing submission {$submission->id}...");

            if (! $submission->rootEntity) {
                $this->warn("Submission {$submission->id}: no root entity found, skipping.");

                continue;
            }

            $farmAttributes = collect($submission->content['farms'])
                ->filter(fn ($value) => $value !== null && $value !== '' && ! is_array($value));

            foreach ($farmAttributes as $name => $value) {
                // $this->info($name . ' => ' . $value);
                EntityValue::updateOrCreate(
                    ['entity_id' => $submission->rootEntity->id, 'dataset_variable_name' => $name],
                    ['value' => $value],
                );
            }

            $this->info("Submission {$submission->id}: wrote {$farmAttributes->count()} attribute(s) to entity {$submission->rootEntity->id}.");
        }

        $this->comment("Done. Processed {$submissions->count()} submission(s).");
    }
}
