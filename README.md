# HOLPA Platform

## Setup

- clone this repo
- inside the cloned repo folder, run:
  - `composer install`
  - `npm install`
- create your local .env file:
  - `cp .env.example .env`
  - update the DB_* variables, the APP_URL variables. 
  - make sure the ODK_* variables are set so the system can connect to an ODK Central server.

## Development

There are 3 ways to set up your environment for local development. The intention is that, at any time, you can reset your local database and environment to one of these 3 pre-configured setups:

### Default; no specific ODK Database Link

This setup will give you a clean database, with 3 teams (linked to new ODK Central projects, if you have the `ODK_*` .env variables required ), and no XlsformTemplate entries. 

To create: 
- `php artisan migrate:fresh --seed`.


### Setup with Mini Test Forms

This setup will give you 3 teams, all linked to the following ODK Projects on our ODK Staging site: 

- 2025 - HOLPA Data Platform LOCAL- P1 Test Team: https://odk-test.stats4sdtest.online/#/projects/1447
- 2025-HOLPA Data Platform LOCAL- P1 Test Team 2: https://odk-test.stats4sdtest.online/#/projects/1448
- 2025-TEST - HOLPA Data Platform LOCAL- Non Program Test Team: https://odk-test.stats4sdtest.online/#/projects/1449

Each team has 2 "mini" forms, which have the required parts to be compatible with the existing HOLPA localisation setup, and very little else. 

To create:

1. Make sure you have `ODK_PLATFORM_PROJECT_ID=1390` in your .env file. 
2. Run the following:

```

php artisan migrate:fresh --seed
php artisan db:seed TestWithMiniForms

# run custom script to copy the media file assets into the storage/app folder
php artisan app:copy-media-test

# run custom command to update your xlsform_versions table, 
# just in case the versions on ODK Central have been changed since the seeder was created.
php artisan app:update-xlsform-versions-from-odk-central
```

### Setup with Real HOLPA Forms

This setup will give you 3 teams, all linked to the following ODK Projects on our ODK Staging site: 

- HOLPA Real Forms - Local Test Team 1: https://odk-test.stats4sdtest.online/#/projects/2728
- HOLPA Real Forms - Local Test Team 2 : https://odk-test.stats4sdtest.online/#/projects/2729
- HOLPA Real Forms - Local Non-Program Test Team: https://odk-test.stats4sdtest.online/#/projects/2730

Each team has copies of the 2 real HOLPA forms, which have the required parts to be compatible with the existing HOLPA localisation setup, and very little else. 

To create:

1. Make sure you have `ODK_PLATFORM_PROJECT_ID=1654` in your .env file. 
2. Run the following:

```
php artisan migrate:fresh --seed
php artisan db:seed TestWithRealForms

# run custom script to copy the media file assets into the storage/app folder
php artisan app:copy-media-real

# run custom command to update your xlsform_versions table, 
# just in case the versions on ODK Central have been changed since the seeder was created.
php artisan app:update-xlsform-versions-from-odk-central
```
