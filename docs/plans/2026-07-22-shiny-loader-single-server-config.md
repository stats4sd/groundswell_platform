# Shiny Loader Single-Server Config Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** In Progress — Tasks 1–4 implemented, reviewed, and committed (see [change log](../change-logs/2026-07-22-shiny-loader-single-server-config.md)); final whole-branch review passed with no blocking findings. Remaining: Task 5, the manual end-to-end verification against a local docker shiny server (user-run), plus setting a real `SHINY_AUTH_KEY` in `.env`.

**Goal:** Make `laravel-shiny-loader` enforce a single-shiny-server model — one root URL for all embedded apps, app names in committed config — and update the Groundswell platform to consume the new API.

**Architecture:** The package config gains `root_url` (one shiny server for all apps), `root_path` (filesystem root shared with the shiny server, containing the `.sessions` dir), and a committed `apps` array of app names. The `ShinyIframe` component takes an app *name* and builds `{root_url}/{app}/` itself. `ShinyController` reads the callback URL from the session file (the R side already writes a full URL there) and validates it starts with `root_url` — replacing the dead `config('services.shiny.rdmt-demo-url')` lookup — plus sanitises the session id and fixes error handling.

**Tech Stack:** Laravel 11/12/13 package (spatie/laravel-package-tools, Orchestra Testbench, Pest 4), consumed by the Groundswell Laravel 11 + Filament app. R side is `stats4sd/shiny-laravel-auth` (unchanged by this plan).

## Global Constraints

- All embedded shiny apps must be served by ONE shiny server instance; the package must document and enforce this.
- App names live in the committed config file, NOT in `.env` (see "Design decisions & critique" below).
- New config keys are snake_case (`root_url`, `root_path`, `auth_key`, `apps`); the config filename stays `shiny-loader.php`.
- Old keys/env vars removed: `app-path`/`SHINY_APP_PATH` → `root_path`/`SHINY_ROOT_PATH`; `auth-key` → `auth_key` (env `SHINY_AUTH_KEY` unchanged); per-app `*-url` keys and `SHINY_APP_URL_*` env vars deleted.
- `config('services.shiny.rdmt-demo-url')` must not survive — it is a leftover from another project and resolves to null here.
- The package repo (`packages/laravel-shiny-loader`) is a SEPARATE git repo, currently on a detached HEAD. Package commits go there on a new branch; app commits go in the main repo.
- Follow the laravel-php-guidelines skill for all PHP (early returns, no `else`, descriptive variable names, imported classnames, string interpolation).

## Design decisions & critique

### Critique of "app names in config, not .env" (requested by spec)

The assumption is sound. App names are structural: blade views reference them literally (`app="groundswell_monitor"`), so code and config must change together — an env-var indirection is false flexibility because renaming the env value would break the blade references anyway. Twelve-factor reserves env vars for values that genuinely vary per deploy; what varies here is the server's *root URL* and *root path*, and those stay in `.env`. Caveats worth knowing:

1. **The names become a cross-environment contract with the shiny server's directory layout.** `{root_url}/{app}/` only works if every environment deploys each app under the same directory name. If a deploy pipeline ever versions directories or uses `myapp-staging` names, the model breaks. Acceptable: stats4sd controls shiny deployment (shiny-manager / shiny-deploy), so keep directory names stable — but this constraint should be stated in the README, which Task 4 does.
2. **Bare `shiny::runApp()` on ad-hoc ports no longer fits** — there is no shared root URL in that mode. Local dev uses either the docker shiny server (as planned) or the R package's `OVERRIDE_LARAVEL_AUTH=true` bypass. Documented in Task 4.
3. **Escape hatch preserved:** if per-app variation is ever needed (aliasing, per-app options), the plain list can become an associative array later without touching `.env`. YAGNI for now — plain list of names.

### Why single-server is enforced in the controller, not just documented

Not a hard technical necessity — the blade component could pass the app name to the auth endpoint and per-app root URLs could be looked up. But the R side already assumes one server (shared `../.sessions` dir at the server root), and the session file lives on a shared volume: the controller currently POSTs the auth key and user context to *whatever URL is in that file*. Validating the callback URL against `root_url` both enforces the single-server rule and closes that redirect hole. Multiple servers would need multiple session dirs, root paths, and origin checks — revisit only if a real deployment needs two servers.

### One root URL for both browser and Laravel

The iframe `src` (browser → shiny) and the auth callback (Laravel → shiny) both use `root_url`, so Laravel must be able to reach the same URL the browser uses. True for local docker (`http://localhost:3838`) and for the production setup (publicly proxied shiny server). If a deployment ever needs a split (browser via public proxy, Laravel via internal address), add an optional `callback_root_url` then — not now.

---

### Task 1: Package config + ShinyIframe by app name

**Files:**
- Modify: `packages/laravel-shiny-loader/config/shiny-loader.php`
- Modify: `packages/laravel-shiny-loader/src/View/Components/ShinyIframe.php`
- Create: `packages/laravel-shiny-loader/tests/ShinyIframeTest.php`

**Interfaces:**
- Produces: config keys `shiny-loader.root_url`, `shiny-loader.root_path`, `shiny-loader.auth_key`, `shiny-loader.apps` (list of strings). `ShinyIframe::__construct(public string $app, public ?array $postData = null)` exposing computed `public string $shinyAppUrl` (`{root_url}/{app}/`), throwing `InvalidArgumentException` for unregistered apps. Task 2's controller and Task 5's blade views rely on these exact names.

- [ ] **Step 1: Create a work branch in the package repo (it is on a detached HEAD)**

```bash
cd /Users/dave/Sites/groundswell_platform/packages/laravel-shiny-loader
git switch -c single-shiny-server
composer install
```

- [ ] **Step 2: Rewrite the package config file**

Replace the full contents of `packages/laravel-shiny-loader/config/shiny-loader.php`:

```php
<?php

// config for Stats4sd/LaravelShinyLoader
return [
    /*
     * All shiny apps embedded in one Laravel app MUST be served by a single
     * shiny server instance, reachable at this root url. Each app is served
     * at {root_url}/{app-name}/. Laravel must also be able to reach this url,
     * as the auth handshake POSTs back to it.
     */
    'root_url' => env('SHINY_ROOT_URL', 'http://localhost:3838'),

    /*
     * Filesystem path to the shiny server's site directory (the folder that
     * contains each app's folder). The shared `.sessions` directory used by
     * the auth handshake lives directly inside it. Must be readable by both
     * the shiny server and this Laravel app (a shared/mounted volume).
     */
    'root_path' => env('SHINY_ROOT_PATH', '/srv/shiny-server'),

    /*
     * Shared secret between this Laravel app and the shiny apps.
     */
    'auth_key' => env('SHINY_AUTH_KEY', 'change-me'),

    /*
     * The names of the shiny apps embedded in this Laravel app. Each name is
     * the app's folder name on the shiny server, so app "monitor" is served
     * at {root_url}/monitor/. Names are structural (blade views reference
     * them) and identical in every environment, so they are listed here
     * rather than in .env.
     */
    'apps' => [],
];
```

- [ ] **Step 3: Write the failing component tests**

Create `packages/laravel-shiny-loader/tests/ShinyIframeTest.php`:

```php
<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Stats4sd\LaravelShinyLoader\View\Components\ShinyIframe;

beforeEach(function () {
    config()->set('shiny-loader.root_url', 'http://localhost:3838');
    config()->set('shiny-loader.apps', ['monitor', 'analysis']);
});

it('builds the app url from the shared root url', function () {
    $component = new ShinyIframe('monitor');

    expect($component->shinyAppUrl)->toBe('http://localhost:3838/monitor/');
});

it('strips a trailing slash from the root url', function () {
    config()->set('shiny-loader.root_url', 'http://localhost:3838/');

    $component = new ShinyIframe('analysis');

    expect($component->shinyAppUrl)->toBe('http://localhost:3838/analysis/');
});

it('rejects an app that is not registered in config', function () {
    new ShinyIframe('unknown-app');
})->throws(InvalidArgumentException::class);

it('renders the iframe pointing at the app url', function () {
    Route::shiny();

    $html = Blade::render('<x-shiny-loader::shiny-iframe app="monitor" :post-data="[\'foo\' => \'bar\']" />');

    expect($html)->toContain('src="http://localhost:3838/monitor/"');
});
```

(`Route::shiny()` is needed in the render test because the blade view calls `route('laravel-shiny.shiny-auth')`.)

- [ ] **Step 4: Run tests to verify they fail**

Run: `cd packages/laravel-shiny-loader && ./vendor/bin/pest --filter=ShinyIframe`
Expected: FAIL — `ShinyIframe::__construct()` does not accept an app name / `$shinyAppUrl` not built.

- [ ] **Step 5: Rewrite the component**

Replace the full contents of `packages/laravel-shiny-loader/src/View/Components/ShinyIframe.php`:

```php
<?php

namespace Stats4sd\LaravelShinyLoader\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

class ShinyIframe extends Component
{
    public string $shinyAppUrl;

    public function __construct(public string $app, public ?array $postData = null)
    {
        if (! in_array($app, config('shiny-loader.apps', []), true)) {
            throw new InvalidArgumentException("Shiny app [{$app}] is not registered in the shiny-loader.apps config array.");
        }

        $rootUrl = rtrim((string) config('shiny-loader.root_url'), '/');

        $this->shinyAppUrl = "{$rootUrl}/{$app}/";
    }

    public function render(): View|Closure|string
    {
        return view('shiny-loader::components.shiny-iframe');
    }
}
```

The blade view `resources/views/components/shiny-iframe.blade.php` keeps using `$shinyAppUrl` and needs no change in this task.

- [ ] **Step 6: Run tests to verify they pass**

Run: `cd packages/laravel-shiny-loader && ./vendor/bin/pest --filter=ShinyIframe`
Expected: PASS (4 tests)

- [ ] **Step 7: Commit (package repo)**

```bash
cd /Users/dave/Sites/groundswell_platform/packages/laravel-shiny-loader
git add config/shiny-loader.php src/View/Components/ShinyIframe.php tests/ShinyIframeTest.php
git commit -m "feat!: single shiny server root url; iframe component takes app name"
```

---

### Task 2: ShinyController — validate callback against root_url, sanitise session id

**Files:**
- Modify: `packages/laravel-shiny-loader/src/Http/Controllers/ShinyController.php`
- Modify: `packages/laravel-shiny-loader/resources/views/components/shiny-iframe.blade.php` (surface non-2xx auth responses)
- Create: `packages/laravel-shiny-loader/tests/ShinyControllerTest.php`

**Interfaces:**
- Consumes: config keys `shiny-loader.root_url`, `shiny-loader.root_path`, `shiny-loader.auth_key` from Task 1.
- Produces: `POST {prefix}/auth` (route name `laravel-shiny.shiny-auth`) accepting JSON `{session: string (alphanumeric), post_data: ?array}`. Responses: 200 `{success}`, 404 session file missing, 422 validation failure or callback URL outside `root_url`, 419 shiny app rejected the callback POST.

Background for the implementer: the R package (`stats4sd/shiny-laravel-auth`) writes a file `{root_path}/.sessions/{uuid}` whose first line is the app's FULL callback URL, e.g. `http://localhost:3838/monitor/session/{uuid}/dataobj/auth?w=&nonce=abc`. The old controller prepended `config('services.shiny.rdmt-demo-url')` (a leftover key from another project, null here) — it worked only because `null . $fullUrl === $fullUrl`. The new controller trusts the file for the path but requires the URL to start with the configured `root_url`, which enforces the single-server model and stops a tampered session file redirecting the auth POST (which carries the shared `auth_key`) elsewhere. The raw `$request->get('session')` was also concatenated into a filesystem path unsanitised (path traversal); session ids are hex uuids, so `alpha_num` validation closes that.

- [ ] **Step 1: Write the failing controller tests**

Create `packages/laravel-shiny-loader/tests/ShinyControllerTest.php`:

```php
<?php

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->rootPath = sys_get_temp_dir().'/shiny-loader-tests-'.uniqid();
    File::ensureDirectoryExists("{$this->rootPath}/.sessions");

    config()->set('shiny-loader.root_path', $this->rootPath);
    config()->set('shiny-loader.root_url', 'http://localhost:3838');
    config()->set('shiny-loader.auth_key', 'test-key');

    Route::shiny();
});

afterEach(function () {
    File::deleteDirectory($this->rootPath);
});

it('posts the auth key and post data to the callback url from the session file', function () {
    Http::fake(['http://localhost:3838/*' => Http::response('ok')]);
    File::put("{$this->rootPath}/.sessions/abc123", "http://localhost:3838/monitor/session/abc123/dataobj/auth?w=&nonce=xyz\n");

    $response = $this->postJson(route('laravel-shiny.shiny-auth'), [
        'session' => 'abc123',
        'post_data' => ['project_id' => 42],
    ]);

    $response->assertOk()->assertJson(['success' => 'Shiny session authenticated']);

    Http::assertSent(fn (ClientRequest $sentRequest): bool => $sentRequest->url() === 'http://localhost:3838/monitor/session/abc123/dataobj/auth?w=&nonce=xyz'
        && $sentRequest['auth_key'] === 'test-key'
        && $sentRequest['project_id'] === 42);
});

it('returns 404 when no session file exists', function () {
    Http::fake();

    $response = $this->postJson(route('laravel-shiny.shiny-auth'), ['session' => 'missing1']);

    $response->assertNotFound();
    Http::assertNothingSent();
});

it('rejects a callback url served from a different shiny server', function () {
    Http::fake();
    File::put("{$this->rootPath}/.sessions/abc123", "http://evil.example.com/monitor/session/abc123/dataobj/auth\n");

    $response = $this->postJson(route('laravel-shiny.shiny-auth'), ['session' => 'abc123']);

    $response->assertStatus(422);
    Http::assertNothingSent();
});

it('rejects a session id that is not alphanumeric', function () {
    Http::fake();

    $response = $this->postJson(route('laravel-shiny.shiny-auth'), ['session' => '../../../etc/passwd']);

    $response->assertStatus(422);
    Http::assertNothingSent();
});

it('returns 419 when the shiny app rejects the callback post', function () {
    Http::fake(['http://localhost:3838/*' => Http::response('nope', 500)]);
    File::put("{$this->rootPath}/.sessions/abc123", "http://localhost:3838/monitor/session/abc123/dataobj/auth\n");

    $response = $this->postJson(route('laravel-shiny.shiny-auth'), ['session' => 'abc123']);

    $response->assertStatus(419);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd packages/laravel-shiny-loader && ./vendor/bin/pest --filter=ShinyController`
Expected: FAIL — old controller reads `config('shiny-loader.app-path')` (null) and does no validation, so file lookups and status codes don't match.

- [ ] **Step 3: Rewrite the controller**

Replace the full contents of `packages/laravel-shiny-loader/src/Http/Controllers/ShinyController.php`:

```php
<?php

namespace Stats4sd\LaravelShinyLoader\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class ShinyController
{
    public function authenticateShiny(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session' => ['required', 'string', 'alpha_num'],
            'post_data' => ['nullable', 'array'],
        ]);

        $sessionFile = config('shiny-loader.root_path')."/.sessions/{$validated['session']}";

        if (! File::exists($sessionFile)) {
            return response()->json(['error' => 'Shiny session not found'], 404);
        }

        $callbackUrl = trim((string) strtok(File::get($sessionFile), "\n"));

        $rootUrl = rtrim((string) config('shiny-loader.root_url'), '/');

        if (! str_starts_with($callbackUrl, "{$rootUrl}/")) {
            return response()->json([
                'error' => "The shiny session's callback url does not start with the configured shiny-loader.root_url ({$rootUrl}). All embedded shiny apps must be served from the same shiny server instance.",
            ], 422);
        }

        $postData = $validated['post_data'] ?? [];
        $postData['auth_key'] = config('shiny-loader.auth_key');

        try {
            Http::post($callbackUrl, $postData)->throw();
        } catch (Exception) {
            return response()->json(['error' => 'Shiny session authentication failed', 'url' => $callbackUrl], 419);
        }

        return response()->json(['success' => 'Shiny session authenticated']);
    }
}
```

(This also fixes the old bug where the `catch` block referenced `$finalUrl` before it was defined when `fopen` threw.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd packages/laravel-shiny-loader && ./vendor/bin/pest`
Expected: PASS (all package tests, including Task 1's)

- [ ] **Step 5: Surface non-2xx auth responses in the blade view**

In `packages/laravel-shiny-loader/resources/views/components/shiny-iframe.blade.php`, `fetch` does not reject on HTTP error statuses, so today a 4xx/419 still calls `showAuthSuccess()`. Change the `.then` handler:

```js
.then((res) => {
    if (!res.ok) {
        throw new Error("Shiny auth request failed with status " + res.status);
    }
    showAuthSuccess();
})
```

(The `.catch` block below it stays as-is.)

- [ ] **Step 6: Commit (package repo)**

```bash
cd /Users/dave/Sites/groundswell_platform/packages/laravel-shiny-loader
git add src/Http/Controllers/ShinyController.php resources/views/components/shiny-iframe.blade.php tests/ShinyControllerTest.php
git commit -m "fix!: validate shiny callback against root_url, sanitise session id, surface auth errors"
```

---

### Task 3: Package docs, changelog, code quality

**Files:**
- Modify: `packages/laravel-shiny-loader/README.md`
- Modify: `packages/laravel-shiny-loader/CHANGELOG.md`

- [ ] **Step 1: Update the README**

Replace the README's "Installation" and "Use" sections (keep the intro, changelog/contributing/credits sections):

`````markdown
## Requirements: one shiny server per Laravel app

**All shiny apps embedded in one Laravel app must be served by a single shiny server instance, from a single root url.** Each app is served at `{root_url}/{app-name}/`, and the auth handshake relies on a `.sessions` directory at the shiny server's root, shared between all apps and readable by Laravel (same machine or a shared/mounted volume).

This means the app *names* are part of your application's structure: they must match the app's folder name on the shiny server in every environment, and your blade views reference them directly. They therefore live in the committed config file, not in `.env`. Only the root url and root path vary per environment.

Running apps individually with `shiny::runApp()` on ad-hoc ports is not supported — there is no shared root url in that mode. For local development, either run a shiny server (e.g. via Docker) serving all apps, or use the R package's `OVERRIDE_LARAVEL_AUTH=true` bypass (see [stats4sd/shiny-laravel-auth](https://github.com/stats4sd/shiny-laravel-auth)).

## Installation

You can install the package via composer, then publish the config file:

```
composer require stats4sd/laravel-shiny-loader
php artisan vendor:publish --tag shiny-loader-config
```

Add the following properties to your .env file:

```
## Root url of the shiny server instance that serves ALL embedded shiny apps.
## Both the browser and the Laravel app must be able to reach this url.
SHINY_ROOT_URL="http://localhost:3838"

## Absolute path to the shiny server's site directory - the folder containing each app's folder
## and the shared `.sessions` directory used by the auth handshake.
SHINY_ROOT_PATH="/srv/shiny-server"

## A secret key that both the Laravel app and the Shiny apps know. This is used to authenticate
## requests from Laravel to the Shiny apps. It can be any string.
SHINY_AUTH_KEY="change-me"
```

Then register each embedded app's name in `config/shiny-loader.php` (names are folder names on the shiny server):

```php
'apps' => [
    'monitor',
    'analysis',
],
```

## Use

To enable authentication, register the auth route in your `routes/web.php` (wrap it in whatever auth middleware suits your app):

```php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::shiny();
});
```

Embed an app with the ShinyIframe component, passing the app's *name* (it must be listed in `shiny-loader.apps`). You may optionally add `$postData` — an array of data to pass to the Shiny app when it loads:

```blade
<x-shiny-loader::shiny-iframe
    app="monitor"
    :post-data="['foo' => 'bar']"
    />
```
`````

- [ ] **Step 2: Add a CHANGELOG entry**

Add at the top of `packages/laravel-shiny-loader/CHANGELOG.md` (below the intro line):

```markdown
## 2.0 - Single shiny server - 2026-07-22

Breaking changes:

- All embedded shiny apps must now be served from a single shiny server instance. New config: `root_url` (env `SHINY_ROOT_URL`) and a committed `apps` array of app names; the per-app `*-url` config entries and `SHINY_APP_URL_*` env vars are removed.
- Config keys renamed to snake_case: `app-path` → `root_path` (env `SHINY_APP_PATH` → `SHINY_ROOT_PATH`), `auth-key` → `auth_key` (env `SHINY_AUTH_KEY` unchanged).
- `<x-shiny-loader::shiny-iframe>` now takes `app` (the app name, which must be registered in `shiny-loader.apps`) instead of `shiny-app-url`.
- The auth controller validates that the session callback url starts with `root_url` (422 otherwise), rejects non-alphanumeric session ids, returns 404 for missing session files, and no longer reads the leftover `services.shiny.rdmt-demo-url` config key.
- The iframe view now reports authentication failures instead of silently treating HTTP error responses as success.
```

- [ ] **Step 3: Run code quality checks and full test suite**

```bash
cd /Users/dave/Sites/groundswell_platform/packages/laravel-shiny-loader
./vendor/bin/pint
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

Expected: pint clean (or auto-fixed — re-stage), phpstan no new errors, all tests PASS.

- [ ] **Step 4: Commit (package repo)**

```bash
git add README.md CHANGELOG.md
git add -u
git commit -m "docs: document single-server requirement and v2 config"
```

---

### Task 4: Update the Groundswell app to the new package API

**Files:**
- Modify: `config/shiny-loader.php`
- Modify: `.env.example` (lines 101–107)
- Modify: `.env` (not committed — same edits as `.env.example`)
- Modify: `resources/views/filament/app/pages/data-collection/monitor-data-collection.blade.php:13-14`
- Modify: `resources/views/filament/app/pages/data-analysis/data-analysis-index.blade.php:20`
- Modify: `packages/groundswell_monitor/example.env`, `packages/groundswell_analysis/example.env` (and untracked `.env` files alongside them)

**Interfaces:**
- Consumes: `shiny-loader.root_url` / `root_path` / `auth_key` / `apps` config keys and the `app="..."` component attribute from Tasks 1–2.

- [ ] **Step 1: Replace the published config file**

Replace the full contents of `config/shiny-loader.php`:

```php
<?php

// config for Stats4sd/LaravelShinyLoader
return [
    'root_url' => env('SHINY_ROOT_URL', 'http://localhost:3838'),
    'root_path' => env('SHINY_ROOT_PATH', '/srv/shiny-server'),
    'auth_key' => env('SHINY_AUTH_KEY', 'change-me'),

    // Folder names of the shiny apps on the shiny server; each is served at {root_url}/{name}/.
    'apps' => [
        'groundswell_monitor',
        'groundswell_analysis',
    ],
];
```

- [ ] **Step 2: Update `.env.example` and `.env`**

Replace lines 101–107 of `.env.example` (the `SHINY_*` block) with:

```
# Shiny server integration. ALL embedded shiny apps must be served by ONE shiny server
# instance at this root url, reachable by both the browser and this Laravel app.
SHINY_ROOT_URL="http://localhost:3838"
# Absolute path to the shiny server's site directory (contains the app folders and the shared
# .sessions folder). For local docker testing, this is the host path mounted into the container
# at /srv/shiny-server - i.e. this repo's packages/ folder.
SHINY_ROOT_PATH="/Users/dave/Sites/groundswell_platform/packages"
SHINY_AUTH_KEY="changemetosomething"
```

Make the same replacement in `.env` (keeping the real `SHINY_AUTH_KEY` value already there).

- [ ] **Step 3: Update the two blade views**

`resources/views/filament/app/pages/data-collection/monitor-data-collection.blade.php` lines 13–14:

```blade
            <x-shiny-loader::shiny-iframe app="groundswell_monitor"
                :post-data="$shinyData->toArray()" />
```

`resources/views/filament/app/pages/data-analysis/data-analysis-index.blade.php` line 20:

```blade
            <x-shiny-loader::shiny-iframe app="groundswell_analysis" :post-data="['foo' => 'bar']" />
```

- [ ] **Step 4: Update the R apps' env examples for the shared-server layout**

In `packages/groundswell_monitor/example.env` line 5:

```
URL=http://localhost:3838/groundswell_monitor
```

In `packages/groundswell_analysis/example.env` line 5:

```
URL="http://localhost:3838/groundswell_analysis"
```

Make the same `URL` changes in the untracked `packages/groundswell_monitor/.env` and `packages/groundswell_analysis/.env`. NOTE: `packages/groundswell_monitor/example.env` line 11 also has `enketo_return_url=http://localhost:7007` — flag this to the user rather than changing it; it may need to become the shiny app's new url or may be unrelated to this handshake.

(The R apps are separate git repos inside `packages/`; commit their example.env changes in each repo on their current branch, message: `chore: point URL at shared shiny server root`.)

- [ ] **Step 5: Run the app's quality checks and test suite**

```bash
cd /Users/dave/Sites/groundswell_platform
./vendor/bin/pint --dirty
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

Expected: PASS / no new errors. (No app tests currently reference the shiny config; this is a regression check.)

- [ ] **Step 6: Commit (main repo)**

```bash
cd /Users/dave/Sites/groundswell_platform
git add config/shiny-loader.php .env.example resources/views/filament/app/pages/data-collection/monitor-data-collection.blade.php resources/views/filament/app/pages/data-analysis/data-analysis-index.blade.php packages/laravel-shiny-loader
git commit -m "refactor: consume single-server shiny-loader config"
```

(`packages/laravel-shiny-loader` is tracked as an embedded repo pointer in the main repo — staging it records the new package commit. Commit on the current branch unless the user directs otherwise.)

---

### Task 5: Local end-to-end verification (docker shiny server)

The user runs the shiny server via Docker to mimic production. This task is a verification checklist, not code.

- [ ] **Step 1: Confirm expected docker layout**

The Laravel config assumes the container serves both apps from one root with the `.sessions` volume shared back to the host, e.g.:

```yaml
services:
  shiny:
    image: rocker/shiny   # or a custom image with the apps' renv dependencies installed
    ports:
      - "3838:3838"
    volumes:
      - ./packages/groundswell_monitor:/srv/shiny-server/groundswell_monitor
      - ./packages/groundswell_analysis:/srv/shiny-server/groundswell_analysis
      - ./packages/.sessions:/srv/shiny-server/.sessions
```

With this layout: `SHINY_ROOT_URL=http://localhost:3838`, `SHINY_ROOT_PATH=/Users/dave/Sites/groundswell_platform/packages`, and each R app's `.env` has `URL=http://localhost:3838/{app-folder-name}`.

- [ ] **Step 2: Verify the monitor app end-to-end**

1. Start the docker shiny server; confirm `http://localhost:3838/groundswell_monitor/` loads in a browser.
2. Log into the Groundswell app and open the Monitor Data Collection page.
3. Expected: iframe loads from `http://localhost:3838/groundswell_monitor/`, the loading message clears (auth handshake succeeded), and the app renders its authenticated UI.
4. Check `packages/.sessions/` gains a file while the session is open and it is removed when the session ends.

- [ ] **Step 3: Verify the analysis app end-to-end**

Same as step 2 for the Data Analysis page at `http://localhost:3838/groundswell_analysis/`.

- [ ] **Step 4: Verify the failure mode**

Temporarily set an R app's `.env` `URL` to a different host (e.g. `http://127.0.0.1:7007`), restart it, reload the Laravel page: the auth endpoint must return 422 and the iframe must show the authentication error message (not silently "succeed"). Restore the correct value afterwards.

- [ ] **Step 5: Write the change log**

Per project working patterns: save a summary to `docs/change-logs/`, referencing this plan file, and update this plan's **Status:** line to `Completed` with a link to the change log.
