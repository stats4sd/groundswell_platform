# Filament 3→5 / Livewire 3→4 / Laravel 11→13 upgrade — change log

Records the work done on the `filament-4` branch to execute [the upgrade plan](../archive/plans/filament-5-livewire-4-laravel-13-upgrade.md). The plan's "single combined update" landed: the root app is on Filament 5, Livewire 4 and Laravel 13, the codemods have run, and the app boots. Remaining work (validation to green, Tailwind v4) and the blockers found are tracked in [docs/issues/filament-5-upgrade-remaining.md](../issues/filament-5-upgrade-remaining.md).

## Installed framework versions (after `composer update -W`)

- `filament/filament` v3.3.52 → **v5.6.7**
- `laravel/framework` v11.54.0 → **v13.17.0**
- `livewire/livewire` v3.8.0 → **v4.3.1**

## Commits (discrete steps, per plan risk note line 140)

Each step is a separate commit so `git` history separates the code-change classes, even though intermediate commits are not individually runnable.

1. **`ade43db` Drop `joserick/laravel-livewire-discover`** — already inert (config was the unedited placeholder; the provider class never existed). Removed the require, the `bootstrap/providers.php` registration, and `config/livewire-discover.php`. Components resolve via Livewire's default convention discovery.
2. **`215bb3e` Replace `awcodes/shout` with native `Callout`** — rewrote all 5 root-app `Shout` usages to `Filament\Schemas\Components\Callout` (`->info()` / `->description()`) and removed the require. Sites: `app/Livewire/CoverPage.php`, `app/Filament/App/Pages/Auth/EditProfile.php`, `app/Filament/Admin/Resources/UserResource/Pages/ListUsers.php`, `app/Filament/Admin/Resources/TeamResource/RelationManagers/UsersRelationManager.php`, `app/Filament/App/Resources/TeamResource/RelationManagers/UsersRelationManager.php` (two callouts).
3. **`ed0f06e` Replace `filament-local-logins` with `dutchcodingcompany/filament-developer-logins`** — the plan linked the plugin as `bramr94/...`; the real Packagist name is `dutchcodingcompany/filament-developer-logins` (bramr94 is the author). Filament-5 line is `^2.1`. Registered `FilamentDeveloperLoginsPlugin` in `AppPanelProvider`, porting the `ADMIN_PANEL_LOCAL_LOGINS_ENABLED` gate into `->enabled(env(..., app()->environment('local')))` and the `ADMIN_PANEL_LOCAL_LOGIN_EMAILS` list into `->users(...)`. Dropped the `HasLocalLogins` trait from the custom `Login` page (the page is retained for its ODK-Central registration in `authenticate()`). Removed `config/filament-local-logins.php` and the require.
4. **`69d60a4` Drop `awcodes/filament-table-repeater`** — no code change needed: odk-link's F5 migration and the app-level `WithXlsformModuleVersionQuestionEditing` trait already use the native `Filament\Forms\Components\Repeater`. Only the orphaned require remained.
5. **`dd268ee` Bump composer constraints** — `filament/filament:^5.0`, `laravel/framework:^13.0`, `livewire/livewire:^4.0`, `althinect/filament-spatie-roles-permissions:^3.1` (declares Filament `^4|^5` and satisfies team-management's `^3.0`; the plan's `^4/^5` guess would not have resolved), `eightynine/filament-excel-import:4.x-dev` (the only line declaring Filament `^5`; v4.0.0 stable is Filament `^4` only), `rappasoft/laravel-authentication-log:^6.1` and `laravel/tinker:^3.0` (L13), pest plugins `^4.0`, `symfony/http-client`+`symfony/mailgun-mailer` `^7.1|^8.0` (L13 pulls Symfony 8).
6. **`5fb6692` `composer update -W`** — installed the lockfile/vendor on F5/LW4/L13. (The `post-autoload-dump` artisan hook fails until the app code is migrated; the package install itself completed.)
7. **`4c5f3aa` `vendor/bin/filament-v4` codemod** — namespace/signature migration across 80 `app/` files (`Filament\Schemas\Schema`, `form(Schema $schema)`, `Get`/`Set` → `Schemas\Components\Utilities`, `Filament\Actions\*` action classes, `->form()` → `->schema()`).
8. **`5894c5f` Widen `EditProfile::$navigationIcon`** to `string|BackedEnum|null` — the v4 codemod widened this property on every page except the auth `EditProfile` subclass; the parent now declares `string|BackedEnum|null`, so the `?string` override was a fatal covariance error. The app boots after this.
9. **`109e701` Fix F5/Livewire-4 view breaking changes** — (a) custom resource-page blades: replaced the F5-removed `filament-panels` components (`resources.tabs`, `form`, `form.actions`, `resources.relation-managers`) with F5 schema rendering — `{{ $this->content }}`, a native `<form>` + form-action loop, and `{{ $this->getRelationManagersContentComponent() }}`. (b) Published `config/livewire.php` and set `component_layout` to `components.layouts.app`: Livewire 4's new default is the unregistered `layouts::app` namespace, but this app's full-page layout is the LW3-style `resources/views/components/layouts/app.blade.php`. Fixed `No hint path [layouts]` on the public routes — `PublicRoutesTest` now passes 7/7.

`vendor/bin/filament-v5` (the Livewire-4 compatibility pass) was also run; it produced **no** changes to `app/` — as the plan predicted (Filament 4→5 is purely Livewire-4 compat with no functional changes for this codebase).

## Submodule change made out-of-tree (needs committing in its own repo)

- `packages/laravel-shiny-loader/composer.json` — widened `illuminate/contracts` from `^11.0||^12.0` to `^11.0||^12.0||^13.0` so the path package resolves on Laravel 13. This is an **uncommitted change inside the submodule working tree** (the root repo only tracks the gitlink commit). It must be committed in the `laravel-shiny-loader` repo and the submodule pointer updated, or it will be lost on the next submodule update.

## Validation status at hand-off

`./vendor/bin/pest`: **33 passed, 76 failed**. The app boots (`php artisan about` works) and the public/auth/login routes pass. The remaining failures are concentrated in a small number of root causes — most outside the root-app scope (team-management/odk-link package state, and pre-existing stale tests). See the issues doc.

`phpstan`, `pint`, `npm run build` and the Tailwind v4 theme port (plan step 5) have **not** been run/done yet.

## Uncommitted working-tree state at hand-off

- `composer.json` / `composer.lock` — `filament/upgrade:^5.0` is still in `require-dev` (only needed to run the codemods; remove with `composer remove --dev filament/upgrade` once no further codemod runs are needed).
- `public/**` — re-published Filament/Livewire static assets (from `vendor:publish --tag=laravel-assets` run by composer) and a new `public/fonts/` directory. Commit alongside the build once Tailwind v4 is done.
- `.claude/settings*.json`, `docs/plans/...md` — pre-existing session edits, unrelated to this work.
