# Plan: Catch-up migrations for updated `filament-odk-link` submodule

**Status: Completed** — implemented on the `filament-5` branch. See the [change log](../change-logs/filament-odk-link-submodule-catchup-migrations.md).

> **Follow-up:** the "column-by-column diff" below was **incomplete** — a later re-diff found five more deltas it missed (`xlsform_modules.row_names`, `dataset_variables.type`/`value_type`, `required_media.links_to_dataset`, `datasets.custom_key`, `datasets.owner_id`). Those are addressed in [the second catch-up plan](filament-odk-link-catchup-missing-columns.md).

## Context

The `packages/filament-odk-link` submodule was updated as part of the Filament 5 / Livewire 4 / Laravel 13 upgrade (branch `filament-5`). The package's `database/migrations/` is the canonical schema for all ODK-link tables, but the host app does **not** load the package migrations. The package registers its migrations with `hasMigrations()` ([FilamentOdkLinkServiceProvider.php:44](packages/filament-odk-link/src/FilamentOdkLinkServiceProvider.php#L44)), which only *publishes* them. The app instead auto-loads its **own copies** from `database/migrations` and every subdirectory ([AppServiceProvider.php:78-83](app/Providers/AppServiceProvider.php#L78-L83)), kept under `database/migrations/03_xlsform_management/`, `04_xlsform_contents/`, `05_xlsform_languages/`.

Those copies are `create_*` migrations that have already run in production, so they must not be edited. The updated submodule introduces new tables and columns that the app's copies lack. This plan adds **new** catch-up migrations (alter/create) to bring the app database in line with the package schema.

App models extend the package models (e.g. [app/Models/Dataset.php:13](app/Models/Dataset.php#L13) extends the package `Dataset`), so they inherit relations that depend on the new schema — e.g. `Dataset::parentDatasets()` / `childDatasets()` are already used in [FarmSurveyDataExport.php](app/Exports/DataExport/FarmSurveyDataExport.php). Without these tables/columns those relations break at runtime.

## Required database changes

Each item below is a delta between the package's current schema and the app's existing copy, verified column-by-column. Items already covered by existing standalone app migrations (e.g. `xlsform_modules.default_order`, `language_strings.text` nullable, `dataset_variables.order`, `xlsform_module_versions.country_id`) are **excluded** — no action needed for those.

### 1. New table: `dataset_parents`
- **Package source:** `033_create_dataset_parents_table.php`. Absent from the app.
- **Why:** Self-referencing many-to-many pivot between `datasets` (a dataset can have many parent datasets and many child datasets — e.g. farm-group ↔ farm ↔ location). Backs `Dataset::parentDatasets()` / `childDatasets()` via the `ParentDatasetPivot` model. The app already calls these inherited relations.
- **Columns (mirror package exactly — confirmed):** `bigIncrements('id')`, `foreignId('parent_id')` → `datasets` cascade on delete/update, `foreignId('child_id')` → `datasets` cascade on delete/update, `timestamps()`.
- **Note (do not act, flag only):** the package's `ParentDatasetPivot` model defines a `foreignKeyVariable()` relation on a `foreign_key_variable_id` column that the package migration does **not** create. We are intentionally mirroring the package migration as shipped; this is an upstream package inconsistency to fix in the package, not here.

### 2. New table: `locale_owner`
- **Package source:** `031_create_locale_owners_table.php`. Absent from the app (the app has `language_owner` only).
- **Why:** Pivot linking `locales` to form owners (teams) — tracks which owners have which locales. Shipped by the package; the app must mirror it to stay schema-compatible.
- **Columns:** `id()`, `foreignId('locale_id')` → `locales` cascade on delete/update, `foreignId('owner_id')` → owner/team table cascade on delete/update, `timestamps()`. Use the team table name the existing app copies use (see `2024_05_10_04_194360_create_language_owner_table.php` for the `$teamTable` pattern).
- **BUG to fix when mirroring:** the package migration's `down()` calls `Schema::dropIfExists('language_owner')` — wrong table. The app migration's `down()` must drop `locale_owner`.

### 3. New column: `datasets.label`
- **Package source:** `000_create_datasets_table.php` line 16 — `string('label')` with comment "which variable in the dataset is the default label". Missing from the app's [datasets copy](database/migrations/03_xlsform_management/2024_03_10_03_101232_1_create_datasets_table.php).
- **Why:** Identifies the default "label" variable for a dataset; used across package Filament forms/exports. App `datasets` rows have no such column.
- **How:** `Schema::table('datasets', fn ($t) => $t->string('label')->nullable()->after('name'))`. **Add as nullable** even though the package declares it `NOT NULL` — the app's `datasets` table is already populated (and seeded in tests), so a non-nullable column with no default would fail. Optionally backfill existing rows from `primary_key`/`name` in the same migration. `down()`: `dropColumn('label')`.

### 4. Alter column: `entities.submission_id` → nullable
- **Package source:** `015_create_entities_table.php` line 20 — `foreignId('submission_id')->nullable()`. The app's [entities copy](database/migrations/03_xlsform_management/2024_03_10_03_101241_10_create_entities_table.php) line 18 has it **NOT NULL**.
- **Why:** Entities can now exist without an originating submission (e.g. imported/cloned/universal-dataset entities). The package relaxed the constraint; the app's stricter column would reject those inserts.
- **How:** drop the existing FK, `foreignId('submission_id')->nullable()->change()` (or raw nullable change), then re-add the FK to `submissions` with cascade on delete/update. Requires `doctrine/dbal` only if using `->change()` on older setups — Laravel 13 supports native column changes. `down()` reverts to non-nullable.

### Optional / low priority (note, likely skip)
- **`entity_values.dataset_variable_name` FK:** the package references `dataset_variables.name` with `cascadeOnUpdate()->cascadeOnDelete()`; the app copy uses a plainer FK. This is a cascade-behavior difference only — the column exists and works. Altering FKs is awkward on the SQLite test DB and carries low benefit. Recommend leaving as-is unless cascade-on-rename of variables becomes a requirement.

## Implementation approach

Add new migration files in the **root** `database/migrations/` directory using dated prefixes, following the existing convention for post-creation alterations (e.g. `2025_06_12_..._add_default_ordering_to_xlsform_modules_table.php`). Do **not** edit the `create_*` copies in the numbered subdirectories. Suggested files (use today's date prefix):

- `..._create_dataset_parents_table.php` (item 1)
- `..._create_locale_owner_table.php` (item 2, with corrected `down()`)
- `..._add_label_to_datasets_table.php` (item 3)
- `..._make_submission_id_nullable_on_entities_table.php` (item 4)

Reuse the `$teamTable = config(...)`/owner-table resolution pattern already used in the existing app copies (grep `$teamTable` in `database/migrations/04_xlsform_contents/`) so the `owner_id` FKs point at the right table.

Each migration must have a correct, reversible `down()`.

## Verification

1. `php artisan migrate` on a fresh DB clone (or `migrate:fresh`) — confirm all four run cleanly and `down()` rolls back without FK errors.
2. `./vendor/bin/pest` — the test suite runs `migrate:fresh` + `DatabaseSeeder` before each test (`$seed = true`); a schema mismatch or bad FK surfaces immediately. Pay attention to dataset/entity/locale seeding.
3. Tinker check: `Dataset::first()->parentDatasets` and `->childDatasets` resolve without "no such table: dataset_parents"; `Locale` owner pivot and `Dataset::first()->label` are accessible.
4. Confirm an `Entity` can be created with `submission_id = null`.
5. `./vendor/bin/phpstan analyse` to ensure no inherited-relation references break.
