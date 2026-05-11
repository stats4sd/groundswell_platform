<?php

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

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
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

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

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

pest()->beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
})->in('Feature');

function createSuperAdmin(): \App\Models\User
{
    Http::fake();
    $user = \App\Models\User::factory()->create();
    $user->roles()->attach(\Spatie\Permission\Models\Role::where('name', 'Super Admin')->first());
    $user->load('roles', 'permissions');
    return $user;
}

function createAppUser(\App\Models\Team $team): \App\Models\User
{
    Http::fake();
    $user = \App\Models\User::factory()->create();
    \App\Models\TeamMembership::withoutEvents(fn () => $user->teams()->attach($team->id));
    $user->latest_team_id = $team->id;
    $user->save();
    return $user;
}

function createProgramAdmin(\Stats4sd\FilamentTeamManagement\Models\Program $program): \App\Models\User
{
    Http::fake();
    $user = \App\Models\User::factory()->create();
    $user->roles()->attach(\Spatie\Permission\Models\Role::where('name', 'Program Admin')->first());
    $user->load('roles', 'permissions');
    $user->programs()->attach($program->id);
    $user->latest_program_id = $program->id;
    $user->save();
    return $user;
}
