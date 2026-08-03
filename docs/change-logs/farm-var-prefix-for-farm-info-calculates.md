# Change log: `farm_var_` prefix for farm info calculate fields

Fixes duplicate-question-name failures in `DeployDraftXlsformToOdkCentral`. No plan file — diagnosed and fixed directly from the failed job logs.

## The failure

Deployment batch of 2026-08-02 19:12:35–19:12:36 (`storage/logs/laravel.log:3931-3973`). Three forms dispatched, two rejected by ODK Central with HTTP 400 / code 400.15:

- **Global Indicators Local Test** (xlsform 6) — `[row : 10] On the 'survey' sheet, the 'calculation' value is invalid. Reference variables names must be unique anywhere in the 'survey'. The name 'interviewer_name' appears more than once.`
- **Womens Form Local Test** (xlsform 9) — `[row : 41] On the 'survey' sheet, the 'label::English (en)' value is invalid. Reference variables names must be unique anywhere in the 'survey'. The name 'interview_date' appears more than once.`

Generated files inspected: `storage/app/27/Global-Indicators-Local-Test.xlsx`, `storage/app/28/Womens-Form-Local-Test.xlsx`. Farmer Registration (`storage/app/26/`) deployed fine — it creates farm entities rather than pre-loading them, so it has no farm info group.

## Root cause

`FarmInfoModuleBuilder::buildSurveyRows()` created one `calculate` row per dataset variable, named `$variable->name` verbatim. Dataset variables are themselves derived from questions' `save_to` targets, so any question whose `name` equals a `save_to` key — its own or another form's — was guaranteed to collide once the farm info module was attached.

The rows pyxform flags are not the duplicates themselves, but the first place a `${...}` reference becomes ambiguous. Row 10 in Global Indicators is `starttime_calculated`, whose calculation contains `${interviewer_name}`; row 41 in the Womens Form is the generated `farmer_note`, whose label lists ~20 `${...}` references including `${interview_date}`.

Collisions present in the generated Global Indicators file: `interviewer_name`, `interview_date`, `participation`, `participant_is_head`, `household_position`, `name_head`. In the Womens Form: `interview_date`, `participation`, `household_position`.

## Changes

**`app/Services/XlsformModules/FarmInfoModuleBuilder.php`**

- Added `private static string $variablePrefix = 'farm_var_'` and a `prefixed()` helper.
- Injected calculate rows are now named `farm_var_<variable>`. The `calculation` right-hand side still targets the **unprefixed** CSV column (`instance('Farm_Summary')/root/item[name=${id}]/participant_name`), so the entity/dataset contract is unchanged.
- `noteText()` emits `${farm_var_…}` references.
- `deleteStaleCalculateRows()` compares against prefixed names. Without this it would have deleted every newly-created row; as a side effect it also cleans up the old unprefixed rows on the next `populate()` run.

Pint additionally reformatted two pre-existing style issues in the same file (closure indentation in `populate()`, missing space in a `buildSurveyRows()` argument list).

**`tests/Feature/Services/FarmInfoModuleBuilderTest.php`**

- Three assertions retargeted to the prefixed names.
- New test: `it prefixes calculate rows so they cannot collide with a question of the same name elsewhere in the form`.
- The stale-row test previously asserted `farm_certificate_no`, a name that never existed, so it passed vacuously; corrected to `farm_var_certificate_no`.

## Template changes

Both templates were untracked by git, so they were backed up before editing. Each `farm info` module holds the same 9 calculates: `district_name`, `community_name`, `institution`, `sample`, `country`, `household_id`, `participant_name`, `participant_sex`, `participant_age`.

**`_FinalVersionsForPlatformUpload/Groundswell - Global Indicators Form Master Version.xlsx`** — 15 cells:

| Cell | Column | Before | After |
|---|---|---|---|
| survey!E24–E32 | name | the 9 names above | `farm_var_` prefixed |
| survey!AC51 | calculation | `once(${participant_sex})` | `once(${farm_var_participant_sex})` |
| survey!AC52 | calculation | `once(${participant_name})` | `once(${farm_var_participant_name})` |
| survey!AC53 | calculation | `once(${participant_age})` | `once(${farm_var_participant_age})` |
| survey!AC62 | calculation | `coalesce(${sex_head}, ${participant_sex})` | `coalesce(${sex_head}, ${farm_var_participant_sex})` |
| survey!AC64 | calculation | `coalesce(${age_head}, ${participant_age})` | `coalesce(${age_head}, ${farm_var_participant_age})` |
| settings!D2 | instance_name | `concat("GLOBAL:", ${interviewer_name},' - ', ${participant_name},' - ', ${starttime_auto} )` | `${participant_name}` → `${farm_var_participant_name}` |

**`_FinalVersionsForPlatformUpload/Groundswelll - Women's Form Master Version.xlsx`** — 9 cells: survey!E22–E30. This form contains no `${}` references to any farm info calculate; its `instance_name` uses `${interviewername}` and `${respondentname_woman}`, both its own questions.

Deliberately not changed:

- `calculation` right-hand sides on the farm info rows (Global survey!AC24–AC32, Womens survey!AC22–AC30) — Farm_Summary CSV column names, not question references.
- `save_to` values, notably Global survey!D51/D52/D53 = `participant_sex`/`participant_name`/`participant_age`. These are entity property keys; renaming them would break write-back.
- Global survey!AC60 `coalesce(${name_head}, ${participant_name_update})` — references the demographics question, not the farm info calculate.
- `choices!D63`/`F63` in Global, which contain the word "institution" inside English/French label prose.
- `Groundswell - Farm Registration Master Version.xlsx` — no `farm info` module, untouched.

Edits were applied by patching shared-string indices inside the xlsx zip rather than round-tripping through PhpSpreadsheet, so formatting, other sheets and the `=NOW()` version formula in settings!E2 are byte-identical. Verified by diffing every populated cell across all sheets before and after: the only differences are the 24 listed above.

## Follow-up required

Nothing in the repo reads `_FinalVersionsForPlatformUpload` — the templates are manual uploads. To pick up the rename:

1. Re-upload both corrected templates.
2. Re-run team localisation so `FarmInfoModuleBuilder::populate()` regenerates the prefixed rows and drops the stale unprefixed ones.

## Verification

- `./vendor/bin/pest` — 251 passed (529 assertions)
- `./vendor/bin/phpstan analyse` — 180 pre-existing errors, none in the changed files
- `./vendor/bin/pint` — passed

Not verified end-to-end: the fix has not been redeployed to ODK Central, which needs the app running against `central.test:8443`. The templates cannot be validated standalone with `xls2xform` either — they carry the platform's custom `module`/`save_to`/`localisable` columns and are inputs to the builder rather than finished forms.
