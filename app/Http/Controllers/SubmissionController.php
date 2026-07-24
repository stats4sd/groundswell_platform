<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;

class SubmissionController extends Controller
{
    // This function will be called when there are new submissions to be pulled from ODK central
    public static function process(Submission $submission): void
    {
        // check if test or live data
        /** @var Team $team */
        $team = $submission->xlsformVersion->xlsform->owner;

        if (! $team->pilot_complete) {
            $submission->test_data = true;
            $submission->save();
        }
    }
}
