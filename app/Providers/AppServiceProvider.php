<?php

namespace App\Providers;

use App\Models\Holpa\Domain;
use App\Models\Holpa\GlobalIndicator;
use App\Models\Holpa\Theme;
use App\Models\SampleFrame\Farm;
use App\Models\SampleFrame\FarmEntity;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Models\User;
use App\Policies\ChoiceListPolicy;
use App\Policies\DatasetPolicy;
use App\Policies\DatasetVariablePolicy;
use App\Policies\DomainPolicy;
use App\Policies\FarmEntityPolicy;
use App\Policies\FarmPolicy;
use App\Policies\GlobalIndicatorPolicy;
use App\Policies\LocationLevelPolicy;
use App\Policies\ProgramPolicy;
use App\Policies\TeamPolicy;
use App\Policies\ThemePolicy;
use App\Policies\UserPolicy;
use App\Policies\XlsformModulePolicy;
use App\Policies\XlsformModuleVersionPolicy;
use App\Policies\XlsformTemplatePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentTeamManagement\Models\Program;

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
        Gate::policy(Theme::class, ThemePolicy::class);
        Gate::policy(Program::class, ProgramPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ChoiceList::class, ChoiceListPolicy::class);
        Gate::policy(XlsformModule::class, XlsformModulePolicy::class);
        Gate::policy(XlsformModuleVersion::class, XlsformModuleVersionPolicy::class);
        Gate::policy(XlsformTemplate::class, XlsformTemplatePolicy::class);
        Gate::policy(Dataset::class, DatasetPolicy::class);
        Gate::policy(DatasetVariable::class, DatasetVariablePolicy::class);
        Gate::policy(LocationLevel::class, LocationLevelPolicy::class);
        Gate::policy(Farm::class, FarmPolicy::class);
        Gate::policy(FarmEntity::class, FarmEntityPolicy::class);

        // Enable migrations in subfolders
        $migrationsPath = database_path('migrations');
        $directories = glob($migrationsPath.'/*', GLOB_ONLYDIR);
        $paths = array_merge([$migrationsPath], $directories);

        $this->loadMigrationsFrom($paths);
    }
}
