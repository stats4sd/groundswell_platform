# Change log: Surface location + farm import errors in the UI

Implements [docs/plans/surface-import-errors-in-ui.md](../plans/surface-import-errors-in-ui.md).

`imports.errors` was written by four code paths and read by none. A team whose spreadsheet failed saw a toast saying the file "will be processed in the background", and then nothing, ever. This adds a team-facing "Past Imports" page under the Survey Locations cluster, a recent-imports widget on the two pages the import flows land on, and deep links from the failure notifications — and normalises the four historical error payload shapes behind one accessor so all of it renders the same.

## Changes

### `app/Models/Import.php`
- Added `errorLines()`, `errorCount()` and `status()` accessors. `error_lines` normalises every payload shape `imports.errors` has ever held — the canonical `{row, attribute, errors}`, `FarmEntityImport`'s older `{location: {row, column}, errors}`, a bare exception-message string, and a line whose `errors` is a string rather than an array — into `{row, attribute, messages}` plain arrays (`RepeatableEntry` resolves child entries by array key).
- `status` is derived, not stored: `failed` when there are messages, `complete` when `success` is true, `stale` when an import has been pending over an hour (a worker OOM-killed mid-chunk never reaches `ReadChunk::failed()`, so "pending forever" is a real terminal state), `pending` otherwise.
- Added `appendErrorLines()`, which merges into the existing payload inside a transaction with `lockForUpdate()`. `ReadChunk::failed()` raises `ImportFailed` once per failing chunk, so the previous `update(['errors' => ...])` in every writer kept only the last chunk's problems on any file over the 1000-row chunk size.
- Added `finished_at` to the casts and `@property`/`@property-read` docblocks for the accessors.
- Documented that `modelType()` shadows the raw `model_type` column on read, so any column or filter needing the class name must query the column directly.

### `app/Imports/Concerns/FormatsImportFailures.php` (new)
- `formatFailures()` and `describeFailure()`, extracted from `LocationImport` and now shared with `FarmEntityImport`, so both importers write one payload shape and produce the same plain-text notification summary. `describeFailure()` caps validation summaries at 10 rows, because one badly prepared file can fail every row and a `ValidationException`'s own message ("The given data was invalid.") names nothing.

### `app/Imports/FarmEntityImport.php`
- The `ImportFailed` handler now writes the flat canonical shape via `appendErrorLines()` instead of assigning the nested `location` wrapper, and records `finished_at`.
- The notification body is `Str::limit($this->describeFailure(...), 200)` rather than the whole exception message wrapped in an `HtmlString` — for a `QueryException` that message is a full SQL statement with its bindings.
- Added a `view_errors` notification action deep-linking the import's own page. The tenant is passed to `getUrl()` explicitly because a queued job has no Filament tenant context.
- `AfterImport` now sets `success = true` and `finished_at`, so a completed import stops reading as pending.

### `app/Imports/LocationImport.php`
- Same treatment: `appendErrorLines()`, `finished_at`, truncated plain-text notification body, deep link, `success` on completion, and `sendToDatabase()` added to the completion notification (it was broadcast-only, so a user who navigated away never learned the import finished).
- `failDependentImport()` appends rather than assigns.
- `formatFailures()`/`describeFailure()` moved to the shared trait.

### `app/Jobs/QueueFarmEntityImport.php`
- `failed()` appends rather than assigns, records `finished_at`, and now notifies the user who started the import. Previously this case — the farm import failing before it could even start — was recorded and reported to nobody.

### `app/Filament/Tables/ImportsTable.php` (new)
- The single column definition behind both the resource table and the widget: started, imported (locations/farms), file, status badge, problem count, first-problem preview and imported-by, sorted newest first and polling every 15 seconds so a row flips from in-progress to failed while the user is looking at it.
- The error-preview column is deliberately plain-escaped: no `->html()` and no `HtmlString`, because these strings are built from spreadsheet cell contents.

### `app/Filament/App/Clusters/LocationLevels/Resources/ImportResource.php` (new)
- Team-scoped resource at `/app/{team}/location-levels/imports`, auto-discovered by the panel's `discoverClusters()`, with navigation registered as "Past Imports". Index and view pages only.
- Filters on the raw `model_type` column, plus a status filter that reproduces the derived status in SQL (`whereJsonLength`/`success`/`created_at`) because there is no status column to match.
- Infolist: an "Import details" section; a "What went wrong" section listing every failure that names a row or column; and a collapsed "Technical details" section for messages the importer could not attribute to a row — raw exception text, or the explanation written onto a farm import skipped because its location import failed. That section is only collapsed when there are attributed failures to read first, so a skipped dependent import still explains itself immediately.
- Error messages use `->html()`, which sanitises via `Str::sanitizeHtml`. A comment on that entry records why an `HtmlString` must never be used there: `CanFormatState::formatState()` hands an `Htmlable` straight through with no sanitisation at all.

### `app/Filament/App/Clusters/LocationLevels/Resources/ImportResource/Widgets/RecentImportsWidget.php` (new)
- The team's five most recent imports, hidden entirely until the team has imported something. Mounted as a header widget on `ListFarmEntities` (where the combined wizard redirects) and on `ViewLocationLevel` (where the standalone location import previously had no feedback surface at all).

### `app/Policies/ImportPolicy.php` (new)
- `viewAny`/`view` gated on `view survey locations`, matching `SurveyLocationsIndex::canAccess()`. Auto-discovered by convention; no `AppServiceProvider` edit.

### `database/migrations/2026_08_02_000002_add_user_and_finished_at_to_imports_table.php` (new)
- `user_id` (nullable, `nullOnDelete`) — `Import::user()` had always pointed at a column that did not exist. Now set at all three `Import::create()` sites.
- `finished_at` (nullable timestamp), written alongside `success`/`errors`.

### Import entry points
- `ImportLocationsAndFarmEntities`, `ImportLocationsAction` and `ImportFarmsAction` now set `user_id`, and each post-submit toast says where a failure will show up and carries a link to "Past imports". The two modal actions previously returned `true` with no user-facing message whatsoever.

### Tests
- `tests/Feature/Models/ImportErrorNormalisationTest.php` (new) — `error_lines` over all four historical shapes plus null/empty/degenerate, `error_count`, all four status branches, and that `appendErrorLines()` merges rather than replaces.
- `tests/Feature/Imports/ImportErrorWritersTest.php` (new) — each writer's `ImportFailed` handler produces the canonical shape and accumulates across chunks; `AfterImport` records success.
- `tests/Feature/Imports/ImportFailureNotificationTest.php` (new) — asserts against the `notifications` table rather than `Notification::assertNotified()`, which only sees session notifications; checks the stored action URL and that the body is a summary.
- `tests/Feature/Crud/ImportResourceTest.php` (new) — tenancy (asserted over real requests, since Filament registers its tenancy global scope when the panel boots during a request and a Livewire-only test would pass either way), the view page's rendering of rows/columns/messages, the derived status filter, and a pair of escaping tests: `<script>` never reaches the page while `<b>` still renders. A future refactor to `HtmlString` fails the first while still passing the second.
- `tests/Feature/Crud/FarmEntityResourceColumnsTest.php`, `tests/Feature/Smoke/AppPanelTest.php` — widget presence/absence on the farms list, and a smoke test for the new route.

### `CLAUDE.md`
- Corrected "Laravel 11 + Filament 3" to "Laravel 13 + Filament 5", which is what `composer.json` has required for some time.

## Outcome

`./vendor/bin/pest` — 249 passed, 524 assertions.

`./vendor/bin/phpstan analyse` over `app/` goes from 143 pre-existing errors to 144. The one addition is a self-contradictory `return.type` report on `Import::errorLines()` ("should return `Collection<int, array{...}>` but returns `Collection<int, array{...}>`"), caused by `Illuminate\Support\Collection`'s invariant `TValue` template being compared against the `@property-read` docblock. The docblock is worth keeping: without it phpstan reports roughly fifteen "access to an undefined property" errors against `error_lines`/`error_count`/`status` across the app and test suite.

## Not covered by this change

- `FarmEntityImport::collection()` still silently drops rows whose location lookup returns null, so a partial import can report success. Now that errors are visible, "imported 48 of 50 rows" is a reportable outcome and deserves a warning-level entry rather than a silent `filter()`.
- No retention policy: `imports` grows without bound and each row keeps a media file.
- M14 — `owner_id`/`user_id` still arrive via client-mutable hidden Livewire fields and are trusted as the tenancy anchor in both importers. This change makes the resulting `Import` rows visible but does nothing about who can write them.
