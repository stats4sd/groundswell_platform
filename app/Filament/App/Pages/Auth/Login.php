<?php

namespace App\Filament\App\Pages\Auth;

use BetterFuturesStudio\FilamentLocalLogins\Concerns\HasLocalLogins;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithOdkCentralAccount;

class Login extends \Filament\Pages\Auth\Login
{
    use HasLocalLogins;

    /**
     * @throws RequestException
     * @throws BindingResolutionException
     * @throws ConnectionException
     */
    public function authenticate(): ?LoginResponse
    {

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        if (! $user->odk_id && $user instanceof WithOdkCentralAccount) {
            $user->registerOnOdkCentral($this->getCredentialsFromFormData($data)['password']);
        }

        return app(LoginResponse::class);
    }
}
