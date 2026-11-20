# Plan: explicit "Update test forms" button on Initial Pilot

**Status: In Progress.** Written 2026-11-19, reviewed four times against the code the same day. Implemented and tested locally on 2026-11-20 (main 460 passed); change log: [2026-11-19-initial-pilot-explicit-update-button.md](../change-logs/2026-11-19-initial-pilot-explicit-update-button.md). Left to do: Dan commits the submodule and main repo, deploys with `php artisan migrate`, verifies on staging (section 8), then this plan is marked Completed. The package has no `docs/` folder, so no package plan or change log was added.

Branches: main repo (page, job, notification, tests, migration copy, submodule bump); submodule `packages/filament-odk-link` (claim, snapshot, jobs, migration), PR base `staging-hotfix-20261026`.

## 1. Problem

`InitialPilot::mount()` calls `Team::deployDraftForms()` on every page load, so a read view rebuilds forms as a side effect.

Read from the code:

1. It runs whenever any form has `draft_needs_update = true`. That flag is set by `SurveyRow` saves, location/farm edits, choice-list changes, optional modules, informed consent, HDDS hints and the translation import (`NotifyUserThatLanguageImportIsComplete`).
2. It runs `localiseXlsforms()` synchronously in the request, then `localiseModules()` and `deployDraft()` per form.
3. `Xlsform::deployDraft()` guards with `if ($this->processing)`, which is check-then-set. `processing` is only true while the workbook is generated: `generateXlsfile()` sets it, `UpdateXlsformFile` clears it in `finally` **before** `DeployDraftXlsformToOdkCentral` runs, and the deploy job never clears it on success. During that gap, and during the deploy's retries, a second page load queues a second full chain. This is the source of duplicate deploys.
4. Three jobs per form from a page view; no user control; the only status is a spinner while the flag is true.
5. The Echo listener `handleXlsformDraftWasDeployed` calls `->find($id)->refresh()`; for another team's event `find()` returns null and the handler throws. The event only fires after a deploy changes `draft_needs_update` true to false while `live_needs_update` is true, so it is not a reliable signal.
6. The deploy job sets `draft_needs_update = false` at its end, so an edit saved while an update runs loses its flag.

Alex's real case: after a translation import the forms are only flagged; nothing deploys them until someone opens Initial Pilot.

## 2. Decisions (agreed with Dan, 2026-11-19)

- Option b: opening the page never changes anything; an explicit "Update test forms" button starts the update.
- After a translation import: only add a link to Initial Pilot to the "import complete" notification. The import does not queue the update.
- The claim lives in the package, not a `Cache::lock`.
- An outdated form never shows its QR code; it shows "EXPIRED". People scan a QR code the moment they see it.
- Every form shows "Includes all changes made up to YYYY-MM-DD HH:MM:SS (timezone)", app timezone, with its label. Per-user timezones are out of scope.

"Claim" = one atomic `UPDATE xlsforms SET processing = 1, draft_claimed_at = now WHERE id = ? AND (processing = 0 OR draft_claimed_at IS NULL OR draft_claimed_at < now - 15 min)`; only a caller that changes a row owns the form. It is held for the whole chain (generate file, attach, deploy) and released by the last job, on success or failure. A claim nobody releases (killed worker, lost job) expires by itself after 15 minutes, so nobody ever needs a database fix.

The expiry clock is a dedicated column, `draft_claimed_at`, not `updated_at`: every edit does `xlsforms()->update([...])` (location, farm, survey row, choice entry, ...), which bumps `updated_at` on all of the team's forms, so a leaked claim would never look old while the team keeps working.

## 3. Design

### 3.1 States of a form (one definition, used by page, action and job)

`Xlsform` gets three small methods (package):

- `hasActiveClaim()`: `processing` and `draft_claimed_at` not null and within 15 minutes (`CLAIM_TTL_MINUTES = 15`). A form with `processing = 1` and no `draft_claimed_at` (set by older code, or leaked before this release) is a stale claim, so rows stuck today recover at once after the release.
- `draftIsOutdated()`: `draft_needs_update` or no draft (`! has_draft`) or a stale claim (`processing` but not active).
- state: `updating` if `hasActiveClaim()`; else `missing` if `! has_draft`; else `expired` if `draftIsOutdated()`; else `current`.

Only `current` shows a QR code.

### 3.2 Flag and snapshot semantics

- The flag means "something changed after the last snapshot". It is cleared when the snapshot is taken (inside `deployDraft()`), **not** at the end of the deploy job, and set again on any failure. An edit saved during an update therefore keeps the flag true.
- `draft_snapshot_at` (new nullable timestamp on `xlsforms`) = the time `deployDraft()` started, after the app job's localise step and before the workbook is generated. It is written by the deploy job on success. Everything saved before it is in the draft; a change saved just after may or may not be. Wording is "includes all changes made up to".
- The flag is cleared after the localise step on purpose: the localise step saves survey rows, and the `SurveyRow` saved hook re-flags the forms. Clearing earlier would leave every form permanently "Needs update".
- Publish-triggered re-deploy of the draft (`published: true`) reuses the existing file: it neither clears the flag nor changes the snapshot.

### 3.3 Package changes (`packages/filament-odk-link/src/` unless noted)

| File | Change |
|---|---|
| `Models/OdkLink/Xlsform.php` | Add `claimForDeployment()`, `releaseClaim(bool $needsUpdate)`, `hasActiveClaim()`, `draftIsOutdated()`, state helper, `CLAIM_TTL_MINUTES`. Claim uses `Xlsform::withoutGlobalScopes()` (the `owned` scope does not apply in queue/console) and an Eloquent builder `update()` (no model events fire), sets `processing = true` and `draft_claimed_at = now()`, then syncs the in-memory attributes. `releaseClaim(true)` sets `processing = false`, `draft_claimed_at = null` and `draft_needs_update = true` with a query update; `releaseClaim(false)` leaves the flag alone. Cast `draft_snapshot_at` and `draft_claimed_at` as datetime (add to the existing `$casts` at line 44). |
| `Models/OdkLink/Xlsform.php` `deployDraft()` | New signature `deployDraft(bool $withMedia = true, bool $published = false, bool $alreadyClaimed = false, bool $holdClaim = false, ?Authenticatable $user = null)`. Flow: claim unless `$alreadyClaimed` (return null if it fails); `$user ??= auth()->user()`; if `$published`: dispatch the deploy job with no snapshot and `$holdClaim`; else `$snapshotAt = now()`, clear the flag (query update), then `try { generateXlsfile($user)->chain([new Deploy(..., $snapshotAt, $holdClaim)]) } catch (Throwable) { releaseClaim(true); throw; }`. Existing positional callers (`deployDraft(true)`, `deployDraft()`) are unchanged. |
| `Models/OdkLink/Xlsform.php` `generateXlsfile()` | Add `?Authenticatable $user = null` (default `auth()->user()`); build `UpdateXlsformFile` with `holdClaim: true`. Its only caller is `deployDraft()`. Leave the existing `updateQuietly(['processing' => true])`. |
| `Models/OdkLink/Xlsform.php` `publishForm()` | Take the claim first: `if (! $this->claimForDeployment()) return null;` (replaces the `if ($this->processing)` check; the existing "returns null while processing" test still holds). If `draft_needs_update`: `deployDraft(alreadyClaimed: true, holdClaim: true, user: $user)` chained with the publish job; else dispatch the publish job. The old "deployment is null" branch becomes unreachable; keep it as a guard. |
| `Jobs/XlsformDeployment/UpdateXlsformFile.php` | New property `holdClaim` (default false). When true, `finally` does not clear `processing`. `failed()` calls `releaseClaim(true)`. |
| `Jobs/XlsformDeployment/DeployDraftXlsformToOdkCentral.php` | New properties `?Carbon $snapshotAt = null`, `bool $holdClaim = false`. In `handle()` for an `Xlsform`: remove `draft_needs_update = false`; write `draft_snapshot_at` when `$snapshotAt` is set; after the version `updateOrCreate`, if not `holdClaim`, release the claim (`releaseClaim(false)`: `processing = false`, `draft_claimed_at = null`, flag untouched). `failed()` for an `Xlsform` calls `releaseClaim(true)`; templates keep today's behaviour. |
| `Exports/XlsformExport/XlsformWorkbookExport.php` | `failed()` calls `releaseClaim(true)`. |
| `Jobs/XlsformDeployment/PublishXlsformOnOdkCentral.php` | Trailing call becomes `deployDraft(published: true, alreadyClaimed: true, user: $this->user)`. `failed()` is unchanged (clears `processing` only; the draft state is not affected). |
| `database/migrations/036_add_draft_tracking_columns_to_xlsforms_table.php` | Adds two nullable timestamps to `xlsforms`: `draft_snapshot_at` (what the draft includes) and `draft_claimed_at` (claim expiry clock). Style of `035`; `up()` only, no `down()` (package convention). |
| `FilamentOdkLinkServiceProvider.php` | Add `'036_add_draft_tracking_columns_to_xlsforms_table'` to `getMigrations()` (after the `035` entry). |

New job properties are declared as plain properties with defaults, set in the constructor body, not as promoted constructor properties. Jobs already in the queue at deploy time were serialised without them; a promoted property would be uninitialised on unserialize and fatal. Defaults keep old queued jobs working (old deploy job: releases the claim at its end, no snapshot; old file job: clears `processing` as before).

Verified, no change needed: `createDraftForm` does not touch the flag; `updateDraftDetails` (which does set it false) is only reached from the `enketo_draft_url` accessor, not from the deploy path. That accessor can clear the flag when it refreshes a missing Enketo id (admin template page only). Pre-existing, left alone.

### 3.4 Main repo changes

- **Migration copy**: `database/migrations/2026_11_19_000001_036_add_draft_tracking_columns_to_xlsforms_table.php`, identical to the package file (same precedent as `2026_10_28_000001_035_...`). The main app runs this copy; tests need it. Staging and live need `php artisan migrate`.
- **`app/Models/Team.php`**: remove `deployDraftForms()` (its only caller was `InitialPilot::mount`). Add `claimDraftForms(): Collection`: fresh forms of the team (`withoutGlobalScopes`), filtered by `draftIsOutdated()`, claim each. Returns the claimed forms.
- **New job `app/Jobs/DeployTeamDraftForms.php`**: constructor `(Team $team, array $formIds, User $user)`; `tries = 1`, `timeout = 50` (Horizon supervisor timeout is 60), `failOnTimeout = true`; uses `NotifiesOnJobFailure`. `handle()`: first `try { $team->localiseXlsforms(); }`; if it throws nothing has been handed to a chain yet, so release every claim (`releaseClaim(true)`), notify the user and stop. Then per form in its own `try`: `localiseModules()`, `$pending = $form->deployDraft(alreadyClaimed: true, user: $this->user); unset($pending);` (unset forces the dispatch inside the `try`). On a per-form exception: `releaseClaim(true)` for that form only, log, remember; the others still go. At the end, one danger notification listing forms that failed to start. Log the duration of the localise step and of each form (`Log::info`), so the 50 second limit can be checked on staging. `failed()` (only reached on a timeout, a killed worker or max attempts, where progress is unknown) does **not** release any claim; it only notifies "The update could not be finished. If a form still shows Updating, you can start it again within 15 minutes." Nothing in the job ever releases a claim that may belong to a running chain, so a duplicate chain can only come from a claim that really expired.
- **`app/Filament/App/Pages/PlaceAdaptations/InitialPilot.php`**:
  - `mount()`: no deployment. Remove the Echo listener, the `On` and `XlsformDraftWasDeployed` imports.
  - `xlsforms` becomes a `#[Computed]` property (fresh each render); `#[Locked] public array $activeFormIds` tracks forms seen updating.
  - Header action `update-test-forms`, label `t('Update test forms')`: visible if `can('maintain initial pilot')`; disabled when no form is outdated or any form has an active claim (tooltip says why); `requiresConfirmation()` with text that the test forms are replaced and test submissions are reset; body re-checks permission (`abort(403)`), refuses with a warning notification if any form has an active claim, calls `claimDraftForms()`, returns an info notification if nothing was claimed, dispatches `DeployTeamDraftForms` inside `try` (on failure: `releaseClaim(true)` for each claimed form and a danger notification), stores the claimed ids in `activeFormIds`, sends a "started" notification.
  - The action body lives in a protected method called from the closure (a public Livewire method can be called from the browser); `refreshForms()` is public because `wire:poll` calls it, and is read-only apart from toasts.
  - `refreshForms()` (public, polled): recompute active claims; for ids that were active and are now neither active nor outdated, send a "Test form updated: {title}" notification; update `activeFormIds`. Failures are already notified by the package jobs.
- **`resources/views/filament/app/pages/place-adaptations/initial-pilot.blade.php`**:
  - Wrap the forms grid in a `div` with `wire:poll.5s="refreshForms"` only while any form has an active claim (the whole page re-renders on each poll, including the submissions table, only during an update).
  - Per card by state: `current`: QR code, title, "Includes all changes made up to {time}" (no line if `draft_snapshot_at` is null, i.e. deployed before this feature); `updating`: spinner, the existing "being updated" text, the start time (`draft_claimed_at`) and "If this does not finish within 15 minutes, you will be able to start it again."; `expired`: no QR code, red "EXPIRED" block, "Changes made after {time} are not included" (or "This test form is out of date" with no snapshot), and for users with `maintain initial pilot` "Click Update test forms"; for others "Ask a team member who can update the test forms"; `missing`: "Test form not deployed yet", same hint. Time format `Y-m-d H:i:s T` in the app timezone.
  - Banner above the grid with the same message when any form is `expired` or `missing`.
  - Empty state when the team has no forms: "No test forms yet".
  - Change the intro sentence "These versions always have the latest adaptations" (now untrue) to say they contain the adaptations up to the time shown on each card.
  - Use `$this->xlsforms` instead of the old `$xlsforms` variable.
- **`app/Jobs/NotifyUserThatLanguageImportIsComplete.php`**: when the creator is a `Team`, add the text "To deploy the new translations to the test forms, open Initial Pilot and click Update test forms." and an action button linking to `InitialPilot::getUrl(panel: 'app', tenant: $team)` (no tenant exists in a queue; same as `QueueFarmEntityImport`). Added unconditionally; page authorisation still applies.
- New `t()` strings are extracted by the existing translation sync (`lang/gettext`); until synced they show in English.

## 4. Review findings that shaped this plan

1. `auth()->user()` is null in a queued job: `deployDraft` and `generateXlsfile` take an explicit `$user`.
2. 60 second worker timeout: job `timeout = 50` with `failOnTimeout`; the work was already done inside a web request before, so it should fit; the job logs its timings. If a realistic team needs more than about 35 seconds, add a dedicated queue and supervisor with a longer timeout in `config/horizon.php` before release (see section 7, step 4).
3. Killed workers leave claims: 15 minute expiry on a dedicated `draft_claimed_at` clock (not `updated_at`, which every edit bumps); the page treats a stale claim as outdated so the button works again.
4. Forms with no draft at all: treated as outdated and included.
5. Localise rewrites shared rows: the action refuses to start while any form of the team has an active claim.
6. Claims are taken in the action, so the page shows "Updating" at once and double clicks cannot double dispatch.
7. No "done" notification from jobs (the queued chain finishes long after); the page's poll shows it.
8. Dead and crashing Echo listener removed.
9. Publish chain: claim is taken at the start of `publishForm()` and carried through publish and the re-deploy.
10. Template deploys unaffected.
11. View-only users keep seeing status and get a hint instead of the button.
12. Notification URL built with panel and tenant.
13. Old queued jobs: defaults on new job properties.
14. The migration must exist in both the package and `database/migrations` (and in the provider list), or the tests and the app have no column.
15. Lost flag during updates fixed (3.2).
16. `XlsformDraftWasDeployed` stops firing from the deploy job (the flag no longer flips there). Its only listener was the removed page handler; the event class and the `saved` hook stay.
17. `Notification`s for failures keep going to the clicking user.
18. `updated_at` is bumped by every `xlsforms()->update([...])` in the app (location, farm entity, survey row, choice entry, choice-list sibling service), so it cannot be the expiry clock: with a team that keeps editing, a leaked claim would never expire. Hence `draft_claimed_at`.
19. The first version of this plan let `DeployTeamDraftForms::failed()` guess which forms were already handed to a chain and release the rest. A form edited after its hand-over looks identical to one not yet handed over, so a live claim could be released early. Removed: the job releases only what it knows (per-form exceptions, localise failure); unknown cases wait for the 15 minute expiry.
20. `Illuminate\Foundation\Queue\Queueable` includes `SerializesModels`, so the chain jobs reload the form from the database when they start. This is what makes "the deploy job no longer overwrites the flag" true (verified in `vendor`).
21. Draft QR codes also appear in package admin views (`draft-qr-code`, `draft-testing-qr-code`); those are admin-only and are left unchanged. The team-facing `pilot-index` and `set-up-survey` pages show the live app-user QR code, not the draft one.
22. `app:update-xlsform-drafts` is not scheduled anywhere (checked), so the Initial Pilot page really was the only trigger; the command goes through `deployDraft()` and therefore through the claim too.

## 5. Worst cases and how a user recovers without an administrator

Today a leaked `processing = 1` is permanent: `deployDraft()` returns null forever until someone edits the database. With this plan every case below ends with the button working again, by itself.

| Situation | What the user sees | Recovery |
|---|---|---|
| Worker killed, job lost, Redis flushed or Horizon down during an update | Card "Updating" with start time | After 15 minutes the card becomes EXPIRED and the button is enabled; click again. This is the longest wait there is. |
| A chain step fails (ODK rejects the form, network, file missing) | Failure notification; EXPIRED at once | Claim released and flag restored immediately; click again after fixing the cause. |
| One form throws while the app job starts it | Notification naming the form; other forms continue | That form's claim is released at once; click again. |
| `localiseXlsforms()` throws | Notification; EXPIRED | All claims released at once; click again. |
| App job times out or is killed mid-loop | Notification "could not be finished"; forms already handed over finish normally; the rest show Updating | The rest expire after 15 minutes (nothing is released by guessing); click again. |
| Dispatch fails when clicking (queue down) | Error notification | Claims released at once. |
| Two clicks or two tabs | Second one refused with "A form is still updating" | None needed. |
| User edits while an update runs | After it finishes, the form shows EXPIRED (flag stayed true) | Click again. |
| A real update takes longer than 15 minutes (long queue backlog) and the user clicks again | A second chain for the same form | Harmless: the same file is deployed twice and test submissions are reset again; the claim of the first chain ends it, the second releases normally. |
| Rows stuck with `processing = 1` from before this release | EXPIRED at once (no `draft_claimed_at`) | Click; no database fix. |
| User has `view` but not `maintain initial pilot` | Status and a hint | A team member with the permission clicks (same people as today for `pull-submissions`). |

Remaining way to be stuck for longer than 15 minutes: the update itself keeps failing deterministically (for example the job always exceeds 50 seconds). That is why the timings are logged and measured before release (section 7, step 4), rather than assumed.

## 6. Tests

Main repo (the package suite has known factory failures; package behaviour is tested here as existing job tests are).

`tests/Feature/PlaceAdaptations/InitialPilotTest.php` (Pest, Livewire, `Bus::fake()` / `Queue::fake()`; `QUEUE_CONNECTION=sync` would otherwise call ODK):
1. Mounting queues nothing and does not touch `processing` or the flag, with flagged forms.
2. The action claims outdated forms, dispatches exactly one `DeployTeamDraftForms` with their ids; a second click while claims are active dispatches nothing and warns.
3. A form with `has_draft` false and flag false is included.
4. Refuses while any form has an active claim; a stale claim does not block.
5. No `maintain initial pilot`: action absent/403, hint shown.
6. Dispatch failure releases the claims and restores the flag.
7. Cards: QR code only for `current`; EXPIRED for `expired`, `missing`, stale claim and `updating` shows spinner; snapshot line format; no line when `draft_snapshot_at` is null; polling attribute only with an active claim; another team's event does not break the page; empty state.
8. `refreshForms()` sends the "updated" notification for a form that finished current, and none for one that finished outdated.

`tests/Feature/Jobs/` (extend `ProcessingFlagResetTest`, `DeployJobGuardsTest`; new `DraftClaimTest`):
9. `claimForDeployment()`: true then false; a claim older than 15 minutes can be re-taken; `processing = 1` with no `draft_claimed_at` can be re-taken; an edit (`xlsforms()->update(['draft_needs_update' => true])`, which bumps `updated_at`) does not extend a claim; `releaseClaim` clears `draft_claimed_at`; `hasActiveClaim` / `draftIsOutdated` / state table.
10. `deployDraft()` returns null when claimed elsewhere; with `Bus::fake()` the export, file and deploy jobs are chained with `holdClaim` true on the file job, snapshot set, user passed; flag cleared; `generateXlsfile` throwing releases the claim and restores the flag.
11. Deploy success: `processing` false, `draft_snapshot_at` written, flag untouched (an edit saved during the job leaves it true). Deploy with `holdClaim`: `processing` stays true. Deploy for a template: `processing` untouched on success.
12. Failure in export, file or deploy: `processing` false and flag true (existing failure tests stay green).
13. Publish chain: `publishForm()` holds through deploy and publish; the re-deploy releases; `publishForm()` returns null while processing (existing test).
14. `DeployTeamDraftForms`: releases and restores a form whose `localiseModules()` throws and still processes the others; when `localiseXlsforms()` throws every claim is released; `failed()` releases nothing and only notifies.
15. The localise step does not leave a form flagged after a normal update.
16. Import-complete notification carries the link built with panel and tenant and the new text; no link when the creator is not a team.

## 7. Implementation order (so it is done in one pass)

1. Package (follow `packages/filament-odk-link/CLAUDE.md`: migration with `up()` only, registered in `getMigrations()`; no inline fully qualified class names; descriptive variable names, no `$e`): migration, provider list, main-repo migration copy, `Xlsform` methods and casts, job changes, `publishForm`, `PublishXlsformOnOdkCentral`. Add a short plan file in the package's `docs/plans` pointing to this plan, and a package change log, as that repository's conventions ask.
2. Main: `Team`, `DeployTeamDraftForms`, `InitialPilot`, blade, import notification.
3. Tests as in section 5.
4. Run `./vendor/bin/pest` (whole suite), the package suite, `./vendor/bin/phpstan analyse`, `./vendor/bin/pint` on changed files. Fix everything before reporting. Then time the localise step and a full `DeployTeamDraftForms` run for the largest local team (read the job's log lines; use a copy of the data or a team whose forms may be rebuilt). If the whole job needs more than about 35 seconds, add a dedicated queue and a supervisor with a longer `timeout` to `config/horizon.php`, point the job at it (`$queue`), and set the job `timeout` below the supervisor's.
5. Plan status to Completed, change log in `docs/change-logs`, suggested commit messages (Dan commits manually).

Out of scope: auto-deploy after import; a stored "failed" state per form; per-user timezones; the `enketo_draft_url` accessor clearing the flag; changing which edits set `draft_needs_update`; the draft QR code in package admin views; the admin `status` label still shows PROCESSING for a leaked row.

## 8. Rollout

Submodule PR first (base `staging-hotfix-20261026`), then the main PR with the submodule bump. Deploy order: run `php artisan migrate` before the new code serves requests, then restart Horizon; ideally let queued deploy jobs drain first (old queued jobs still work, see 3.3). On staging verify: one form update with the timestamp shown, a double click, a second tab, an edit during an update (form turns "Needs update" afterwards), a translation import followed by the notification link, a publish, and a failed deploy (flag back to true, EXPIRED shown).
