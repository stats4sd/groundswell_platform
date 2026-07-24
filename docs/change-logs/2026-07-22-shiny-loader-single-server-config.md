# Change Log: Shiny Loader Single-Server Config

**Date**: 2026-07-22
**Plan**: [docs/plans/2026-07-22-shiny-loader-single-server-config.md](../plans/2026-07-22-shiny-loader-single-server-config.md)
**Repos touched**: main app (`pre-publish-fixes`), `packages/laravel-shiny-loader` (new branch `single-shiny-server`), `packages/groundswell_monitor` (new branch `shared-shiny-server`), `packages/groundswell_analysis` (`analysis-dashboard-integration`)

## Summary

Converted `stats4sd/laravel-shiny-loader` to a single-shiny-server model and updated the Groundswell platform to consume it. All shiny apps embedded in one Laravel app must now be served by one shiny server instance at a shared root URL; app names live in the committed config file, not `.env`.

## Package: laravel-shiny-loader (v2.0, breaking)

Commits `da98fc4`, `c8f449e`, `ea7a052`, `312875c`, `7c88c25`, `2facab5` on branch `single-shiny-server`.

- **Config rewritten** (snake_case keys): `root_url` (env `SHINY_ROOT_URL`), `root_path` (env `SHINY_ROOT_PATH`), `auth_key` (env `SHINY_AUTH_KEY`), and a committed `apps` array of app folder names. Old `app-path`/`auth-key`/per-app `*-url` keys and `SHINY_APP_URL_*` env vars removed.
- **`ShinyIframe` component** now takes `app="<name>"` instead of `shiny-app-url`; it validates the name against `shiny-loader.apps` (throws `InvalidArgumentException` otherwise) and builds `{root_url}/{app}/` itself.
- **Component registration bug fixed** (discovered during implementation): `hasViewComponent()` never bound the `<x-shiny-loader::shiny-iframe>` tag to the class — the tag silently resolved via Blade's anonymous-component fallback, so the component class had never actually run, in production included. Replaced with `Blade::componentNamespace(...)` in `packageBooted()`.
- **`ShinyController` rewritten**: removed the dead `config('services.shiny.rdmt-demo-url')` leftover (the old code only worked because `null . $fullUrl === $fullUrl`); the callback URL from the session file must now start with `root_url` (422 otherwise), enforcing the single-server model and closing a redirect hole that could leak the shared `auth_key`; session ids are validated `alpha_num` (closes a path-traversal hole in the session-file lookup); missing session file returns 404; the undefined `$finalUrl` bug in the old catch block is gone.
- **Iframe view**: non-2xx auth responses now surface the error message instead of silently reporting success (`fetch` `res.ok` check).
- **Tests**: 9 new Pest tests (4 component, 5 controller — including path traversal and foreign-callback rejection). Full suite 11/11 passing.
- **README/CHANGELOG**: documented the single-server requirement, the app-names-in-config rationale, and the v2.0 breaking changes.

## Main app

Commits `dc6c110`, `3b9d932` on `pre-publish-fixes`.

- `config/shiny-loader.php` republished in the new shape with `apps => ['groundswell_monitor', 'groundswell_analysis']`.
- `.env.example` (and untracked `.env`): `SHINY_ROOT_URL` + `SHINY_ROOT_PATH` + `SHINY_AUTH_KEY` replace the old per-app URL vars.
- Both blade views (`monitor-data-collection`, `data-analysis-index`) now pass `app="..."` names.
- `packages/laravel-shiny-loader` gitlink pointer bumped to `2facab5`; `packages/groundswell_monitor` pointer bumped to `f11be9a`.

## R apps

- `groundswell_monitor` `example.env` (`f11be9a`, new branch `shared-shiny-server`) and `groundswell_analysis` `example.env` (`bb44df3`): `URL` now points at `http://localhost:3838/{app-name}` (local docker shiny server). Untracked `.env` files updated to match.

## Review outcome

Executed via subagent-driven development: per-task spec+quality reviews (2 Important findings fixed in-loop: a redundant `data()` override; a concatenation style violation), then a whole-branch final review across all three repos — **ready to merge**, no Critical/Important findings. Minors triaged ship-as-is: dead axios-style `err.response` branch in the iframe view JS, 419 ambiguity with Laravel's CSRF status, developer-specific path in `.env.example`, quoting inconsistency between the two R `example.env` files. Package phpstan has 4 pre-existing-in-kind errors (empty baseline; CI was already red before this branch) — track separately.

## Outstanding / deploy notes

- **Task 5 (manual)**: local end-to-end verification against a docker shiny server serving both apps (see plan Task 5 checklist), including the 422 failure mode.
- `.env` currently has the placeholder `SHINY_AUTH_KEY` — set a real key matching the R apps before testing/deploy.
- `enketo_return_url=http://localhost:7007` in `groundswell_monitor/example.env` was deliberately left unchanged — flagged for review: it may need the new shiny URL or may be unrelated to this handshake.
- `packages/groundswell_analysis` is tracked as plain files in the main repo (no gitlink, unlike the other packages) — pre-existing inconsistency worth resolving separately.
- Production deploys need the package branch `single-shiny-server` merged/pushed, `composer install` refreshed (a stale non-symlinked vendor copy of the package caused local test failures until reinstalled), and `SHINY_ROOT_URL`/`SHINY_ROOT_PATH` set for the shared server.
