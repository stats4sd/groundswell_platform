<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure;
use Stats4sd\FilamentTeamManagement\Events\RegisteredWithData;
use Throwable;

/**
 * Deliberately NOT queued: the RegisteredWithData event carries the user's
 * plaintext password (needed for the ODK Central account), and queueing the
 * listener would serialise that password into the queue store and failed_jobs
 * table. Failures are caught so registration itself never breaks, and are
 * reported to Super Admins via a durable notification instead of being
 * silently swallowed.
 */
class RegisterNewUserToOdkCentral
{
    use NotifiesOnJobFailure;

    public function handle(RegisteredWithData $event): void
    {
        /** @var User $user */
        $user = $event->user;

        try {
            $user->registerOnOdkCentral($event->data['original_password']);
        } catch (Throwable $exception) {
            Log::error("Failed to register new user {$user->email} on ODK Central", ['exception' => $exception]);

            $this->notifyJobFailure(
                "ODK Central registration failed for new user: {$user->email}",
                $exception,
                $this->superAdmins(),
            );
        }
    }
}
