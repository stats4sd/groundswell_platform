# Change log: Fix Horizon's auth gate

Implements [fix-horizon-auth-gate.md](../plans/fix-horizon-auth-gate.md).

## Changes

**`app/Providers/HorizonServiceProvider.php`** — renamed the gate defined in `gate()` from `viewTelescope` to `viewHorizon`. The check is unchanged (`$user->hasRole('Super Admin')`).

The copy-pasted name meant `viewHorizon` was never defined, so `Horizon::auth()` (`Gate::check('viewHorizon', ...) || app()->environment('local')`) only ever passed locally and `/horizon` was inaccessible in every deployed environment. It also shadowed the real `viewTelescope` gate in `TelescopeServiceProvider`, since the last registration wins.

**`tests/Feature/Smoke/HorizonAccessTest.php`** (new) — three cases pinning the gate name so the mistake cannot silently return:

- Super Admin gets 200 from `/horizon`
- a plain authenticated user gets 403
- a guest gets 403

The guest case passes because the gate closure takes an untyped `$user` parameter, which Laravel treats as not allowing guests.

## Out of scope

The commented-out `Horizon::route*NotificationsTo()` calls in `HorizonServiceProvider::boot()` remain commented; enabling them is an ops decision tracked in [job-failure-db-notifications.md](../plans/job-failure-db-notifications.md).

## Verification

- `./vendor/bin/pest` — 185 passed (412 assertions)
- `./vendor/bin/phpstan analyse` — no new errors (170 pre-existing, none Horizon-related)
- `./vendor/bin/pint` — passed
