# Fix: AdminPanelCrudTest import namespaces

**Date:** 2026-06-24
**Branch:** `filament-5`

## Summary

Fixed three incorrect `use` import namespaces in [tests/Feature/Crud/AdminPanelCrudTest.php](../../tests/Feature/Crud/AdminPanelCrudTest.php). The package had been reorganised so that each resource's pages live under a plural, non-suffixed directory (e.g. `Datasets/Pages/`) rather than the old `DatasetResource/Pages/` convention, but the test imports had not been updated.

## Changes

| Old import | Fixed import |
|---|---|
| `Resources\DatasetResource\Pages\CreateDataset` | `Resources\Datasets\Pages\CreateDataset` |
| `Resources\XlsformModuleResource\Pages\ManageXlsformModule` | `Resources\XlsformModules\Pages\ManageXlsformModule` |
| `Resources\XlsformModuleVersionResource\Pages\ManageXlsformModuleVersion` | `Resources\XlsformModuleVersions\Pages\ManageXlsformModuleVersion` |

## Remaining failures (not import-related)

After the import fixes, 5 tests still fail due to mismatches between the tests and current component behaviour in the package:

- `can create dataset` — form now requires `description`, `custom_key`, and `label` fields; test only supplies `name`.
- `can create xlsform module` / `create xlsform module requires name` — `getHeaderActions()` is commented out in `ManageXlsformModule`, so no `create` action exists.
- `can delete xlsform module` — `DeleteBulkAction` is not registered on the `ManageXlsformModule` table.
- `can create default xlsform module version` — `getHeaderActions()` is commented out in `ManageXlsformModuleVersion`.
