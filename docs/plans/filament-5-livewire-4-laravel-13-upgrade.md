# Upgrade: Filament 3→5, Livewire 3→4, Laravel 11→13 (single combined update)

## Context

The platform runs Filament 3.3 / Livewire 3 / Laravel 11 (PHP already `^8.4`). The goal is Filament 5 + Livewire 4 + Laravel 13. The work is **mostly the Filament 3→4 jump** — Filament 4→5 is purely Livewire-4 compatibility (an automated script, no functional changes per the [v5 release notes](https://laravel-news.com/filament-5)), Livewire 3→4 is small, and Laravel 11→12→13 is a sequence of low-breakage steps ([Laravel 13](https://laravel.com/docs/13.x/releases) shipped with "zero breaking changes", min PHP 8.3 — already satisfied).

This `filament-4` branch is **already mid-migration**: the `filament-odk-link` submodule (upstream `dev` branch) has been migrated to Filament 4 (22 src files use `Filament\Schemas`, only 3 stragglers reference `Filament\Forms\Form`). The `filament-team-management` submodule has been **bumped to its Filament 5 line** and its restructuring already reflected in the host app — see [`filament-team-management-f5-reference-fixes.md`](../change-logs/filament-team-management-f5-reference-fixes.md) (Admin resources moved into per-resource subdirectories; the Program panel moved from a `ProgramResource` to a `ManageProgram` `EditTenantProfile` page; host-app references + the two affected CRUD tests rewritten). The **root app** (`app/Filament/`, 0 files on `Filament\Schemas`, 10 still on `Filament\Forms\Form`) is **not** yet migrated. Because all Filament code shares one resolved `filament/filament` version via the path-repo symlinks, root + both packages + all third-party plugins must reach their target versions before `composer update` can land.

## Sequencing decision: one update, validate at the end

**This is not a staged migration.** The team-management submodule has already been migrated against the package's **Filament 5** structure, and the host-app reference fixes that go with it are already committed. That means the repo cannot return to a green, testable state at any intermediate version — `pest`/`phpstan` cannot run while installed Filament is v3 (the package requires v5), and stopping at Filament 4 would leave the team-management F5 references broken. There is **no useful checkpoint** between "here" and "Filament 5 + Livewire 4 + Laravel 13".

So the plan drives all three frameworks to their targets in one pass, applies every code change the upgrades require, and **validates everything only at the end**. We expect bugs at first green-attempt; those are fixed *after* the update lands, not interleaved with it. Package submodule migrations (finishing odk-link's 3 stragglers, releasing/aligning the F5 tags) are handled separately by Dave; this plan owns the root app and treats compatible package versions as a prerequisite gate.

## Ground-truth inventory (root app)

*   11 Resources, ~42 Pages, 3 Widgets, 7 RelationManagers, 2 Shared traits — 72 Filament PHP files across Admin/App/Program panels.
*   Layout components in use: `Section` (~13), `Fieldset` (3, incl. responsive `columns(['sm'=>1,'md'=>2,'lg'=>2])`), `columns()` (~30), `columnSpanFull()` (8), `columnSpan()` (~2). No `Grid`/`Split`.
*   10 files use `form(Form $form)` / `Filament\Forms\Form`; 5 use `Filament\Forms\{Get,Set}`. **No** custom Field/Column/Entry classes overriding `make()`.
*   `FileUpload` ×3 — all on the **local default disk** (Excel import temp files), no S3/`ImageColumn`/`ImageEntry` → the v4 private-visibility default is a non-issue.
*   Multi-tenancy: App panel (`Team`), Program panel (`Program`), Admin (none). 2 App clusters.
*   13 custom Livewire components (`app/Livewire/`), all modern attribute syntax (`#[Reactive]`, `#[Url]`, `#[Computed]`, `#[On]`), self-closing `<livewire:…/>` tags. **No** `wire:model.defer/.lazy`, `wire:scroll`, `wire:transition`, or `Route::livewire()`. One custom Alpine plugin (`resources/js/sortable.js`).
*   Custom Filament theme: `resources/css/filament/app/theme.css` + `tailwind.config.js` (Tailwind **3.4.15** — needs Tailwind v4 migration). Vue 3.5 / Vite 6 frontend is already modern.
*   Reverb/Echo configured but broadcasting driver is `null` (inactive).

## Pre-flight gate (Dave — package/plugin versions)

The single `composer update` cannot resolve until **all** Filament code is F5-compatible and every plugin has a Filament-5 / Laravel-13 line. Confirm and pin a compatible version for every row below **before** starting; the four "remove" rows are **code changes, not bumps** and must be done in the same pass.

| Dependency | Now | Needs | Status / decision |
| --- | --- | --- | --- |
| `stats4sd/filament-odk-link` (submodule) | `^4.0` | F5 tag (finish 3 straggler files) | Dave — upstream `dev` |
| `stats4sd/filament-team-management` (submodule) | `^3.0` → F5 line | F5 tag | **migrated; host-app refs already updated** (see [change-log](../change-logs/filament-team-management-f5-reference-fixes.md)) |
| `althinect/filament-spatie-roles-permissions` | `^2.2` | `^4.0`/`^5.0` for F5 | version bump |
| `eightynine/filament-excel-import` | `3.x-dev` | F5-compatible stable line | confirm a release supports F5; the import-action API may shift |
| `awcodes/shout` | `^2.0` | **remove** | replace with native `Callout` (Filament `Schemas\Components\Callout`) |
| `awcodes/filament-table-repeater` | `^3.1` | **remove** | replace with native Repeater table layout (odk-link trait) |
| `better-futures-studio/filament-local-logins` | `^1.2` | **remove** | replace with [`bramr94/filament-developer-logins`](https://filamentphp.com/plugins/bramr94-developer-logins) |
| `joserick/laravel-livewire-discover` | `^1.3` | **remove** | already inert; rely on Livewire default discovery, [LW4 namespaces](https://livewire.laravel.com/docs/4.x/components#organizing-components) if needed |
| `laravel/framework` | `^11` | `^13.0` | min PHP 8.3 satisfied at 8.4 |
| Livewire | 3 | 4 | pulled in via Filament 5 |
| First-party Laravel pkgs (`horizon`, `reverb`, `telescope`, `tinker`, `sentry/sentry-laravel`, `nesbot/carbon ^3`, `maatwebsite/excel`, `rappasoft/laravel-authentication-log`, staudenmeir deep-relation pkgs, `shiftonelabs/*`) | `^11`/`^12` | resolve on 13 | bump any that constrain `^11`/`^12` only |

**Gate:** every version-bump row has a confirmed, pinned target that resolves together with `filament/filament:^5.0` and `laravel/framework:^13.0`; the four removal rows have their replacement code ready to write. Do not start the update until this resolves on paper (`composer update --dry-run -W` against the drafted constraints).

## The update (one pass — no testing until Validation)

### 1. Code changes that don't need the new packages installed first

Do these against the current tree so `composer update` has nothing dangling to resolve:

*   **Drop `joserick/laravel-livewire-discover`.** It is already inert: `config/livewire-discover.php` is the unedited placeholder and its provider `App\Providers\LivewireDiscoverServiceProvider` is listed in `bootstrap/providers.php` but the class file does not exist. Remove the require, delete the provider line from `bootstrap/providers.php`, delete `config/livewire-discover.php`. Existing components (`app/Livewire/DataCollection/*`, `app/Livewire/SurveyLanguages/*`) already resolve via Livewire's default convention-based discovery (`data-collection.*`, `survey-languages.*`) — no functional change.
*   **`awcodes/shout` → native `Callout`** (`Filament\Schemas\Components\Callout`). Rewrite the 5 root-app usages — `app/Livewire/CoverPage.php`, `app/Filament/App/Resources/TeamResource/RelationManagers/UsersRelationManager.php`, `app/Filament/App/Pages/Auth/EditProfile.php`, `app/Filament/Admin/Resources/UserResource/Pages/ListUsers.php`, `app/Filament/Admin/Resources/TeamResource/RelationManagers/UsersRelationManager.php`. (Shout is no longer used in odk-link after its latest dev version — Dave.) Remove the `awcodes/shout` require. *Note: `Callout` is a Filament 4.2+ class, so these edits only compile after `composer update` lands — write them now but expect red until step 3.*
*   **`better-futures-studio/filament-local-logins` → [`bramr94/filament-developer-logins`](https://filamentphp.com/plugins/bramr94-developer-logins).** Three sites: the plugin registration in `app/Providers/Filament/AppPanelProvider.php` (`new LocalLogins`), the `HasLocalLogins` trait on `app/Filament/App/Pages/Auth/Login.php`, and `config/filament-local-logins.php`. Port the `ADMIN_PANEL_LOCAL_LOGINS_ENABLED` env gating to the new plugin's config; verify it still hides local logins outside `local`. Remove the old require + config file.
*   **`awcodes/filament-table-repeater` → native Repeater table layout.** This is the `TableRepeater` usage in the odk-link trait `WithXlsformModuleVersionQuestionEditing` (coordinate with Dave). Remove the require. Fiddliest of the four.

### 2. Bump every constraint at once

In root `composer.json`, in a single edit:

*   `filament/filament:"^5.0"`
*   `laravel/framework:"^13.0"`
*   plugins to their F5 lines: `althinect/filament-spatie-roles-permissions` (`^4.0`/`^5.0`), `eightynine/filament-excel-import` (F5-compatible stable)
*   add `bramr94/filament-developer-logins`
*   remove `awcodes/shout`, `awcodes/filament-table-repeater`, `better-futures-studio/filament-local-logins`, `joserick/laravel-livewire-discover`
*   bump submodule pointers (`packages/filament-team-management`, `packages/filament-odk-link`) to their F5 tags
*   bump first-party Laravel/dev tooling (pint, sail, collision, larastan v3, pest plugins, horizon, reverb, telescope, sentry, etc.) to L13-compatible lines

Then `composer update -W`. Livewire 4 comes in transitively via Filament 5; pin it explicitly if the resolver needs help.

### 3. Run the Filament codemods (v4 then v5)

Per the [v4](https://filamentphp.com/docs/4.x/upgrade-guide) and [v5](https://filamentphp.com/docs/5.x/upgrade-guide) upgrade guides. Run the v4 tool first (it does the bulk of namespace/signature rewrites — `Filament\Schemas\Schema`, `form(Schema $schema)`, `Get`/`Set` moves across `app/Filament/`), then the v5 tool (Livewire-4 compatibility pass).

```
composer require filament/upgrade:"^4.0" -W --dev
vendor/bin/filament-v4

composer require filament/upgrade:"^5.0" -W --dev
vendor/bin/filament-v5
```

Review every diff. The codemods do not cover the manual breaking changes in step 4.

### 4. Manual breaking changes (audit each — codemods miss these)

**Filament 3→4 layout / behaviour:**

*   **Layout default-span change**: `Section`/`Fieldset` now span 1 column, not full width. Audit the ~13 `Section` + 3 `Fieldset` sites and the `columns()`/`columnSpanFull()` usage (esp. the responsive `Fieldset` in `app/Filament/Shared/WithXlsformModuleVersionQuestionEditing.php`); add `columnSpanFull()`/`columnSpan()` where full width is expected.
*   **`columnSpan()` now targets `>= lg`** by default — verify the ~2 sites that pass scalars.
*   **Table filters deferred by default** — users must click Apply. Decide per-table vs global `deferFilters(false)` (set in a panel/service provider for prior behaviour) across the ~11 resources.
*   **`unique()` now ignores the current record by default** — audit form validation; pass `ignoreRecord: false` where the old behaviour was relied on.
*   **Enum fields always return enum instances** — check Select/state handling on enum-backed attributes.
*   **URL param renames** (`activeTab`→`tab`, `tableFilters`→`filters`, `tableSort`→`sort`, `activeRelationManager`→`relation`) — fix any hardcoded query strings / links / tests that depend on them.
*   **Authorization**: replace any overridden `can*()` with `get*AuthorizationResponse()` if used.
*   **`TableRepeater` → native v4 repeater table layout** (odk-link trait) — same task as the package removal in step 1.
*   **Tenancy auto-scoping**: F4 auto-scopes queries and associates new records to the current tenant. Review the App/Program panel providers and any manual tenant scoping / `SetLatest*Middleware` for now-redundant or now-double scoping.

**Livewire 3→4** ([guide](https://livewire.laravel.com/docs/4.x/upgrading)) — codebase is well-positioned (modern attributes, no legacy modifiers):

*   **`<livewire:…>` tags must be explicitly closed** — already self-closing; confirm none left open (notably the looped `team-translation-entry` and `pilot-index` embeds).
*   **`wire:model` modifier semantics**: `.blur`/`.change` now also gate client-side state sync; add `.live` to restore prior behaviour where used. Audit `wire:` directives in the 13 Livewire blade views + the custom Alpine `sortable.js` directives against the new lifecycle.
*   Component organisation relies on default convention discovery (discover package dropped in step 1); use [LW4 namespaces](https://livewire.laravel.com/docs/4.x/components#organizing-components) only if custom subfolder names are wanted.

**Laravel 11→13:**

*   Apply the [L12](https://laravel.com/docs/12.x/upgrade) then [L13](https://laravel.com/docs/13.x/releases) upgrade-guide diffs (mostly config/default changes — both are low-breakage). Direct 11→13 is unsupported as a documented path, but since we're not testing intermediate states, apply both guides' diffs in one pass against the current config.
*   Confirm all first-party packages from the gate table resolve on 13.

### 5. Frontend — Tailwind v4 theme migration

`resources/css/filament/app/theme.css`: upgrade to Tailwind **4.1+**, replace `@config` with `@source`/CSS-based config, port the custom CSS-variable palette (primary/brown/green/orange…) and Montserrat font, drop `resources/css/filament/app/tailwind.config.js`.

### 6. Config

*   `php artisan vendor:publish --tag=filament-config`; review `post-autoload-dump`'s `filament:upgrade`.
*   Optional: `php artisan filament:upgrade-directory-structure-to-v4 --dry-run` — decide whether to adopt the new resource/cluster directory layout (cosmetic; can defer).

## Validation (only now — after everything above lands)

Run the full gate. Expect failures on the first attempt; this is the bug-fixing phase, done *after* the update, not interleaved.

*   `./vendor/bin/pest` (SQLite in-memory, `DatabaseSeeder` auto-runs). This is the first time the suite can run since the team-management F5 fixes landed.
    *   Re-check the two items flagged in the [team-management change-log](../change-logs/filament-team-management-f5-reference-fixes.md): (a) the exact `EditTenantProfile` `save`/`fillForm` API exercised by `ProgramPanelCrudTest`, and (b) that `Livewire::make()` resolves the un-discovered `ManageProgram*` widgets wired via `tenantProfile()`.
*   `./vendor/bin/phpstan analyse` (larastan v3) and `./vendor/bin/pint`.
*   `npm run build`; visually verify the custom App-panel theme (colours, Montserrat).
*   `php artisan migrate` on a copy; smoke-test `queue:work`/Horizon and any scheduled jobs.
*   **Manual smoke across all three panels**, with emphasis on:
    *   tenancy scoping (App `Team`, Program `Program`) — watch for F4 auto-scoping double-applying with existing manual scoping / `SetLatest*Middleware`.
    *   table filters (deferred behaviour in F4).
    *   the XLSForm module-version question-editing trait and the native Repeater (replaced `TableRepeater`).
    *   Excel import/export actions (`ImportLocationsAction`/`ImportFarmsAction` — the excel-import API may have shifted).
    *   auth/login flows (native `Callout` + `developer-logins`; confirm local logins hidden outside `local`).
    *   survey-language translation Livewire flows; Leaflet maps + Chart.js dashboards.

Then fix bugs to green.

## Risks / watch-items

*   **No intermediate checkpoint.** The whole update lands before anything can be tested. A regression cannot be bisected to a single framework jump — mitigate by committing the discrete steps above as separate commits on one branch so `git` history at least separates code-change classes, even though none of the intermediate commits are runnable.
*   **team-management is on the F5 structure** — its host-app reference fixes are committed but unvalidated until this update's `composer update` resolves Filament 5. Re-run the two change-log re-check items at Validation.
*   **Four packages removed, not bumped** (`shout`, `filament-table-repeater`, `filament-local-logins`, `laravel-livewire-discover`) — each a real code change. `shout`→`Callout` and `local-logins`→`developer-logins` touch auth/login and several schemas; `filament-table-repeater`→native repeater is the fiddliest.
*   **F4 layout default-span + deferred-filters** are the most likely sources of subtle UI regressions across 42 pages / 11 resources.
*   **F4 tenancy auto-scoping** may double-apply with existing manual scoping.
*   **Tailwind v3→v4** theme port is the fiddliest frontend task.
*   **`eightynine/filament-excel-import`** moving off `3.x-dev` to a stable F5 line may shift the import-action API used by `ImportLocationsAction`/`ImportFarmsAction`.
