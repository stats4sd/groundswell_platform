<?php

namespace App\Jobs;

use App\Models\Team;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Throwable;

/**
 * Starts the update of a team's test forms. The forms were already claimed by whoever dispatched this job.
 *
 * Only claims this job knows it holds are released here. If the job itself is killed or times out, nothing is
 * released: the form may belong to a chain that is already running, and an unreleased claim expires on its own.
 */
class DeployTeamDraftForms implements ShouldQueue
{
    use NotifiesOnJobFailure;
    use Queueable;

    public int $tries = 1;

    // Horizon's supervisor timeout is 60 seconds; failing on timeout lets failed() run instead of a silent kill.
    public int $timeout = 50;

    public bool $failOnTimeout = true;

    /**
     * @param  array<int, int>  $formIds
     */
    public function __construct(public Team $team, public array $formIds, public User $user) {}

    public function handle(): void
    {
        $xlsforms = Xlsform::withoutGlobalScopes()
            ->whereIn('id', $this->formIds)
            ->orderBy('id')
            ->get();

        $startedAt = microtime(true);

        try {
            $this->team->localiseXlsforms();
        } catch (Throwable $exception) {
            $xlsforms->each(fn (Xlsform $xlsform) => $xlsform->releaseClaim(needsUpdate: true));

            Log::error('Updating the test forms failed while localising the team modules', [
                'team_id' => $this->team->getKey(),
                'exception' => $exception,
            ]);

            $this->notifyJobFailure(
                'The test forms could not be updated',
                $exception,
                $this->user,
            );

            return;
        }

        Log::info('Test forms: team modules localised', [
            'team_id' => $this->team->getKey(),
            'seconds' => round(microtime(true) - $startedAt, 2),
        ]);

        $failedTitles = [];

        foreach ($xlsforms as $xlsform) {
            $formStartedAt = microtime(true);

            try {
                $xlsform->localiseModules();

                // the unset sends the queued chain now, inside the try
                $pendingDeployment = $xlsform->deployDraft(alreadyClaimed: true, user: $this->user);
                unset($pendingDeployment);
            } catch (Throwable $exception) {
                $xlsform->releaseClaim(needsUpdate: true);

                Log::error('Updating a test form failed before it was queued', [
                    'team_id' => $this->team->getKey(),
                    'xlsform_id' => $xlsform->getKey(),
                    'exception' => $exception,
                ]);

                $failedTitles[] = $xlsform->title;

                continue;
            }

            Log::info('Test forms: form queued', [
                'team_id' => $this->team->getKey(),
                'xlsform_id' => $xlsform->getKey(),
                'seconds' => round(microtime(true) - $formStartedAt, 2),
            ]);
        }

        if ($failedTitles === []) {
            return;
        }

        $titles = implode(', ', $failedTitles);

        Notification::make()
            ->title('Some test forms could not be updated')
            ->body("These forms could not be started: {$titles}. You can start the update again.")
            ->danger()
            ->persistent()
            ->sendToDatabase($this->user, isEventDispatched: true)
            ->broadcast($this->user);
    }

    public function failed(?Throwable $exception = null): void
    {
        Log::error('The job that updates the test forms did not finish', [
            'team_id' => $this->team->getKey(),
            'form_ids' => $this->formIds,
            'exception' => $exception,
        ]);

        $minutes = Xlsform::CLAIM_TTL_MINUTES;

        $this->notifyJobFailure(
            "The update of the test forms could not be finished. If a form still shows \"Updating\", you can start the update again within {$minutes} minutes.",
            $exception,
            $this->user,
        );
    }
}
