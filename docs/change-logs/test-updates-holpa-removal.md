# Change log: Test updates for HOLPA content removal

Implements [docs/plans/test-updates-holpa-removal.md](../plans/test-updates-holpa-removal.md).

## Changes

### `tests/TestCase.php`
- Removed `use App\Models\Holpa\Theme;` import.
- Removed `public Theme $theme;` property.

### `tests/Feature/Crud/AdminPanelCrudTest.php`
- Removed imports for `DomainResource`, `GlobalIndicatorResource`, and `ThemeResource` pages.
- Removed imports for `App\Models\Holpa\{Domain, GlobalIndicator, Theme}`.
- Deleted the `Admin panel CRUD — Domain`, `Admin panel CRUD — Theme`, and `Admin panel CRUD — GlobalIndicator` describe blocks (93 + 62 + 61 lines).

### `tests/Feature/Smoke/AdminPanelTest.php`
- Deleted three smoke tests hitting removed routes: `domains list loads`, `global indicators list loads`, `themes list loads`.

## Outcome

After these changes, the test suite no longer contains any references to the removed HOLPA classes or routes. Remaining failures in these files are Filament 5 action-namespace breakage (`CreateAction`, `DeleteBulkAction`, `ComponentNotFoundException`) which are out of scope for this change.
