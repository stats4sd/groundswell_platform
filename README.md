# Groundswell International Platform

## Setup

This setup is updated for the `shiny-dashboards` branch, which includes 2 Shiny Apps embedded into the Monitoring and Analysis pages.

- clone this repo
- checkout shiny dashboards branch
- `git submodule update --init`
- inside the cloned repo folder, run:
    - `composer install`
    - `npm install`

Create the .env files:

- create your local .env file, plus an .env file for each Shiny app:
    - `cp .env.example .env`
    - `cp packages/groundswell_analysis/example.env packages/groundswell_analysis/.env`
    - `cp packages/groundswell_monitor/example.env packages/groundswell_monitor/.env`
    - update the DB\_\* variables, the APP_URL variables.
    - make sure the ODK\_\* variables are set so the system can connect to an ODK Central server.
    - check other required variables

## Variables for Shiny Integration

All embedded Shiny apps are served by a **single** shiny server instance at one root url. Each app is served at `{SHINY_ROOT_URL}/{app-folder-name}/`. See `packages/laravel-shiny-loader/README.md` for the full package docs.

### Laravel .env:

```
### Root url of the shiny server that serves ALL embedded apps.
### Both the browser and this Laravel app must be able to reach it.
SHINY_ROOT_URL="http://localhost:3838"

### Full local path to the packages folder (contains the app subfolders and the shared .sessions folder).
### For local docker testing this is the host path mounted into the container at /srv/shiny-server.
SHINY_ROOT_PATH="/Users/dave/Sites/groundswell_platform/packages"

### Shared secret between Laravel and the shiny apps. Any string; must match the apps' AUTH_KEY.
SHINY_AUTH_KEY="changemetosomething"
```

### Shiny app .envs:

- `LARAVEL_APP_URL` must be the `APP_URL` of the Laravel app.
- `URL` must be the app's url on the shiny server, i.e. `http://localhost:3838/{app-folder-name}` (e.g. `http://localhost:3838/groundswell_monitor`).

## Test Locally (Docker)

The shiny apps run in a single Docker shiny server that mimics the production single-server layout. A custom image (`packages/Dockerfile`) installs both apps' system + R dependencies on top of `rocker/shiny`, and `packages/docker-compose.yml` mounts both app folders and the shared `.sessions` dir into one container serving them at `http://localhost:3838`.

How the R packages are handled:

- `docker compose build` installs the OS libraries and warms renv's package **cache** inside the image (from the lockfiles).
- On first `docker compose up`, the container's startup script runs `renv::restore()` for each app and writes the packages into the bind-mounted app folders — i.e. into `packages/<app>/renv/library/linux-*/...` on your host. These are Linux packages under a platform-specific subfolder, so they sit alongside (do not clash with) your local macOS `renv/library`.
- Because that library lives on the host, it **persists** across stop / start / rebuild. Subsequent starts see the packages already there and the restore is a near no-op.

> The compose file and Dockerfile are for **local testing only** — not a deployment artifact.

### Steps (start to finish)

```bash
cd packages

# 1. The auth handshake needs a shared .sessions dir; create it before first run
#    (otherwise docker creates it root-owned).
mkdir -p .sessions

# 2. Build the image: OS deps + renv cache warmed from both lockfiles.
#    First build on Apple Silicon runs under amd64 emulation and is slow (tens of minutes).
docker compose build

# 3. Start the server. On the FIRST start the container populates each app's
#    renv/library from the cache before shiny-server comes up, so it takes a
#    couple of minutes; watch the logs until you see shiny-server listening.
docker compose up -d
docker compose logs -f        # Ctrl-C to stop following once it's up
```

Then verify:

1. Confirm each app loads on its own at `http://localhost:3838/groundswell_monitor/` and `http://localhost:3838/groundswell_analysis/`. On their own they show a spinner and never finish loading (they are waiting for the auth POST from Laravel).
2. Confirm the libraries landed on the host — these folders should now exist and contain packages:
   ```bash
   ls packages/groundswell_monitor/renv/library/
   ls packages/groundswell_analysis/renv/library/
   ```
3. Load the Laravel app and open the pages with the shiny apps embedded (Monitoring and Analysis). The apps should finish loading after a few moments once they receive the POST request back from Laravel.

Stop / restart / rebuild:

```bash
docker compose down           # stop; the host renv/library folders remain
docker compose up -d          # restart; restore is now a near no-op (packages already present)

# After editing an app's renv.lock, rebuild so the cache picks up new packages,
# then restart — the startup restore adds the new packages to the host library:
docker compose build
docker compose up -d
```

> If you want a completely clean rebuild of the R libraries, delete the host library folders (`rm -rf packages/*/renv/library/linux-*`) before `docker compose up`.

## Setup Staging

Use the instructions at https://www.notion.so/stats4sd/Authenticate-Shiny-with-Laravel-35d59281d4b081c081ace6c6772877b8?source=copy_link (note, Stats4SD internal link for now).
