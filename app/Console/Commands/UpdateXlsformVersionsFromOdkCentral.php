<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

// TODO: move into package?
class UpdateXlsformVersionsFromOdkCentral extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-xlsform-versions-from-odk-central';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'A command to use when testing ODK Central integration. In cases where the version of the xlsform is updated on ODK Central without being updated locally, this command will update the xlsform_versions table locally with data from ODK Central';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $this->info('Updating xlsform versions from ODK Central');

        // for each xlsform, get all the versions from ODK Central
        Xlsform::with('owner')->get()->each(function (Xlsform $xlsform) {

            $odkLinkService = app()->make(OdkLinkService::class);

            $baseUrl = config('filament-odk-link.odk.base_endpoint');
            $projectId = $xlsform->owner->odkProject->id;
            $formId = $xlsform->odk_id;

            $versions = Http::withToken($odkLinkService->authenticate())
                ->get($baseUrl . '/projects/' . $projectId . '/forms/' . $formId . '/versions')
                ->throw()
                ->json();


            dump($xlsform->owner->name . ' - ' . $xlsform->odk_id);
            dump($versions);

            collect($versions)->each(function ($version) use ($xlsform) {
                $versionName = $version['version'];

                if ($xlsform->xlsformVersions()->where('odk_version', $versionName)->count() === 0) {
                    $xlsform->xlsformVersions()->create([
                        'version' => $versionName,
                        'odk_version' => $versionName,
                        // schema will be null, but I don't think we use the xlsform version schema at the moment.
                    ]);
                }

                // if the version has an enketoId, it is the active version
                if (isset($version['enketoId'])) {
                    $xlsform->update([
                        'odk_published_at' => Carbon::make($version['publishedAt']),
                        'is_active' => true,
                        'enketo_id' => $version['enketoId'],
                    ]);
                }

            });




            // create a draft version
            $draft = Http::withToken($odkLinkService->authenticate())
                ->get($baseUrl . '/projects/' . $projectId . '/forms/' . $formId . '/draft');

            if ($draft->status() === 404) {
                // create draft
                $xlsform->deployDraft();
                $this->alert('NOTE - The form with id ' . $xlsform->id . ' does not have a draft version on ODK Central. Deployment has been queued. Please run the job queue to deploy the draft version, and then re-run "php artisan app:update-xlsform-versions-from-odk-central"');
            }

            if ($draft->ok()) {

                $draft = $draft
                    ->json();

                $xlsform->xlsformVersions()->updateOrCreate([
                    'is_draft' => true,
                ],
                    [
                        'version' => $draft['version'],
                        'odk_version' => $draft['version'],
                    ],
                );

                // update draft status of xlsform
                $xlsform->update([
                    'enketo_draft_id' => $draft['enketoId'] ?? null,
                    'odk_draft_updated_at' => Carbon::make($draft['updatedAt']) ?? null,
                    'has_draft' => true,
                ]);
            }


        });

        $this->info('Xlsform versions updated successfully!');






    }
}
