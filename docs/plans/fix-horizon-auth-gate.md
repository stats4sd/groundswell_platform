# Plan: Fix Horizon's auth gate

**Status:** Not Started

## Problem

`app/Providers/HorizonServiceProvider.php:32-34` defines a gate named `viewTelescope` instead of `viewHorizon` — a copy-paste from the Telescope provider. Horizon's dashboard authorisation looks for the `viewHorizon` gate; since it is never defined, Horizon falls back to local-environment-only access, so `/horizon` is inaccessible in every deployed environment. This matters because Horizon is currently the only failed-jobs UI in the system, and the wider error-visibility work ([job-failure-db-notifications.md](job-failure-db-notifications.md)) leans on it as the admin debugging surface.

There is also a real `viewTelescope` gate defined in `app/Providers/TelescopeServiceProvider.php:58` — the duplicate definition in the Horizon provider silently competes with it (last registration wins), so the fix also removes that ambiguity.

## Change

In `app/Providers/HorizonServiceProvider.php`:

- Rename the gate at `:32-34` from `viewTelescope` to `viewHorizon`, keeping the existing check (`Super Admin` role), consistent with the Telescope gate's check.

Not in scope (note only): the commented-out Horizon failure-notification routes at `HorizonServiceProvider.php:18-20` stay commented; enabling them is an ops decision tracked in the job-failure plan's out-of-scope list.

## Verification

1. Local: `php artisan horizon` running, log in as a Super Admin, load `/horizon` — accessible. Log in as a non-admin — 403.
2. Feature test (`tests/Feature/Smoke/HorizonAccessTest.php`): `get('/horizon')` as Super Admin asserts OK; as a plain user asserts forbidden. This pins the gate name so the copy-paste cannot regress.
3. `./vendor/bin/pest`, `./vendor/bin/phpstan analyse`.
