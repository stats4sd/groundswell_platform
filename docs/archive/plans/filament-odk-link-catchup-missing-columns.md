# Plan: Second round of catch-up columns for the `filament-odk-link` submodule

**Status: Completed** — see [change log](../../change-logs/filament-odk-link-catchup-missing-columns.md). Follow-up to [the first catch-up plan](filament-odk-link-submodule-catchup-migrations.md), which was completed but whose "column-by-column diff" turned out to be incomplete. A full re-diff of every package migration against the app schema (create-copies + standalone alters + the four migrations added by the first plan) found five further deltas the first pass missed. This plan adds the migrations to close them.

## Context

Same mechanism as the first plan: the host app does not load the package's migrations — it auto-loads its own already-run `create_*` copies, so new package schema must land as **new** alter migrations rather than edits to the copies. The package's `database/migrations/` remains the canonical schema.

The trigger for this plan was `xlsform_modules.row_names`: it is new in the package's `003_create_xlsform_modules_table.php`, the app lacks it, and the first plan didn't catch it. Re-running the diff exhaustively surfaced four more genuinely missing columns plus the `datasets.owner_id` change. Each missing column is **actively read or written by package code the app inherits**, so each is a latent runtime SQL error, not a cosmetic difference.

## Required database changes

Each item is a delta where the package has a column (or constraint) the app does not. Cascade-only FK differences and app-ahead-of-package columns are excluded — see "Out of scope" below.

### 1. New column: `xlsform_modules.row_names`
- **Package source:** `003_create_xlsform_modules_table.php` — `$table->json('row_names')->nullable()`.
- **Why:** `XlsformModule` casts `row_names` to `'collection'`; it is written by `XlsformModuleImport` and read by `GetsModuleNamesPerRow`. Without the column, module import throws on the missing column.
- **How:** `Schema::table('xlsform_modules', fn ($t) => $t->json('row_names')->nullable()->after('can_be_replaced'))`. `down()`: `dropColumn('row_names')`.

### 2. New columns: `dataset_variables.type` and `dataset_variables.value_type`
- **Package source:** `016_create_dataset_variables_table.php` — `$table->string('type')` and `$table->string('value_type')->nullable()`.
- **Why:** `DatasetVariable::upsert(..., ['name','dataset_id'], ['label','type','value_type'])` at `XlsformTemplate.php:461` writes both columns; `value_type` is also read in `OdkSubmissionService` to detect `select_multiple` fields. Without the columns, template processing throws.
- **How:** `Schema::table('dataset_variables', fn ($t) => { $t->string('type')->nullable()->after('label'); $t->string('value_type')->nullable()->after('type'); })`. `down()`: drop both.
- **Important — add `type` as `nullable()`, NOT matching the package literally.** The package migration declares it `$table->string('type')->nulllable()` — note the typo (three L's). Laravel's fluent `__call` silently swallows the unknown `nulllable` attribute, so in the package the column is actually **NOT NULL with no default** — an upstream bug. The app's `dataset_variables` table is already populated/seeded, and the upsert does not always supply `type` for every row, so a NOT NULL column with no default would fail. Add it `nullable()` to both stay safe and avoid reproducing the bug. (Flag the typo upstream.)

### 3. New column: `required_media.links_to_dataset`
- **Package source:** `011_create_required_media_table.php` — `$table->boolean('links_to_dataset')->default(false)`.
- **Why:** `RequiredMedia` casts `links_to_dataset` to `boolean`; it is set during import (`XlsformTemplateChoiceListImport`), read in `OdkFormMediaService`, and drives field visibility in `XlsformTemplateForm`. Without the column, media import and the template form throw.
- **How:** `Schema::table('required_media', fn ($t) => $t->boolean('links_to_dataset')->default(false)->after('updated_during_import'))`. `down()`: `dropColumn('links_to_dataset')`.

### 4. New column: `datasets.custom_key`
- **Package source:** `000_create_datasets_table.php` — `$table->string('custom_key')->nullable()`.
- **Why:** The package admin `DatasetForm` renders `TextInput::make('custom_key')`; saving a dataset through that form in the app would fail on the missing column.
- **How:** `Schema::table('datasets', fn ($t) => $t->string('custom_key')->nullable()->after('primary_key'))`. `down()`: `dropColumn('custom_key')`.
- **Note:** the app keeps its own `primary_key` column (NOT in the package's current migration). Package code still reads `dataset->primary_key` (`Entity`, `EntityExport`, `DatasetInfoList`), so the app's `primary_key` must stay. `custom_key` is added **alongside** it, not as a replacement. (The package itself is inconsistent — its migration renamed `primary_key`→`custom_key` but left `primary_key` reads in code; flag upstream.)

### 5. New column + constraint: `datasets.owner_id`
- **Package source:** `000_create_datasets_table.php` — `$table->foreignId('owner_id')->nullable()->constrained($ownerTable)` plus `$table->unique(['name','owner_id'])`.
- **Why:** This is the intended direction of travel. Earlier versions identified a dataset's owner polymorphically via `model_id`/`model_type`; ownership has since been restricted to a **single** model type (the form-owner / `Team`), so the polymorphic indirection is no longer needed and the package now carries a direct `owner_id` FK. `Dataset::owner()` is `belongsTo(form_owner, 'owner_id')`, and `HasXlsforms::datasets()` is `hasMany(Dataset::class, 'owner_id')` — both depend on the column. Without it those inherited relations break.
- **How:**
  - Resolve the owner table from the **new** `form_owner` key the package itself uses — `$ownerTable = (new (config('filament-odk-link.models.form_owner')))->getTable();`. This depends on the config change below landing first; it deliberately does not reuse the deprecated `team_model` key. (Both resolve to the `teams` table today, so behaviour is identical — this just starts the app's move onto `form_owner`.)
  - `Schema::table('datasets', fn ($t) => $t->foreignId('owner_id')->nullable()->constrained($ownerTable)->after('model_id'))`.
  - `down()`: drop the FK then `dropColumn('owner_id')`.
- **Backfill:** none required. The app's `DatasetSeeder` creates datasets as global/unowned (`name` + `primary_key` + `entity_model` only), so `owner_id` stays `NULL` for all existing rows. No data migration from the morph is needed.
- **Morph columns:** **retain** `model_id`/`model_type`. The package keeps them too (it added `owner_id` alongside the morph, it did not drop the morph), so retaining them keeps the app schema-compatible. The morph is now effectively dormant for datasets. Fully removing `model_id`/`model_type` would be a separate app-and-package-wide cleanup, out of scope for a schema catch-up that mirrors the package.
- **Unique constraint — deliberate deviation from the package.** The package uses `unique(['name','owner_id'])`; the app's copy uses `unique('name')`. **Keep the app's existing `unique('name')` and do NOT switch to the composite.** Because every app dataset has `owner_id = NULL`, a `(name, owner_id)` unique index would not enforce name-uniqueness at all (SQL treats NULLs as distinct), silently dropping the integrity the app currently relies on. This mirrors the first plan's pattern of deliberately deviating where the app's populated/seeded state demands it (cf. the nullable-`label` decision). If per-owner datasets are introduced later, revisit the index then.

## Config change: add `form_owner` (prerequisite for `owner_id` to function)

The app's published `config/filament-odk-link.php` defines only `team_model` and `user_model` under `models` — it does **not** define `form_owner`. The package has fully migrated to the new key: its `src/` references `config('filament-odk-link.models.form_owner')` in 14 places (`Dataset::owner()`, `Entity::owner()`, `OdkDataset`, `ChoiceListEntry`, `XlsformModuleVersion`, `HasXlsforms`, `Submission`, etc.) and **no longer references `team_model` at all**. Because `mergeConfigFrom` replaces the whole `models` array with the app's published copy, `form_owner` resolves to `null` in the app, so `belongsTo(null, 'owner_id')` will not work even after the `owner_id` column exists.

This is a config edit, not a migration, but it is a hard prerequisite for item 5 and is part of this plan.

- **Add the `form_owner` key**, env-backed and defaulting to `Team::class`, matching the package's own default (`ODK_FORM_OWNER_MODEL`):

  ```php
  'models' => [

      /**
       * The single model type that owns forms / datasets / locales etc.
       * The package has standardised on this key; it replaces the old polymorphic
       * owner and the deprecated `team_model` key below.
       */
      'form_owner' => env('ODK_FORM_OWNER_MODEL', Team::class),

      /**
       * @deprecated The package no longer reads `team_model` — it uses `form_owner`.
       * Kept only until the app's own migrations/code that still reference
       * `models.team_model` have been moved over (see review note below).
       */
      'team_model' => env('ODK_TEAM_MODEL', Team::class),

      'user_model' => env('ODK_USER_MODEL', User::class),
  ],
  ```

- **Keep `team_model` for now, commented as deprecated** (as above). It is still referenced by 10 of the app's existing migration files (`database/migrations/04_xlsform_contents/`, `05_xlsform_languages/`, and the first plan's `2026_06_24_000002_create_locale_owner_table.php`). Those have already run in production, so the key cannot simply be deleted.

- **Review the app to confirm full migration to `form_owner`.** Audit every app reference to `models.team_model` (currently only the 10 migration files — no app runtime code uses it) and move them to `form_owner`, then remove the deprecated `team_model` key in a later cleanup. Both keys resolve to `Team::class` today, so the migration is mechanical and low-risk; this note exists so the deprecated key does not linger indefinitely.

## Implementation approach

Add new migration files in the **root** `database/migrations/` directory, continuing the dated sequence used by the first plan (`2026_06_24_0000NN_...`) so they sort after the existing history and run last (their tables already exist). Do **not** edit the `create_*` copies. Suggested files:

- `2026_06_24_000005_add_row_names_to_xlsform_modules_table.php` (item 1)
- `2026_06_24_000006_add_type_and_value_type_to_dataset_variables_table.php` (item 2)
- `2026_06_24_000007_add_links_to_dataset_to_required_media_table.php` (item 3)
- `2026_06_24_000008_add_custom_key_to_datasets_table.php` (item 4)
- `2026_06_24_000009_add_owner_id_to_datasets_table.php` (item 5)

Each migration must have a correct, reversible `down()`. The `owner_id` FK resolves its table from `config('filament-odk-link.models.form_owner')` (added by the config change above), following the same `(new (config(...)))->getTable()` pattern the existing copies use for `team_model`.

Land the **config change first** (or in the same commit) so `form_owner` resolves when migration 000009 runs.

## Out of scope (verified, no action)

- **App-ahead-of-package columns** — the app already has these and the package does not; leave as-is: `xlsforms.odk_project_id`, `xlsforms.is_draft`, `xlsform_versions.is_draft`, `submissions.survey_started_at` / `survey_ended_at` / `survey_duration`, `dataset_variables.order`, `xlsform_module_versions.country_id`, and the app-only `country_language` table.
- **Cascade / FK-behaviour-only differences** (columns exist, only the FK behaviour differs): `entity_values.dataset_variable_name` (already noted in the first plan), `xlsforms.xlsform_template_id`, `entities.dataset_id`, `selected_xlsform_module_versions.xlsform_module_version_id`. Altering FKs on the SQLite test DB is awkward for low benefit.
- **`xlsform_modules.default_order`** — app added it `default(0)` NOT NULL via a standalone alter; the package has it `nullable()`. Functionally equivalent for the app's usage; no change.
- **`datasets.primary_key`** — app-only relative to the package's current migration, but package code still reads it. The app must keep it (it already does); no change.

## Verification

1. `php artisan migrate` on a fresh DB clone (or `migrate:fresh`) — confirm all five run cleanly and `down()` rolls back without FK errors.
2. `./vendor/bin/pest` — the suite runs `migrate:fresh` + `DatabaseSeeder` before each test (`$seed = true`); a schema mismatch or bad FK surfaces immediately. Watch dataset / dataset-variable / required-media seeding.
3. Tinker checks: `DatasetVariable::first()->type` and `->value_type` are accessible; `RequiredMedia::first()->links_to_dataset` returns a bool; `XlsformModule::first()->row_names` resolves; `Dataset::first()->custom_key` and `->owner_id` are accessible.
4. Confirm a template re-import runs the `DatasetVariable::upsert([...,'type','value_type'])` path without a SQL error.
5. `./vendor/bin/phpstan analyse` to ensure no inherited-relation references break.
6. Confirm the `form_owner` config key is present and `Dataset::first()->owner()` resolves (not `belongsTo(null, ...)`) before relying on owner-scoped relations.
