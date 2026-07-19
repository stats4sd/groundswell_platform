<?php

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentTeamManagement\Models\Program;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// expect()->extend('toBeOne', function () {
//     return $this->toBe(1);
// });

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create $count `location` XlsformModules, each on its own XlsformTemplate.
 *
 * The module builders (LocationsModuleBuilder / FarmInfoModuleBuilder) loop over
 * every `location` XlsformModule and build one module version per module, so the
 * modules must exist before populate()/localiseXlsforms() is called.
 *
 * @return Collection<int, XlsformModule>
 */
function createLocationModules(int $count = 1): Collection
{
    return collect(range(1, $count))->map(function (int $i) {
        $template = XlsformTemplate::withoutEvents(
            fn () => XlsformTemplate::create([
                'title' => "Test Template {$i}",
                'available' => true,
            ])
        );

        // Creating the module fires its 'created' hook, which makes a default
        // "Global location" XlsformModuleVersion linked to this module.
        return XlsformModule::create([
            'xlsform_template_id' => $template->id,
            'label' => 'Location',
            'name' => 'location',
        ]);
    });
}

pest()->beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
})->in('Feature');

function createSuperAdmin(): User
{
    Http::fake();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Super Admin')->first());
    $user->load('roles', 'permissions');

    return $user;
}

function createAppUser(Team $team): User
{
    Http::fake();
    $user = User::factory()->create();
    TeamMembership::withoutEvents(fn () => $user->teams()->attach($team->id));
    $user->latest_team_id = $team->id;
    $user->roles()->attach(Role::where('name', 'Team Admin')->first());
    $user->save();

    return $user;
}

function withAdminPanel(): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
}

function withAppTenant(Team $team): void
{
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($team);
}

function withProgramTenant(Program $program): void
{
    Filament::setCurrentPanel(Filament::getPanel('program'));
    Filament::setTenant($program);
}

function createProgramAdmin(Program $program): User
{
    Http::fake();
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Program Admin')->first());
    $user->load('roles', 'permissions');
    $user->programs()->attach($program->id);
    $user->latest_program_id = $program->id;
    $user->save();

    return $user;
}
