# Smoke Test Plan

## Overview

43 tests across 5 files that assert every page returns HTTP 200 for the correct actor, and HTTP 302 (redirect) for unauthenticated guests. Tests live in `tests/Feature/Smoke/`.

---

## Test Infrastructure

### `tests/Pest.php` additions

Add beneath the existing `something()` stub:

**Global `beforeEach`** (flush Spatie permission cache between tests):
```php
pest()->beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
})->in('Feature');
```

**`createSuperAdmin()`**
```php
function createSuperAdmin(): \App\Models\User
{
    Http::fake(); // prevent ODK Central sync
    $user = \App\Models\User::factory()->create();
    $user->roles()->attach(\Spatie\Permission\Models\Role::where('name', 'Super Admin')->first());
    $user->load('roles', 'permissions');
    return $user;
}
```

**`createAppUser(Team $team)`**
```php
function createAppUser(\App\Models\Team $team): \App\Models\User
{
    Http::fake(); // prevent ODK Central sync
    $user = \App\Models\User::factory()->create();
    // Use withoutEvents to suppress TeamMembership::created ODK call
    \App\Models\TeamMembership::withoutEvents(fn () => $user->teams()->attach($team->id));
    $user->latest_team_id = $team->id;
    $user->save();
    return $user;
}
```

**`createProgramAdmin(Program $program)`**
```php
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
```

> **Why bypass `assignRole()` and `teams()->attach()`?**
> `User::assignRole()` overrides Spatie's method and calls `syncWithOdkCentral()` when the user is an admin, making live HTTP calls. `TeamMembership::created` does the same for project membership. Both blow up in tests because `ODK_URL` is null. Attaching roles directly via `roles()->attach()` and suppressing the pivot event bypasses both.

---

## Gotchas Summary

| Issue | Fix |
|---|---|
| `User::assignRole()` calls ODK Central | Use `$user->roles()->attach()` instead |
| `TeamMembership::created` calls ODK Central | Wrap in `TeamMembership::withoutEvents()` |
| `Team::created` boot hook calls ODK Central | Wrap factory call in `Team::withoutEvents()` |
| Spatie permission cache bleeds between tests | `forgetCachedPermissions()` in global `beforeEach` |
| `XlsformResource.php` is 0 bytes | No `/app/{tenant}/xlsforms` route exists — skip |
| `LocalIndicatorResource.php` is 0 bytes | No `/app/{tenant}/local-indicators` route exists — skip |

---

## File 1 — `tests/Feature/Smoke/PublicRoutesTest.php`

`describe('Public routes are accessible without authentication')`

No `actingAs()` on any test.

| Test name | URL | Assertions |
|---|---|---|
| `cover page loads` | `GET /` | `assertOk()` + `assertSeeLivewire(App\Livewire\CoverPage::class)` |
| `results page loads` | `GET /results` | `assertOk()` + `assertSeeLivewire(App\Livewire\ResultsPage::class)` |
| `temp results page loads` | `GET /temp-results` | `assertOk()` |
| `app login page loads` | `GET /app/login` | `assertOk()` |
| `register team page loads` | `GET /app/new` | `assertOk()` |
| `password reset request page loads` | `GET /app/password-reset/request` | `assertOk()` |
| `password reset reset page loads` | `GET /app/password-reset/reset` | `assertOk()` |

---

## File 2 — `tests/Feature/Smoke/AuthRedirectTest.php`

`describe('Protected routes redirect unauthenticated guests')`

No `actingAs()`. Placeholder tenant ID `1` is fine — the redirect fires before tenant resolution.

| Test name | URL | Assertion |
|---|---|---|
| `downloads route redirects guest` | `GET /downloads/test.pdf` | `assertRedirect()` |
| `admin dashboard redirects guest` | `GET /admin/dashboard` | `assertRedirect()` |
| `app panel redirects guest` | `GET /app/1/survey-dashboard` | `assertRedirect()` |
| `program panel redirects guest` | `GET /program/1` | `assertRedirect()` |

---

## File 3 — `tests/Feature/Smoke/AdminPanelTest.php`

`describe('Admin panel routes load for Super Admin')`

```php
beforeEach(function () {
    $this->superAdmin = createSuperAdmin();
});
```

All tests: `actingAs($this->superAdmin)->get($url)->assertOk()`

| Test name | URL |
|---|---|
| `admin dashboard loads` | `/admin/dashboard` |
| `domains list loads` | `/admin/domains` |
| `global indicators list loads` | `/admin/global-indicators` |
| `themes list loads` | `/admin/themes` |
| `programs list loads` | `/admin/programs` |
| `teams list loads` | `/admin/teams` |
| `users list loads` | `/admin/users` |
| `datasets list loads` | `/admin/datasets` |
| `xlsform templates list loads` | `/admin/xlsform-templates` |
| `xlsform modules list loads` | `/admin/xlsform-modules` |
| `xlsform module versions list loads` | `/admin/xlsform-module-versions` |
| `choice lists list loads` | `/admin/choice-lists` |

The Admin panel has no tenancy — no team attachment needed for the Super Admin user.

---

## File 4 — `tests/Feature/Smoke/AppPanelTest.php`

`describe('App panel routes load for authenticated team member')`

```php
beforeEach(function () {
    $this->team = Team::withoutEvents(fn () => Team::factory()->create());
    $this->user = createAppUser($this->team);
});
```

All tests: `actingAs($this->user)->get("/app/{$this->team->id}/{$slug}")->assertOk()`

### Custom pages (18 tests)

| Test name | Slug |
|---|---|
| `survey dashboard loads` | `survey-dashboard` |
| `set up survey loads` | `set-up-survey` |
| `monitor data collection loads` | `monitor-data-collection` |
| `data collection index loads` | `data-collection-index` |
| `data analysis index loads` | `data-analysis-index` |
| `context questions loads` | `context-questions` |
| `survey locations index loads` | `survey-locations-index` |
| `survey languages index loads` | `survey-languages-index` |
| `survey country loads` | `survey-country` |
| `survey translations loads` | `survey-translations` |
| `lisp index loads` | `lisp-index` |
| `lisp indicators loads` | `lisp-indicators` |
| `lisp workshop loads` | `lisp-workshop` |
| `pilot index loads` | `pilot-index` |
| `place adaptations index loads` | `place-adaptations-index` |
| `initial pilot loads` | `initial-pilot` |

### Resources (5 tests)

| Test name | URL |
|---|---|
| `submissions list loads` | `/app/{id}/submissions` |
| `teams list loads` | `/app/{id}/teams` |
| `location levels list loads` | `/app/{id}/location-levels/location-levels` |
| `farms list loads` | `/app/{id}/location-levels/farms` |
| `choice list entries list loads` | `/app/{id}/localisations/choice-list-entries` |

> **Cluster URL pattern**: `/{panel}/{tenant-id}/{cluster-slug}/{resource-slug}`. The `LocationLevels` cluster slug is `location-levels`; the `Localisations` cluster slug is `localisations`. URLs derived from class name kebab-case — no custom `$slug` properties found in any page or resource file.

---

## File 5 — `tests/Feature/Smoke/ProgramPanelTest.php`

`describe('Program panel routes load for Program Admin')`

```php
beforeEach(function () {
    $this->program = \Stats4sd\FilamentTeamManagement\Models\Program::create(['name' => 'Smoke Test Program']);
    $this->programAdmin = createProgramAdmin($this->program);
});
```

| Test name | URL | Assertion |
|---|---|---|
| `program panel dashboard loads` | `GET /program/{$this->program->id}` | `assertOk()` |
| `programs list loads` | `GET /program/{$this->program->id}/programs` | `assertOk()` |

---

## Running the tests

```bash
php artisan test --testsuite=Feature --filter=Smoke
```

All 43 tests should pass. Common failure modes:

| Symptom | Likely cause |
|---|---|
| `ConnectionException` | `Http::fake()` missing before a helper call |
| `403` on admin routes | Role not loaded — check `$user->load('roles', 'permissions')` |
| `302` on app panel pages | User not attached to team, or `latest_team_id` not set |
| `404` on cluster routes | Verify cluster slug matches class name in kebab-case |
