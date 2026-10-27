# Change log: repair command for the staging form deploy issues (R1 and R2)

Implements the data repair of [docs/plans/2026-10-26-odk-form-deploy-fixes.md](../plans/2026-10-26-odk-form-deploy-fixes.md). One-off command, to be run on staging after the Phase 1 code is deployed.

## Command

```bash
php artisan odk:repair-form-deploy-issues                  # dry run (default): reports only
php artisan odk:repair-form-deploy-issues --apply          # R1 and R2
php artisan odk:repair-form-deploy-issues --only=labels    # R1 only (add --apply to write)
php artisan odk:repair-form-deploy-issues --only=modules   # R2 only (add --apply to write)
```

`app/Console/Commands/RepairFormDeployIssues.php`.

- **R1 (labels):** per template, lists the choice lists whose global entries have no label at all. With `--apply` it runs `AddMissingChoiceListStrings` for the template, then reports how many lists still have unlabelled global entries (those have no labelled same-named list to copy from).
- **R2 (modules):** per form, lists the selected module versions whose module belongs to another template, plus the modules that would get their default version back. With `--apply`, in one transaction per form: detach those versions, `syncWithTemplate()`, `draft_needs_update = true` (set quietly). A form that selects two versions of one of its own modules is only reported.

## Tests

`tests/Feature/Console/RepairFormDeployIssuesTest.php` (6 tests): the dry run reports and changes nothing for both repairs; `--apply` copies the label for R1; `--apply` swaps the foreign version for the form's own default, keeps the other versions and marks the form for a draft update for R2; a form with only its own versions is left alone; an invalid `--only` fails.

Pint and phpstan pass on the command. A dry run on the local (clean) database reports nothing to repair. 

It was also run against a clone of the staging database (dump of 2026-10-27 14:17), `--apply` included:

- R1 reported 0 lists for all three templates. `crop_list` #28 (and #53) already had all 35 global entries labelled in the clone, so R1 is expected to be a no-op on staging.
- R2 found forms 2, 3, 5 and 6 (teams 1 and 2) selecting Local versions of other templates (versions 38, 41, 48, 51). `--apply` detached them and a second dry run reported nothing. Forms 7, 8 and 9 (team 3) had no foreign versions and were not touched.
- The name-based `can_be_replaced` UPDATE affected 5 rows (modules 3, 6, 7, 23, 24); `new farm info` was unchanged.
- The choices export for forms 9 and 3 had no rows without a label.

## Running it on staging

1. Deploy the Phase 1 code (needed for R1) and run `php artisan queue:restart`.
2. `php artisan odk:repair-form-deploy-issues` and read the output.
3. `php artisan odk:repair-form-deploy-issues --apply`.
4. Run the dry run again; it should report nothing for R2, and R1 should no longer list `crop_list`.
