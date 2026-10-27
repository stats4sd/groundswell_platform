# Change log: Replace XLSForm list button and farm choice order

Two follow-ups from the local end-to-end test in [docs/plans/2026-10-26-odk-form-deploy-fixes.md](../plans/2026-10-26-odk-form-deploy-fixes.md).

## 1. Replace XLSForm button on the Xlsform Templates list

**Problem:** clicking Replace XLSForm on a row of Admin Panel > Xlsform Templates threw `TypeError: Filament\Actions\Action::getModalContent(): Return value must be of type View|Htmlable|null, array returned`. The action passed the array returned by `XlsformTemplateForm::getCreateFields()` to `->modalContent()`.

**Fix** (`packages/filament-odk-link`, `src/Filament/OdkAdmin/Resources/XlsformTemplates/Tables/XlsformTemplateTable.php`): `->modalContent(fn (XlsformTemplate $record) => XlsformTemplateForm::getCreateFields())` is now `->schema(XlsformTemplateForm::getCreateFields())`, as the template View page's action already did.

The list action also forced `->modalWidth('lg')`, which made its modal narrower than the one on the template View page; that line is removed so both use the default width.

**Test:** `tests/Feature/Crud/AdminPanelCrudTest.php` mounts the action from the list page (`mountTableAction('update_xlsform_template', ...)`); without the fix it fails with the exact TypeError above.

**Not changed:** the list action does not run the XLSForm checks that the View page's action runs before saving (`XlsformValidationHelper::validateTypeOrOther` and `validateColumnHeadersWithLanguageString`), and the two actions still duplicate most of their code.

## 2. Farm choices listed newest first in forms

**Problem:** the farm selection in a form (`select_one_from_file Farm_Summary.csv`) listed the farms F003, F002, F001. ODK Central serves an entity list newest first; entities created in one bulk request share a `createdAt`, and the observed order was by descending internal id.

**Fix** (`app/Services/OdkFarmEntityService.php`): `bulkCreateFarms()` sends the batch to Central in reverse. The local `FarmEntity` rows are still created, and returned, in the uploaded order.

**Tests:** `tests/Feature/Services/OdkFarmEntityServiceBulkCreateOrderTest.php`: the request to Central carries `F003, F002, F001`, and the local records are created in `F001, F002, F003` order. The first test fails without the change.

**Limits:**
- Only farms imported with this change are affected. Entities already on Central keep their order.
- A farm created later (Create farm page) is the newest, so it is listed first.
- The farm import reads the file in chunks of 1000 rows and each chunk is sent separately, so a file with more than 1000 farms is listed one chunk at a time, newest chunk first, each chunk in file order.
- Reverse creation order assumes Central breaks ties on `createdAt` by descending id. Verified locally: importing the 12-farm test file into a team with no farms yet and previewing the Global Indicators form lists the farms F001, F002, F003 in order.

## Checks

Main app suite: 323 passed. Pint passes on the touched files. `phpstan` reports the same single error on `OdkFarmEntityService.php` before and after.
