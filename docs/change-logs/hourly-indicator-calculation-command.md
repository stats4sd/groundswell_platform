# Change log: hourly indicator calculation command

## What changed

New Artisan command `app:calculate-indicators` shells out to an external R script to run indicator calculation for a project. It's registered on the hourly scheduler in [routes/console.php](../../routes/console.php).

- `app/Console/Commands/CalculateIndicators.php` — accepts four positional arguments: `project` (an `odk_projects.id`) and `xlsform_1`/`xlsform_2`/`xlsform_3` (three `xlsforms.odk_id` values). It validates the project and all three xlsforms exist before shelling out, then runs `Rscript <script> <project.id> <xlsform_1> <xlsform_2> <xlsform_3>` via `Process`, streaming output to the console.
- `config/services.php` — added `services.R.indicator_calculation_script_path`, read from a new `INDICATOR_CALCULATION_SCRIPT_PATH` env var. The command fails fast with a clear error if it's unset.
- `routes/console.php` — registered `Schedule::command('app:calculate-indicators', [...])->hourly()->withoutOverlapping()` with placeholder project/xlsform arguments (`1`, `xlsform_1`, `xlsform_2`, `xlsform_3`) and a `TODO` comment, since the real values weren't decided as part of this change.

## Still to do

Before this runs for real: set `INDICATOR_CALCULATION_SCRIPT_PATH` (and `RSCRIPT_PATH` if not already set) in `.env`, and replace the placeholder arguments in `routes/console.php` with the actual project id and xlsform odk_ids. No R script exists in this repo for this yet — one needs to be supplied at the configured path.
