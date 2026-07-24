# =============================================================================
# LOCAL TESTING ONLY - DO NOT USE IN PRODUCTION
# =============================================================================
# Builds a single shiny-server image with BOTH groundswell shiny apps' R
# dependencies installed, so the shiny-loader auth handshake can be verified
# end-to-end locally. Used by docker-compose.yml in this folder; see
# docs/plans/2026-07-22-shiny-loader-single-server-config.md (Task 5).
#
# It is NOT hardened and NOT a deployment artifact:
#   - installs both apps' renv.lock packages, pulling BINARY packages from the
#     Posit Public Package Manager (P3M) for speed
#   - relocates renv's library + cache OUTSIDE /srv/shiny-server so the
#     live-mounted app source (compose volumes) does not shadow the packages
#   - runs shiny-server as root's default config, no auth/tls in front
# =============================================================================

# R 4.5.3 matches both apps' renv.lock "R" version. Base OS is Ubuntu 24.04 (noble).
FROM rocker/shiny:4.5.3

# --- System libraries needed by the apps' compiled R packages -----------------
# Covers the geospatial + graphics + db stack seen in the lockfiles:
#   sf/terra/s2/units      -> gdal, geos, proj, udunits, sqlite3
#   Cairo/svglite/ragg/    -> cairo, xt, fontconfig, freetype, harfbuzz, fribidi,
#     systemfonts/textshaping   png, tiff, jpeg
#   magick                 -> imagemagick (magick++)
#   pdftools               -> poppler
#   rsvg                   -> librsvg2
#   V8                     -> libnode
#   RMariaDB / odbc        -> libmysqlclient-dev (already in the base image), unixodbc
#   sodium / openssl / curl / xml2
# make/gcc/g++ are kept so any package without a P3M binary can compile from source.
# NOTE: libmariadb-dev is intentionally NOT installed - it conflicts with the
# libmysqlclient-dev already present in the base image, which satisfies RMariaDB.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev libssl-dev libxml2-dev libicu-dev zlib1g-dev \
        libgdal-dev libgeos-dev libproj-dev libudunits2-dev libsqlite3-dev \
        libcairo2-dev libxt-dev \
        libfontconfig1-dev libfreetype-dev libharfbuzz-dev libfribidi-dev \
        libpng-dev libtiff-dev libjpeg-dev \
        libmagick++-dev libpoppler-cpp-dev librsvg2-dev libnode-dev \
        unixodbc-dev libsodium-dev \
        make gcc g++ git pandoc \
        cmake gdal-bin libabsl-dev \
    && rm -rf /var/lib/apt/lists/*

# --- renv configuration -------------------------------------------------------
# RENV_PATHS_ROOT=/renv keeps renv's global CACHE inside the image (warmed by the
# build-time restore below) so it is present in every container without a volume.
# We do NOT relocate the library: it stays at the renv default, <app>/renv/library,
# so at runtime it lands in the bind-mounted app folder (i.e. on the host, at the
# usual renv/library/... path) and persists across stop/start/rebuild.
# CACHE_SYMLINKS=FALSE makes the library hold real package copies instead of
# symlinks into /renv/cache - so the host library is self-contained (no broken
# symlinks when inspected on the mac) and stands alone even if the cache is gone.
# REPOS_OVERRIDE forces the noble binary repo so packages install without compiling.
ENV RENV_PATHS_ROOT=/renv \
    RENV_CONFIG_SANDBOX_ENABLED=FALSE \
    RENV_CONFIG_CACHE_SYMLINKS=FALSE \
    RENV_CONFIG_REPOS_OVERRIDE=https://packagemanager.posit.co/cran/__linux__/noble/latest

RUN install2.r --error renv

# --- Warm the package cache at build time -------------------------------------
# Only the renv scaffolding (lockfile + activate/settings + .Rprofile) is copied
# in - just enough to run a restore. This build-time restore's real purpose is to
# populate the image's /renv/cache with every package the lockfiles need; the
# library it also writes here is thrown away at runtime when the compose bind
# mount shadows /srv/shiny-server/<app>. The startup restore (entrypoint.sh) then
# rebuilds the library into the mounted host folder, copying from this warm cache
# so it is fast and needs no downloads. Rebuild the image after editing a
# renv.lock so the cache picks up the new packages.
WORKDIR /srv/shiny-server/groundswell_monitor
COPY groundswell_monitor/.Rprofile ./.Rprofile
COPY groundswell_monitor/renv.lock ./renv.lock
COPY groundswell_monitor/renv/activate.R groundswell_monitor/renv/settings.json ./renv/
RUN R -e "renv::restore(prompt = FALSE)"

WORKDIR /srv/shiny-server/groundswell_analysis
COPY groundswell_analysis/.Rprofile ./.Rprofile
COPY groundswell_analysis/renv.lock ./renv.lock
COPY groundswell_analysis/renv/activate.R groundswell_analysis/renv/settings.json ./renv/
RUN R -e "renv::restore(prompt = FALSE)"

# --- Startup restore hook -----------------------------------------------------
# The entrypoint runs renv::restore() for each app against the bind-mounted
# folders (writing the library onto the host), then exec's the image's default
# CMD (rocker/shiny's shiny-server launcher, inherited unchanged).
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R shiny:shiny /renv /srv/shiny-server
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
# Setting ENTRYPOINT above resets the base image's CMD to empty, so we must
# re-declare it. rocker/shiny runs under s6-overlay: /init is PID 1, which
# supervises the shiny-server service. entrypoint.sh exec's this ("$@") after
# the startup restores, handing off to s6 so the server actually starts.
CMD ["/init"]

WORKDIR /srv/shiny-server
