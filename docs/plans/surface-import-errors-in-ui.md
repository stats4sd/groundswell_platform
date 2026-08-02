# Plan: Surface location + farm import errors in the UI

**Status:** Not Started

## Scope decision

This plan is deliberately narrow: a team-facing review page for **location and farm spreadsheet imports only** (the `Import` model), living in the Survey Locations cluster. A wider review considered widening `imports` into a generic background-operation log and adding an admin cross-system errors page; both were rejected. Admin-side debugging is served by Horizon/Telescope/Sentry (Horizon's broken auth gate is fixed in [fix-horizon-auth-gate.md](fix-horizon-auth-gate.md)), durable per-user error delivery across all jobs is handled by database notifications in [job-failure-db-notifications.md](job-failure-db-notifications.md), and better UX for xlsform drafts/publishing/submission processing is deferred to the September ODK Link package rewrite.

The goal: a team member can open "Past Imports", see each import's status, and read clear messaging like "Row 14, column loc1_code: the value is required" for whatever caused a failure.

## Background

`imports.errors` is written by several code paths and read by none. No Filament resource, page, widget, Livewire component or Blade view anywhere in the tree displays the `Import` model. When an import fails, the wizard's toast has already said "The file will be processed in the background and the data will appear below once complete" (`ImportLocationsAndFarmEntities.php:122`) and then nothing further ever appears. A real failure landed this way: a blank location code in a CSV, `locations.code` is `string` NOT NULL, the row hit the database, the `QueryException` aborted the chunk, the abort took the chained farm import down with it, and both `Import` records ended up holding genuinely useful error text that no screen renders.

### There is no import surface at all

`Import` records are created in three places and are invisible in every one of them:

| Entry point | `Import::create()` | What the user sees afterwards |
| --- | --- | --- |
| `app/Filament/App/Clusters/LocationLevels/Resources/FarmEntityResource/Pages/ImportLocationsAndFarmEntities.php` | `:84-87` (locations) and `:92-95` (farms) | success toast at `:120-124`, then `redirect(FarmEntityResource::getUrl('index'))` at `:126` |
| `app/Filament/Tables/Actions/ImportLocationsAction.php` | `:154-157` | the modal closes; `ViewLocationLevel`'s locations relation manager will fill in if it worked |
| `app/Filament/Tables/Actions/ImportFarmsAction.php` | `:176-179` | the modal closes; `ListFarmEntities`' table will fill in if it worked |

`Team::imports()` already exists (`app/Models/Team.php:148-152`), so the tenant-scoped query is free.

### The `errors` payload has several shapes in the wild

The cast at `app/Models/Import.php:20` is `'collection'`, so `json_decode` output is wrapped in a `Collection` — and a bare JSON string decodes to a string which `Collection::make()` turns into a one-element list.

1. **Nested** — `FarmEntityImport.php:206-214`: `[{location: {row, column}, errors: [...]}]`, one entry per `Maatwebsite\Excel\Validators\Failure`.
2. **Flat** — `FarmEntityImport.php:219-226`, `QueueFarmEntityImport.php:47-54`, `LocationImport::failDependentImport()` and `LocationImport::formatFailures()`: `[{row, attribute, errors: [msg]}]`.
3. **Bare string** — the old `LocationImport` `ImportFailed` handler wrote `$event->getException()->getMessage()` directly. Normalised to shape 2 by the row-validation change on this branch, but legacy rows in existing dev databases still hold it.
4. **Degenerate** — nothing writes it today, but `'errors' => 'a string'` inside shape 2 is one typo away, and the accessor should not care.

### Structural problems being fixed alongside

**Every writer overwrites.** All the write sites use `update(['errors' => ...])`. `Maatwebsite\Excel\Jobs\ReadChunk::failed()` raises `ImportFailed` **once per failing chunk**, so on a multi-chunk file only the last chunk's errors survive. With `chunkSize()` at 1000 in both importers this needs a >1000-row file to bite, which is exactly the size of file a real team uploads.

**`success` is never written.** `imports.success` is `false` on every row ever created, so a derived status cannot use it until an importer starts writing it.

**`Import::user()` points at a column that has never existed.** The relation is declared at `app/Models/Import.php:42-45`, but the migration creates only `id, team_id, model_type, success, errors, timestamps`. Nothing sets `user_id` at any of the create sites.

## Design decisions

- **One canonical error shape**, and normalisation lives in **both** the writers and a read-side accessor. Fixing the writers alone leaves legacy rows holding shapes 1 and 3; fixing only the accessor leaves divergent writers to drift again. The accessor is ~25 lines and is the single point every surface reads through.
- **Status is derived, no `status` column.** `failed` when the normalised error list is non-empty, `complete` when `success` is true, `pending` otherwise — plus one small writer change so `success` is actually written.
- **One migration, additive and optional to the core feature.** `user_id` (fixing the broken relation and giving the list an "Imported by" column) and `finished_at` (so `pending` is distinguishable from "finished 30 seconds ago"). Isolated into Phase 4 so Phases 1–3 can ship without touching schema.
- **The errors screen shows *what* failed; the notification only says *that* it failed and links to the screen.** Rendering a per-row failure list inside a toast is what produces the current wall-of-SQL notification body at `FarmEntityImport.php:233-236`.
- **The resource is navigable.** Teams should be able to browse to their import history, not only reach it via notification links, so the resource registers navigation inside the Survey Locations cluster (label "Past Imports").

---

# Phase 1 — Canonical error shape and a read model on `Import`

Do this first: every later phase reads through it, and it is the only phase with unit-testable logic.

## 1.1 Normalise the writers

| File | Change |
| --- | --- |
| `app/Imports/FarmEntityImport.php:206-214` | flatten the nested shape — `['row' => $failure->row(), 'attribute' => $failure->attribute(), 'errors' => $failure->errors()]`. Drop the `location` wrapper; nothing reads it. |
| `app/Imports/LocationImport.php` | already canonical via `formatFailures()`; no change. Listed so it is not "fixed" by mistake. |
| `app/Imports/FarmEntityImport.php:217-226`, `app/Jobs/QueueFarmEntityImport.php:46-54` | already canonical — no change. |
| all write sites | switch `update(['errors' => ...])` to `$import->appendErrorLines([...])` (see 1.3) so a second failing chunk does not erase the first. |

The canonical shape:

```php
[
    ['row' => ?int, 'attribute' => ?string, 'errors' => array<int, string>],
    // ...
]
```

## 1.2 Read-side accessor

Add to `app/Models/Import.php`, following the existing `public function modelType(): Attribute` style at `:24-31`:

- `errorLines(): Attribute` → `Collection<int, array{row: ?int, attribute: ?string, messages: array<int, string>}>`. Tolerates: null/empty; a bare string element; the nested `location` shape; the flat shape; `errors` being a string rather than an array. Returns **plain arrays**, not objects — `RepeatableEntry`'s child entries resolve state by array key.
- `errorCount(): Attribute` → total message count across all lines. Drives the table's "N problems" column and the `failed` branch of `status`.
- `status(): Attribute` → `'failed' | 'complete' | 'pending'`, derived as above.

Two gotchas to write into the code comments: `modelType()` already **shadows** the raw `model_type` column on read (`Str::of($value)->afterLast('\\')->plural()`), so `TextColumn::make('model_type')` renders `FarmEntities`, not `Farms`, and any `model_type` *filter* must query the raw column rather than the attribute. And `status`/`error_count` are accessors, not columns, so the table cannot sort or search on them in the database.

## 1.3 Append-not-replace helper

`Import::appendErrorLines(array $lines): void` — normalise the existing payload, merge, write back, all inside a `DB::transaction` with `lockForUpdate()` on the row. Two chunk jobs failing on two workers is exactly the concurrent case this exists for.

## 1.4 Write `success` on completion

- `app/Imports/FarmEntityImport.php:241-251` (`AfterImport`) — set `success = true` before sending the notification.
- `app/Imports/LocationImport.php` `AfterImport` handler — same.

Both importers are shared by all entry points (`ImportLocationsAction`/`ImportFarmsAction` instantiate `$this->importClass` from the same classes), so two edits cover every path. No migration: `success` already exists.

---

# Phase 2 — The surfaces

## 2.1 `ImportResource`

New `app/Filament/App/Clusters/LocationLevels/Resources/ImportResource.php`, alongside `FarmEntityResource.php` and `LocationLevelResource.php`:

```
$model = Import::class
$slug = 'imports'
$cluster = LocationLevels::class
$navigationLabel = 'Past Imports' (via t())
$tenantOwnershipRelationshipName = 'team'
$tenantRelationshipName = 'imports'
```

Navigation **is** registered (unlike an earlier draft of this plan) — the page is the team's import history and should be browsable, not link-only. Sort it after the existing cluster items.

**Discovery is free.** `AppPanelProvider.php:143` calls `->discoverClusters(...)`, which recursively discovers `Cluster`, `Page` **and** `Resource` classes under `app/Filament/App/Clusters` — the same mechanism that already registers `FarmEntityResource`. No panel-provider edit needed.

Tenancy is free too: the panel is `->tenant(Team::class)` (`AppPanelProvider.php:43`), `imports.team_id` exists, and `Team::imports()` exists, so Filament scopes the list and blocks cross-tenant record access without a manual `getEloquentQuery()` override. Add a test for it anyway (Phase 5) — it is the assertion that matters most on a page that renders raw exception text.

Pages: `index` (`ListImports`) and `view` (`ViewImport`) only. No create/edit.

**Table** (`ImportResource::table()`):

- `TextColumn::make('created_at')->dateTime()->sortable()` — default sort `desc`.
- `TextColumn::make('model_type')` labelled "Imported" with a `match` mapping `Location::class`/`FarmEntity::class` to `t('Locations')`/`t('Farms')`. Do **not** lean on the pluralising accessor for the label — see the shadowing note in 1.2.
- `TextColumn::make('file_name')` — media-backed accessor (`Import::fileName()` at `:33-40` calls `getFirstMedia()`), so not sortable/searchable, and the table needs `->modifyQueryUsing(fn ($query) => $query->with('media'))` or it is an N+1 per row.
- `TextColumn::make('status')->badge()` with `danger`/`success`/`warning` for `failed`/`complete`/`pending`.
- `TextColumn::make('error_count')` labelled "Problems", `->placeholder('—')`.
- `TextColumn::make('user.name')` labelled "Imported by" — **Phase 4 only**, it needs the `user_id` column.
- `SelectFilter::make('model_type')` on the raw column, and a status filter implemented as a `Filter` with a `->query()` closure (`whereJsonLength('errors', '>', 0)` / `where('success', true)`), because status is derived.
- `->poll('15s')` — the whole point is that a row flips from pending to failed while the user is looking at it.
- `recordActions`: `ViewAction::make()`. Optionally a media-download action so the user can re-open the file that failed; useful, not required.

**Infolist** (`ImportResource::infolist()`), following the `Filament\Schemas\Schema` + `Filament\Schemas\Components\Section` style of `LocationLevelResource::infolist()` at `:130-140`:

- `Section` "Import details" — model type, file name, imported by, started/finished, status badge.
- `Section` "What went wrong" — `RepeatableEntry::make('error_lines')` containing `TextEntry::make('row')` (label "Row", placeholder "—"), `TextEntry::make('attribute')` (label "Column", placeholder "—"), and `TextEntry::make('messages')->listWithLineBreaks()->bulleted()->html()`. Hidden when `error_lines` is empty.
- A collapsed "Technical details" `Section` for the raw single-message case. A `QueryException` message is a full SQL statement with bindings; it is the only thing we have for a non-validation failure, so it must be reachable, but it should not be the first thing on the page.

## 2.2 Escaping

Filament v5 sanitises HTML for you, but only on one of the two paths, and the difference matters here because `FarmEntityImport` already builds `HtmlString` bodies elsewhere (`:233-236`) and `XlsformTemplateLanguageImport.php:130` pushes `<b>` tags into validator messages.

In `vendor/filament/infolists/src/Components/Concerns/CanFormatState.php:376-397`:

- `->html()` sets `$isHtml`, and `formatState()` then runs `Str::sanitizeHtml($state)` — Symfony's `HtmlSanitizer`.
- but a state that is already `Htmlable` takes the `elseif ($state instanceof Htmlable)` branch at `:391-393`, which sets `$isHtml = true` and calls `->toHtml()` with **no sanitisation at all**.

So the rule for this feature: **never** wrap an error message in `HtmlString` or return one from `formatStateUsing()`, and always use `->html()`. Write that as a comment on the `messages` entry, because the natural instinct when a `<b>` renders literally is to reach for `HtmlString`, which is precisely the XSS hole. Accepted cost: a message containing a literal `<` (an exception mentioning `a < b`) will have that fragment sanitised away. That is the right trade for text that originates in exception messages built from spreadsheet cell contents.

The table's error-preview column, by contrast, should stay plain-escaped (no `->html()`) with `->wrap()->limit(120)` — no sanitiser, no markup, no ambiguity.

## 2.3 Policy

New `app/Policies/ImportPolicy.php` with `viewAny`/`view` → `$user->can('view survey locations')`, matching `SurveyLocationsIndex::canAccess()` at `:23-26` — both import types live under Survey Locations, and a single permission avoids a resource that is half-visible depending on which import you are looking at. Add `delete`/`deleteAny` → `maintain list of farms` only if a delete action is included.

**No `AppServiceProvider` edit.** `Import` is in `App\Models`, so Laravel's convention-based policy discovery finds `App\Policies\ImportPolicy`.

## 2.4 `RecentImportsWidget`

The resource page is where you go to investigate. The widget is what tells you there is anything to investigate, on the page the wizard already redirects you to.

New `app/Filament/App/Clusters/LocationLevels/Resources/ImportResource/Widgets/RecentImportsWidget.php extends Filament\Widgets\TableWidget`:

- `->query(fn () => HelperService::getCurrentOwner()->imports()->with('media')->latest()->limit(5))`, `->heading(t('Recent imports'))`, `->paginated(false)`, `->poll('15s')`.
- Entirely hidden when the team has no imports, so it costs nothing on a first visit.
- `columnSpan = 'full'`, matching `FarmListHeaderWidget::$columnSpan`.
- Each row links to `ImportResource::getUrl('view', ['record' => $record])`.

To keep one table definition, extract the column list into a shared static configurator — `App\Filament\Tables\ImportsTable::configure(Table $table): Table` — called by both `ImportResource::table()` and the widget. `app/Filament/Tables/` already exists as the home for shared table code.

Mount it from:

- `ListFarmEntities::getHeaderWidgets()` (`:34-39`) — add alongside `FarmListHeaderWidget`.
- `ViewLocationLevel` — add a `getHeaderWidgets()`. `ViewRecord` inherits `getHeaderWidgets()` from `Filament\Pages\Page`, and `ViewLocationLevel` does not override `getHeader()`, so the default page layout renders header widgets.

Widgets are not auto-discovered on this panel, but explicit registration from `getHeaderWidgets()` needs no discovery — `FarmListHeaderWidget` is the working precedent.

## 2.5 Close the loop on the wizard's own copy

- `ImportLocationsAndFarmEntities.php:120-124` — add `->actions([Action::make('view_imports')->url(ImportResource::getUrl('index'))])` to the toast, and amend the body at `:122` so it says where a failure will show up, not just that success will appear "below". The redirect at `:126` already lands on `ListFarmEntities`, which is where the widget lives.
- Same treatment for the two modal-based actions, whose `importData()` closures (`ImportLocationsAction.php:154-168`, `ImportFarmsAction.php:176-190`) currently `return true` with no user-facing message at all.

---

# Phase 3 — Deep-link the import notifications

The panel-wide `->databaseNotifications()` switch, and the audit of *all* jobs' `failed()` methods, live in [job-failure-db-notifications.md](job-failure-db-notifications.md). This phase covers only the import-specific notification content, and its notifications become durably visible once that plan's panel change lands (either order works; links in a database notification render whenever the bell arrives).

## 3.1 Make the failure notification actionable

In `FarmEntityImport::registerEvents()`'s `ImportFailed` branch (`:229-239`):

- truncate the body — `Str::limit($event->getException()->getMessage(), 200)` — instead of interpolating the whole exception message into an `HtmlString` at `:233-236`. For a `QueryException` that message is a full SQL statement.
- add `->actions([Action::make('view_errors')->url($url)->markAsRead()])` deep-linking `ImportResource::getUrl('view', ['record' => $this->data['import_id']], tenant: $team)`. **The tenant must be passed explicitly** — this runs in a queued job with no Filament tenant context, so `getUrl()` cannot infer it. The `Team` is already resolved in this class from `$this->data['owner_id']`.
- keep both `->sendToDatabase()` and `->broadcast()`: the toast catches a user who stayed on the page, the database row catches the one who left.

## 3.2 Give `LocationImport` and `QueueFarmEntityImport` the same treatment

- `LocationImport`'s `ImportFailed` handler already notifies; add the same deep link.
- `LocationImport`'s `AfterImport` handler is `broadcast()`-only; add `sendToDatabase` so a completed background import is visible to a user who navigated away.
- `QueueFarmEntityImport::failed()` (`:44-55`) — writes the error but notifies nobody. Add a notification using `$this->data['user_id']`. (Noted in the job-failure plan's inventory as handled here — do not double-implement.)
- `LocationImport::failDependentImport()` already writes the "skipped because the location import failed" explanation onto the farm record; with Phase 2 in place that text finally has somewhere to render.

---

# Phase 4 — Two schema niceties (separable)

One additive migration, `database/migrations/2026_XX_XX_XXXXXX_add_user_and_finished_at_to_imports_table.php`, with no `down()` per the convention noted in the farm-cutover plan's A6:

- `user_id` — nullable `foreignId` to `users`, `nullOnDelete`. `Import::user()` at `app/Models/Import.php:42-45` has always pointed at a nonexistent column. Set it at all create sites; every one of them already has `auth()->id()` in scope. Nullable because existing rows have no owner and because a future system-triggered import may have none.
- `finished_at` — nullable timestamp, written next to `success`/`errors` in both `AfterImport` and `ImportFailed` handlers. Lets the status badge say "pending for 40 minutes", and gives the list an honest completion sort.

Optionally then derive a fourth status: `pending` with `created_at` older than an hour and no `finished_at` → `stale`, badged `warning` with "this import may have been interrupted". Worth it because a worker OOM-kill never reaches `ReadChunk::failed()`, so `pending` forever is a real terminal state, not just a transient one.

---

# Phase 5 — Tests

Unit / model (`tests/Feature/Models/ImportErrorNormalisationTest.php`):

- `error_lines` over all four historical shapes plus `null` and `[]` — this is the core of the plan and the cheapest thing to pin.
- `error_count` and `status` derivation across the three (or four) branches.
- `appendErrorLines()` merges rather than replaces — the `ReadChunk::failed()`-per-chunk regression.

Writers (`tests/Feature/Imports/ImportErrorWritersTest.php`): invoke each `ImportFailed` handler directly and assert the canonical shape, using the pattern already established in `tests/Feature/Imports/ImportLocationsAndFarmEntitiesChainingTest.php` (pull the closure out of `registerEvents()` and call it with a hand-built `ImportFailed`). Cover `FarmEntityImport`'s `ValidationException` branch as well as its generic branch — the validation branch is the one changing shape.

Resource (`tests/Feature/Crud/ImportResourceTest.php`):

- tenant scoping — two teams with one `Import` each, `assertCanSeeTableRecords` / `assertCanNotSeeTableRecords`, plus a direct `get()` on the other team's view URL asserting a 403/404.
- `ViewImport` renders row number, column name and message for a multi-line failure.
- **XSS** — an error message of `<script>alert(1)</script>` renders escaped/sanitised, not as a script tag. This is the assertion that protects the `->html()` decision in 2.2; pair it with one asserting that a `<b>` in a message *does* render bold, so a future refactor to `HtmlString` fails the first test rather than silently passing the second.
- the derived status filter returns the right rows.

Host pages: extend `tests/Feature/Crud/FarmEntityResourceColumnsTest.php` to assert `ListFarmEntities` renders the widget with a failed import present, and that it is absent when the team has no imports. Mirror for `ViewLocationLevel`. Both need the `Http::fake()` treatment already used there, since `ListFarmEntities::mount()` calls `refreshFromCentral()`.

Smoke: add `/app/{team}/location-levels/imports` to `tests/Feature/Smoke/AppPanelTest.php`, next to the farms-list test at `:114-119`.

Notifications (`tests/Feature/Imports/ImportFailureNotificationTest.php`): assert a database notification row is created for the recipient and that its stored `actions` payload contains the `ImportResource` view URL. `Notification::assertNotified()` is for session notifications and will not see a `sendToDatabase` one — assert against `$user->notifications` / the `notifications` table instead.

---

# Files to change, in order

| # | File | Why, and why here in the order |
| --- | --- | --- |
| 1 | `app/Models/Import.php` | `errorLines`/`errorCount`/`status` accessors and `appendErrorLines()`. Everything downstream reads through this; it is also the only piece testable without a browser. |
| 2 | `app/Imports/FarmEntityImport.php` | flatten the nested shape at `:206-214`, switch to `appendErrorLines()`, write `success` in `AfterImport` at `:241-251`. |
| 3 | `app/Imports/LocationImport.php` | `appendErrorLines()`, `success` + `sendToDatabase` in `AfterImport`. Error shape is already canonical. |
| 4 | `app/Jobs/QueueFarmEntityImport.php` | `appendErrorLines()` at `:46-54`; already canonical otherwise. |
| 5 | `tests/Feature/Models/ImportErrorNormalisationTest.php`, `tests/Feature/Imports/ImportErrorWritersTest.php` (new) | pin the shape before any UI is built on it. |
| 6 | `app/Policies/ImportPolicy.php` (new) | must exist before the resource, or `ListImports` denies everyone. Auto-discovered; no `AppServiceProvider` edit. |
| 7 | `app/Filament/Tables/ImportsTable.php` (new) | the shared column configurator, so the resource and the widget cannot drift. |
| 8 | `app/Filament/App/Clusters/LocationLevels/Resources/ImportResource.php` + `ImportResource/Pages/ListImports.php` + `ImportResource/Pages/ViewImport.php` (new) | the destination every notification and widget row links to. Auto-discovered via `discoverClusters`. |
| 9 | `app/Filament/App/Clusters/LocationLevels/Resources/ImportResource/Widgets/RecentImportsWidget.php` (new) | needs `ImportResource::getUrl()` to exist for its row links. |
| 10 | `app/Filament/App/Clusters/LocationLevels/Resources/FarmEntityResource/Pages/ListFarmEntities.php` | register the widget at `:34-39`. This is the page the wizard redirects to. |
| 11 | `app/Filament/App/Clusters/LocationLevels/Resources/LocationLevelResource/Pages/ViewLocationLevel.php` | add `getHeaderWidgets()` — the standalone location import currently has no feedback surface whatsoever. |
| 12 | `app/Imports/FarmEntityImport.php`, `app/Imports/LocationImport.php`, `app/Jobs/QueueFarmEntityImport.php` (second pass) | truncated bodies + deep-link actions. Second pass on purpose: it needs `ImportResource::getUrl()`, which does not exist until step 8. |
| 13 | `app/Filament/App/.../Pages/ImportLocationsAndFarmEntities.php`, `app/Filament/Tables/Actions/ImportLocationsAction.php`, `app/Filament/Tables/Actions/ImportFarmsAction.php` | post-submit copy and toast actions pointing at the imports list; `user_id` on `Import::create()` if Phase 4 is taken. |
| 14 | `database/migrations/2026_XX_XX_XXXXXX_add_user_and_finished_at_to_imports_table.php` (new) + the create sites + the `user.name` column | Phase 4. Last, and separable — revert it alone without losing the feature. |
| 15 | `tests/Feature/Crud/ImportResourceTest.php` (new), `tests/Feature/Crud/FarmEntityResourceColumnsTest.php`, `tests/Feature/Smoke/AppPanelTest.php`, `tests/Feature/Imports/ImportFailureNotificationTest.php` (new) | UI and notification coverage, once the surfaces exist. |
| 16 | `CLAUDE.md` | line 48 says "Laravel 11 + Filament 3"; this tree is Laravel 13 and Filament v5.6.7 (`composer.json:15,18`). Fix it while touching Filament code, so the next planner does not design against v3 APIs. |

## Verification

1. `./vendor/bin/pint` and `./vendor/bin/phpstan analyse` after each phase. Expect phpstan to want a generics docblock on the `errorLines` return type.
2. `./vendor/bin/pest` — full suite green, with particular attention to `tests/Feature/Imports/`.
3. `grep -rn "'errors' =>" app` returns only the canonical shape, and no `update(['errors'` remains.
4. Manual, the originating incident: upload a locations+farms spreadsheet with one blank location code. Confirm the wizard redirects to the farms list, the "Recent imports" widget shows the location import as **failed** and the farm import as **failed** with the "skipped because the location import it depends on failed" text, and the view page names the offending row and column. (The bell-notification leg of this check needs [job-failure-db-notifications.md](job-failure-db-notifications.md) to have landed.)
5. Manual, the happy path: a clean file leaves both records **complete** and the widget shows two green rows.
6. Manual, cross-tenant: with two teams, confirm team A's imports list shows only team A's rows, and pasting team B's view URL is refused.
7. Manual, escaping: seed an `Import` by hand whose error message contains `<script>alert(1)</script>` and confirm no dialog appears and no `<script>` element reaches the DOM.
8. Manual, multi-chunk: a >1000-row file with failures in two different chunks accumulates both sets of errors rather than showing only the last.

## Follow-ups this plan does not close

- **`FarmEntityImport::collection()` silently drops rows** whose location lookup returns null (`:102-106`), so a partial import can report success. Once errors are visible, "imported 48 of 50 rows" is a reportable outcome and this deserves a warning-level entry rather than a silent `filter()`.
- **No import history retention policy.** `imports` grows without bound and each row keeps a media file. A prune command / `DeleteAction` is out of scope here.
- **M14** — `owner_id`/`user_id` arrive via client-mutable hidden Livewire fields and are then trusted as the tenancy anchor in both importers (`ImportLocationsAndFarmEntities.php:211-215`, `:283-288`). Carried forward from [farm-crud-cutover-and-import-chaining.md](farm-crud-cutover-and-import-chaining.md); this plan makes the resulting `Import` rows visible but does nothing about who can write them.
- **Xlsform / submission error UX** — deferred to the September ODK Link rewrite; interim durability comes from [job-failure-db-notifications.md](job-failure-db-notifications.md) and [processing-flag-reset-and-dead-column-removal.md](processing-flag-reset-and-dead-column-removal.md).
