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
  - `cp packages/groundswell_analysis/example.env packages/groundwell_analysis/.env`
  - `cp packages/groundswell_monitor/example.env packages/groundwell_monitor/.env`
  - update the DB_* variables, the APP_URL variables.
  - make sure the ODK_* variables are set so the system can connect to an ODK Central server.
  - check other required variables

## Variables for Shiny Integration

### Laravel .env:

```
### Should be the full local path to the packages folder, which contain the 2 Shiny apps as subfolders. This folder should also contain a .sessions folder
SHINY_APP_PATH="/Users/dave/Sites/groundswell_platform/packages"

### When testing locally, these will be the ports you run the apps on.
### For live / staging, these should be the urls that each app are served on.
MONITORING_APP_URL="http://127.0.0.1:7007"
ANALYSIS_APP_URL="http://127.0.0.1:7009"
```

### Shiny app .envs:

The important variables are the 2 top ones:

- the LARAVEL_APP_URL must be the APP_URL of the Laravel app;
- The APP_URL must be the url of the Shiny app.


## Test Locally

For each shiny app:

- run the `renv::restore()` to install the needed packages.
- run the app with `shiny::runApp(port=xxxx)`; use the port specified for the app in the .env files.
- The apps on their own should show a spinner, and never complete loading.
- load up the Laravel app, and go to the pages with the Shiny apps embedded. The shiny apps should complete loading after a few moments as they will get the POST request back from Laravel.

## Setup Staging

Use the instructions at https://www.notion.so/stats4sd/Authenticate-Shiny-with-Laravel-35d59281d4b081c081ace6c6772877b8?source=copy_link (note, Stats4SD internal link for now).
