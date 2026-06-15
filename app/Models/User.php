<?php

namespace App\Models;

use Filament\Notifications\Notification;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithOdkCentralAccount;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasOdkCentralAccount;
use Stats4sd\FilamentTeamManagement\Mail\InviteUser;
use Stats4sd\FilamentTeamManagement\Models\Invite;
use Stats4sd\FilamentTeamManagement\Models\User as FilamentTeamManagementUser;

class User extends FilamentTeamManagementUser implements WithOdkCentralAccount
{
    use HasFactory;
    use HasOdkCentralAccount;
    use AuthenticationLoggable;

    /**
     * @throws RequestException
     * @throws BindingResolutionException
     * @throws ConnectionException
     */
    public function assignRole(...$roles): void
    {
        parent::assignRole(...$roles);

        if ($this->isAdmin()) {
            $this->syncWithOdkCentral();
        }
    }

    public function teams(): BelongsToMany
    {
        return parent::teams()
            ->using(TeamMembership::class);
    }

    /** @return  HasMany<TeamMembership, $this> */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    /**
     * Generate an invitation to be a role for each of the provided email addresses.
     *
     * Extends the base implementation to also link the invite to a program or team,
     * so that "Program Admin"/"Program Viewer" and "Team Admin" invitees are
     * automatically attached to the selected program/team when they register.
     */
    public function sendInvites(array $items): void
    {
        foreach ($items as $item) {
            // if email is empty, skip to next email
            if ($item['email'] == null || $item['email'] == '') {
                continue;
            }

            /** @var Invite $invite */
            $invite = $this->invites()->create([
                'email' => $item['email'],
                'role_id' => $item['role'],
                'program_id' => $item['program_id'] ?? null,
                'team_id' => $item['team_id'] ?? null,
                'token' => Str::random(24),
            ]);

            Mail::to($invite->email)->send(new InviteUser($invite));

            // show notification after sending invitation email to user
            Notification::make()
                ->success()
                ->title('Invitation Sent')
                ->body('An email invitation has been successfully sent to ' . $item['email'])
                ->send();
        }
    }
}
