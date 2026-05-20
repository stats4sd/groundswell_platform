<?php

namespace App\Providers;

use App\Filament\App\Pages\SurveyDashboard;
use App\Models\Holpa\Domain;
use App\Models\Holpa\GlobalIndicator;
use App\Policies\DomainPolicy;
use App\Policies\GlobalIndicatorPolicy;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // unguard all models at once, so that filament-odk-link package XlsformTemplate model can be created successfully
        Model::unguard();

        // Implicitly grant "Super Admin" role all permissions, bypassing all policy checks.
        // Global Viewer is intentionally excluded — their access is controlled per-resource via policies.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        // Explicit policy registrations for models outside the App\Models namespace,
        // since Laravel's auto-discovery won't match them by convention.
        Gate::policy(Domain::class, DomainPolicy::class);
        Gate::policy(GlobalIndicator::class, GlobalIndicatorPolicy::class);

        // Enable migrations in subfolders
        $migrationsPath = database_path('migrations');
        $directories = glob($migrationsPath.'/*', GLOB_ONLYDIR);
        $paths = array_merge([$migrationsPath], $directories);

        $this->loadMigrationsFrom($paths);

    }

}
