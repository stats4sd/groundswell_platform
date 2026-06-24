**Date**: 2026-06-24
**Branch**: `filament-5`
**Scope**: `HasXlsformTemplates` accessor bug + redundant `withoutEvents()` in four test files

## What broke

Loading the Team edit page in the Admin panel threw:

```
Attempt to read property "appUsers" on null
```

The error originated in the `odkQrCode` attribute accessor in `HasXlsformTemplates`:

```php
// packages/filament-odk-link/src/Models/OdkLink/Traits/HasXlsformTemplates.php:80
if (! $this->odkProject->appUsers->first()) {
```

When Filament's `EditRecord::mount()` calls `attributesToArray()` on the Team model, it eagerly evaluates every cast/accessor — including `odkQrCode`. If the Team has no associated `OdkProject`, `$this->odkProject` is `null` and dereferencing `->appUsers` on it throws a PHP warning that Laravel promotes to an exception.

## Root cause

`OdkProject` and its default `AppUser` are created atomically inside `createLinkedOdkProject()`, which is called from the `created` Eloquent event registered by `bootHasXlsformTemplates()`. However, that boot method has an early-return guard:

```php
// line 19
if (config('filament-odk-link.odk.url') === null || config('filament-odk-link.odk.url') == '') {
    return;
}
```

In the test environment `phpunit.xml` sets `ODK_URL=""`, so the guard fires and the `created` listener is never registered. Every Team created in tests therefore has no `OdkProject`. The same applies to any deployment running in local-only (no ODK Central) mode.

In production where ODK_URL is configured, `OdkProject` and `AppUser` are always created together; an `OdkProject` with no `AppUser` should not occur. The accessor's original `appUsers->first()` guard was therefore checking the wrong thing — it assumed `$this->odkProject` was always non-null.

## Changes

### `packages/filament-odk-link/src/Models/OdkLink/Traits/HasXlsformTemplates.php`

Added a null guard for `$this->odkProject` before accessing `appUsers`:

```php
// before
if (! $this->odkProject->appUsers->first()) {

// after
if (! $this->odkProject || ! $this->odkProject->appUsers->first()) {
```

This makes `odkQrCode` return `null` gracefully for Teams without an OdkProject (test environment, local-only mode, or any edge-case where ODK setup has not completed).

### `tests/Feature/Crud/AdminPanelCrudTest.php`

Removed `Team::withoutEvents()` from the "team view page loads" and "team edit page loads" tests and replaced with the `Http::fake()` pattern used by the "can create team" test:

```php
// before
$team = Team::withoutEvents(fn () => Team::factory()->create());

// after
Http::fake();
$team = Team::factory()->create();
```

`withoutEvents()` was redundant: because `ODK_URL` is empty in phpunit.xml, the boot hook's early-return fires before the `created` listener is registered, so no ODK calls are attempted regardless. Suppressing events gave the false impression that ODK event side-effects needed to be manually avoided, and obscured that the real fix was the null guard in the accessor. Using `Http::fake()` instead is consistent with the rest of the Team tests and correctly future-proofs against a non-empty test `ODK_URL`.

### `tests/Feature/Smoke/AppPanelTest.php` and `tests/Feature/Crud/AppPanelCrudTest.php`

Applied the same `withoutEvents()` → `Http::fake()` replacement across the `beforeEach` blocks in both App panel test files (1 block in `AppPanelTest.php`, 5 blocks in `AppPanelCrudTest.php`).

These files had an additional wrinkle: after suppressing events, each `beforeEach` manually created `localContextModuleVersion` as a workaround, because that relationship is normally created by the `Team::booted()` `created` hook (which was being suppressed):

```php
// before
$this->team = Team::withoutEvents(fn () => Team::factory()->create());
$this->team->localContextModuleVersion()->create(['name' => 'Local Context']);

// after
Http::fake();
$this->team = Team::factory()->create();
```

With `withoutEvents()` removed, the `Team::booted()` hook fires naturally and creates `localContextModuleVersion` itself. The `bootHasXlsformTemplates()` ODK hook still early-returns (empty `ODK_URL`), so no HTTP calls are made. The manual `localContextModuleVersion()->create()` line is therefore redundant and was removed.

Three tests in these files were already failing before these changes (`monitor data collection loads`, `can create location level via table action`, `can bulk delete location level`) and remain so — they are unrelated Filament API compatibility issues.
