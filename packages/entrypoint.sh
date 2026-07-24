#!/usr/bin/env bash
# =============================================================================
# LOCAL TESTING ONLY - startup restore for the single shiny-server image.
# =============================================================================
# The Dockerfile warms renv's package CACHE into the image at build time, but
# the actual project libraries must be written HERE, at container startup,
# because compose bind-mounts each app folder over /srv/shiny-server/<app> and
# a build-time library would be hidden by that mount.
#
# Running renv::restore() now writes real package copies into
#   <app>/renv/library/<linux-platform>/...
# which, because the folder is bind-mounted, lands on the host and persists
# across stop/start/rebuild. On the next start renv sees the packages already
# present and the restore is a near no-op (it copies missing packages from the
# warm image cache - no download/compile).
set -euo pipefail

for app in groundswell_monitor groundswell_analysis; do
    dir="/srv/shiny-server/${app}"
    if [ -f "${dir}/renv.lock" ]; then
        echo "[entrypoint] renv::restore() for ${app}"
        # cd into the app so its .Rprofile activates renv (renv/activate.R),
        # then restore into the (bind-mounted) project library.
        ( cd "${dir}" && R -e "renv::restore(prompt = FALSE)" )
    fi
done

# shiny-server runs each app as the 'shiny' user; make sure it can read the
# freshly restored libraries. (On Docker Desktop macOS bind mounts this is
# effectively a no-op, hence the guard.)
chown -R shiny:shiny /srv/shiny-server 2>/dev/null || true

# Hand off to the image's default command (rocker/shiny's shiny-server launcher).
exec "$@"
