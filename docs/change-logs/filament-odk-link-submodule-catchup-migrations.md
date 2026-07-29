# `filament-odk-link` submodule catch-up migrations — change log

Records the work executing [the plan](../archive/plans/filament-odk-link-submodule-catchup-migrations.md) on the `filament-5` branch: adding host-app migrations to match schema introduced by the updated `filament-odk-link` submodule.

## Why

The `packages/filament-odk-link` submodule was updated during the Filament 5 / Livewire 4 / Laravel 13 upgrade. Its `database/migrations/` is the canonical schema, but the host app does **not** load the package's migrations — the package registers them with `hasMigrations()` (publish-only), while the app auto-loads its **own copies** from `database/migrations` + every subdirectory ([AppServiceProvider.php:78-83](../../app/Providers/AppServiceProvider.php#L78-L83)). The app copies are already-run `create_*` migrations, so the new package schema had to land as **new** catch-up migrations rather than edits.

A column-by-column diff of every package migration against its app copy found four genuine deltas (others — `xlsform_modules.default_order`, `language_strings.text` nullable, `dataset_variables.order`, `xlsform_module_versions.country_id` — were already covered by existing standalone app migrations).

> **Correction:** that diff was incomplete. A later exhaustive re-diff found five more missing columns/constraints (`xlsform_modules.row_names`, `dataset_variables.type`/`value_type`, `required_media.links_to_dataset`, `datasets.custom_key`, `datasets.owner_id`), tracked in [the second catch-up plan](../archive/plans/filament-odk-link-catchup-missing-columns.md).

## Migrations added

All in [database/migrations/](../../database/migrations/), dated `2026_06_24` so they sort after the existing history and run last (their referenced tables already exist):

1. **`2026_06_24_000001_create_dataset_parents_table.php`** — new `dataset_parents` pivot (`parent_id`, `child_id`, `timestamps`). Backs the inherited `Dataset::parentDatasets()` / `childDatasets()` relations (`ParentDatasetPivot`), already used in `app/Exports/DataExport/FarmSurveyDataExport.php`. Mirrors package `033` exactly — deliberately **omits** `foreign_key_variable_id` (see open issue below).
2. **`2026_06_24_000002_create_locale_owner_table.php`** — new `locale_owner` pivot (`locale_id`, `owner_id`, `timestamps`) linking locales to form owners. Mirrors package `031`, **fixing** that migration's `down()` bug (it drops `language_owner`, the wrong table). Uses `config('filament-odk-link.models.team_model')` to match the app's published config, not the package's newer `form_owner` key.
3. **`2026_06_24_000003_add_label_to_datasets_table.php`** — adds `datasets.label` (the dataset's default-label variable, used across package forms/exports). Added **nullable** even though the package declares it `NOT NULL`, because the app's `datasets` table is already populated/seeded; a non-nullable column with no default would fail.
4. **`2026_06_24_000004_make_submission_id_nullable_on_entities_table.php`** — relaxes `entities.submission_id` to nullable (drop FK → `->nullable()->change()` → re-add cascade FK), matching package `015`. Entities can now exist without an originating submission (imported / cloned / universal-dataset entities).

## Not done (deliberately)

- **`entity_values.dataset_variable_name` FK cascade difference** — the package references `dataset_variables.name` with cascade on update/delete; the app copy uses a plainer FK. Column exists and works; altering FKs on SQLite is awkward for low benefit. Left as-is.

## Verification

- `php artisan migrate` against a temporary file SQLite DB: all four run clean; `migrate:rollback --step=4` reverses all four with no FK errors.
- Schema confirmed: `dataset_parents` = `id,parent_id,child_id,created_at,updated_at`; `locale_owner` = `id,locale_id,owner_id,created_at,updated_at`; `datasets.label` present; `entities.submission_id` `notnull` flag = 0 (nullable).
- Pest boots `migrate:fresh` + `DatabaseSeeder` cleanly (DB-backed smoke tests that don't depend on the in-progress Filament 5 view fixes pass). The remaining smoke-test failures are pre-existing F5 namespace issues (`Filament\Tables\Actions\EditAction` not found), unrelated to these schema changes.

## Open issue to raise upstream (in the package, not here)

`ParentDatasetPivot::foreignKeyVariable()` references a `foreign_key_variable_id` column on `dataset_parents` that the package's own `033` migration never creates. We mirrored the package migration as shipped; the package should either add the column to `033` or drop the relation.
