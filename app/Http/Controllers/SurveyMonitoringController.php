<?php

namespace App\Http\Controllers;

use App\Models\SampleFrame\Farm;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Spatie\MediaLibrary\Support\MediaStream;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

// TODO - generalise this and bring it into the package. Keep a custom version here for HOLPA-specific overrides.
class SurveyMonitoringController extends Controller
{
    /**
     * @param  Team  $team  - the team to get the submission summary for
     * @param  string  $isTest  - whether to get the submission summary for the test form or the live form (test/live) - NOTE: In this setup, it is assumed there are *only* 2 forms per team - one that is marked as test and one that is marked as live.
     * @return array - containing the count of submissions and the date of the latest submission
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function getSubmissionSummary(Team $team, string $isTest): array
    {
        [$xlsform, $odkLinkService] = $this->getFormAndLinkService($team, $isTest === 'test');

        $householdXlsform = $team->xlsforms->where('title', 'HOLPA Household Survey')->first();
        $fieldworkXlsform = $team->xlsforms->where('title', 'HOLPA Fieldwork Survey')->first();

        $token = $odkLinkService->authenticate();
        $endpoint = config('filament-odk-link.odk.base_endpoint');

        $householdResults = Http::withToken($token)
            ->get("{$endpoint}/projects/{$householdXlsform->owner->odkProject->id}/forms/{$householdXlsform->odk_id}/submissions")
            ->throw()
            ->json();

        $fieldworkResults = Http::withToken($token)
            ->get("{$endpoint}/projects/{$fieldworkXlsform->owner->odkProject->id}/forms/{$fieldworkXlsform->odk_id}/submissions")
            ->throw()
            ->json();

        $householdCount = count($householdResults);
        $fieldworkCount = count($fieldworkResults);

        $latestHouseholdSubmission = collect($householdResults)
            ->sort(fn ($submission) => $submission['createdAt'])
            ->last();

        $latestFieldworkSubmission = collect($fieldworkResults)
            ->sort(fn ($submission) => $submission['createdAt'])
            ->last();

        if ($householdCount === 0 || $fieldworkCount === 0) {
            return [
                'householdCount' => 0,
                'fieldworkCount' => 0,
                'latestHouseholdSubmissionDate' => 'no submissions yet',
                'latestFieldworkSubmissionDate' => 'no submissions yet',

                'successfulSurveys' => 0,
                'surveysWithoutRespondentPresent' => 0,
                'surveysWithNonConsentingRespondent' => 0,
                'farmsFullySurvey' => 0,
            ];
        }

        // Submission Summary based on pulled data
        $submissions = Submission::all();

        // temporary comment below code
        // short summary content to be determined for HOLPA

        // $successfulSurveys = $submissions->filter(fn (Submission $submission) => $submission->content['reg']['respondent_check']['respondent_available'] === "1" &&
        //     $submission->content['consent_grp']['consent'] === "1");

        // $surveysWithoutRespondentPresent = $submissions->filter(fn (Submission $submission) => $submission->content['reg']['respondent_check']['respondent_available'] === "0")->count();

        // $surveysWithNonConsentingRespondent = $submissions->filter(fn (Submission $submission) => $submission->content['consent_grp']['consent'] === "0")->count();

        // TODO: find all farms ID
        // hardcode temporary for testing
        $farmsSurveyed = [1, 2];

        // find number of farms that completed household form and fieldwork form
        $farmsFullySurveyed = Farm::whereIn('id', $farmsSurveyed)->where('household_form_completed', 1)->where('fieldwork_form_completed', 1)->count();

        // hardcode all figures to 0 for testing temporary
        $successfulSurveys = $submissions;
        $surveysWithoutRespondentPresent = 0;
        $surveysWithNonConsentingRespondent = 0;
        $farmsFullySurvey = $farmsFullySurveyed;

        return [
            'householdCount' => $householdCount,
            'fieldworkCount' => $fieldworkCount,
            'latestHouseholdSubmissionDate' => (new Carbon($latestHouseholdSubmission['createdAt']))->format('Y-m-d H:i:s'),
            'latestFieldworkSubmissionDate' => (new Carbon($latestFieldworkSubmission['createdAt']))->format('Y-m-d H:i:s'),
            'successfulSurveys' => $successfulSurveys->count(),
            'surveysWithoutRespondentPresent' => $surveysWithoutRespondentPresent,
            'surveysWithNonConsentingRespondent' => $surveysWithNonConsentingRespondent,
            'farmsFullySurvey' => $farmsFullySurvey,

        ];
    }

    /*
     * Function to download the submissions from ODK Central. The submission data are retrieved directly from the API and returned exactly as if you downloaded the data manually from ODK Central.
     * @param Team $team - which team?
     * @param string $isTest - retrieve the test or live form?
     * @return \Illuminate\Http\Response (zip file containing the csv data)
     */
    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function downloadData(Team $team, string $isTest): Application|Response|ResponseFactory
    {
        [$xlsform, $odkLinkService] = $this->getFormAndLinkService($team, $isTest === 'test');
        $token = $odkLinkService->authenticate();
        $endpoint = config('odk-link.odk.base_endpoint');

        $results = Http::withToken($token)
            ->get("{$endpoint}/projects/{$xlsform->owner->odkProject->id}/forms/{$xlsform->odk_id}/submissions.csv.zip")
            ->throw();

        return response($results->body(), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="submissions.csv.zip"',
        ]);
    }

    /**
     * Function to retrieve the XLSForm model and the Link Service class instance. This is a helper function to avoid repeating the same code in multiple functions.
     *
     * @param  Team  $team  - which team?
     * @param  $isTest  - retrieve the test or live form?L
     *
     * @throws BindingResolutionException
     */
    public function getFormAndLinkService(Team $team, $isTest): array
    {
        $xlsform = $team->xlsforms->where('is_test', $isTest)->first();
        $odkLinkService = app()->make(OdkLinkService::class);

        return [$xlsform, $odkLinkService];
    }

    /**
     * Function to download all attached media from the submissions. This assumes the submissions (and their media) have already been pulled from ODK Central. This function will zip all the media files and return a MediaStream object.
     *
     * @param  Team  $team  - which team?
     * @param  string  $isTest  - retrieve the test or live form?
     *
     * @throws BindingResolutionException
     */
    public function downloadAttachedMedia(Team $team, string $isTest): MediaStream
    {
        [$xlsform, $odkLinkService] = $this->getFormAndLinkService($team, $isTest === 'test');

        $downloads = [];

        foreach ($xlsform->submissions as $submission) {
            foreach ($submission->getMedia() as $media) {
                $downloads[] = $media;
            }
        }

        return MediaStream::create('map-survey-attachments.zip')->addMedia($downloads);
    }
}
