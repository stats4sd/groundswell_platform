# Plan: Database notifications + `failed()` on every queueable job

**Status:** Not Started

## Goal

Every queued job, queued import and queued export that can fail must have a `failed(?Throwable)` hook that sends a Filament notification which is (a) broadcast over Reverb for users currently on a panel page, and (b) stored via `sendToDatabase()` so users can review failures at leisure from the panel's notification bell. Enable the bell (`->databaseNotifications()`) on the panels so those rows are actually visible — today two importers already write database notifications that **no panel renders**.

This plan deliberately does **not** build any aggregation UI. Per-user notification history is the durable channel; system-wide debugging is Horizon/Telescope/Sentry (see [fix-horizon-auth-gate.md](fix-horizon-auth-gate.md)). Deeper xlsform/submission error UX is deferred to the September ODK Link rewrite.

## Current state (audited 2026-08-02)

- `->databaseNotifications()` is called by **no panel provider**. `->sendToDatabase()` rows from `LocationImport.php:389` and `FarmEntityImport.php:238,249` are written and never displayed.
- `->catch()` is used on **no job chain** in the codebase. Six chains exist; a failure in any early link silently kills every downstream job, including the `NotifyUserThat…` jobs that are the only user-facing signal in two of the chains.
- Nine odk-link package jobs have no `failed()`, no `$tries`, no notification and no user context at all.
- Broadcasting config: `config/broadcasting.php:18` defaults to the `null` driver unless `BROADCAST_CONNECTION` is set — with it unset, every `->broadcast()`-only notification in the codebase is silently dropped. Verify the env var in each deployed environment as part of this work.
- `packages/filament-odk-link` is a **git submodule**. Changes to package files need a commit in the submodule repo plus a pointer bump in the app repo.

## Design

### D1. Panels

Add `->databaseNotifications()->databaseNotificationsPolling('30s')` to:

- `app/Providers/Filament/AppPanelProvider.php` — team users reviewing import/translation/deployment failures. Verify the bell renders sensibly with `->topNavigation()` at `:198`.
- `app/Providers/Filament/AdminPanelProvider.php` — Super Admins are the recipients for all system-triggered job failures (submission pulls, template imports).

Skip `ProgramPanelProvider` — no job notifies a program-scoped audience, and the panel has no resources.

### D2. One shared failure-notification helper

A single trait in the odk-link package (app code can use it too, since the app depends on the package): `Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure` with one method:

```
notifyJobFailure(string $title, ?Throwable $exception, User|Collection|array $recipients, ?string $actionUrl = null): void
```

Behaviour: builds a Filament `Notification` — `->danger()->persistent()`, title as given, body `Str::limit($exception?->getMessage() ?? 'Unknown error', 200)` (never the full message: a `QueryException` body is a whole SQL statement, and `PublishXlsformOnOdkCentral.php:54` shows why the null-safe call matters — it currently calls `$exception->getmessage()` unguarded on a `?Throwable`) — then `->sendToDatabase($recipient, isEventDispatched: true)->broadcast($recipient)` per recipient. `isEventDispatched: true` makes the bell update live over the websocket instead of waiting for the next poll (the pattern `LocationImport.php:389` already uses). Optional `->actions([Action::make('view')->url($actionUrl)])`.

Recipients must be Eloquent `User` models (database notifications are `Notifiable`-routed). A `superAdmins()` resolver helper (`Role::findByName('Super Admin')->users`, the recipient set `FinishXlsformTemplateImport.php:41` already uses for success) covers every job with no user context.

### D3. Recipient policy

- Job has a user in context → notify that user.
- Job is part of the template-import machinery (admin-uploaded templates) → Super Admins, matching the existing success notifications.
- Job is cron/system-triggered (submission pulls, submission processing) → Super Admins.
- `UpdateXlsformFile` has no user in its constructor: thread the same nullable `$user` that `DeployDraftXlsformToOdkCentral` already takes through `Xlsform::generateXlsfile()` / `deployDraft()`, falling back to Super Admins when null.

### D4. Interaction with the processing-flag work

[processing-flag-reset-and-dead-column-removal.md](processing-flag-reset-and-dead-column-removal.md) adds `processing`-flag resets to the **same `failed()` methods** this plan creates on the template-import and deployment chain jobs. Implement the two plans together for those jobs: one `failed()` per job that both resets the flag and calls `notifyJobFailure()`. Neither plan should add a second `failed()`.

## The inventory — what each class needs

### app/Jobs

| Class | Has `failed()`? | Change |
| --- | --- | --- |
| `QueueFarmEntityImport` (`app/Jobs/QueueFarmEntityImport.php:22`) | Yes (`:44`, writes `Import.errors` only) | Add notification to the existing `failed()`. **Handled in [surface-import-errors-in-ui.md](surface-import-errors-in-ui.md) Phase 3.2** — do not double-implement; recipient `User::find($this->data['user_id'])`. |
| `NotifyUserThatLanguageImportIsComplete` (`:15`) | No | Add `failed()` → notify `$this->user`. Note this job also mutates `locales.processing_count` and `xlsforms.draft_needs_update` (`:41` fatals if the team has no matching xlsform — guard the null while in here). |
| `NotifyUserThatLanguageImportIsFailed` (`:15`) | No | Add `failed()` → notify `$this->user`. Also change its **success-path** notification (`:49-51`) from `->broadcast()->send()` to include `->sendToDatabase()` — it is itself a failure report and must be durable. |

### packages/filament-odk-link — deployment chain

| Class | Has `failed()`? | Change |
| --- | --- | --- |
| `DeployDraftXlsformToOdkCentral` (`src/Jobs/XlsformDeployment/DeployDraftXlsformToOdkCentral.php:19`) | Yes (`:99`), broadcast-only, silent when `$user` is null (`:110-112`) | Add `->sendToDatabase()`; when `$user` is null, notify Super Admins instead of returning early. |
| `PublishXlsformOnOdkCentral` (`:21`) | Yes (`:47`), broadcast-only | Add `->sendToDatabase()`; fix the null-unsafe `$exception->getmessage()` at `:54` (the helper does both). Consider `$tries`/`$backoff` matching `DeployDraftXlsformToOdkCentral` since it makes the same class of ODK Central calls. |
| `UpdateXlsformFile` (`:13`) | No | Currently **swallows** `FileDoesNotExist`/`FileIsTooBig` into `Log::error` (`:28-31`) and lets the chain continue with no file attached. Change the catch to `$this->fail($e)` (the `finally` still clears `processing`), and add `failed()` → notify (threaded user, fallback Super Admins). |
| `XlsformWorkbookExport` (`src/Exports/XlsformExport/XlsformWorkbookExport.php:10`) | No | Queued Maatwebsite export at the **head** of the deployment chain — its failure is the stuck-`processing` hole. Add a `failed(Throwable)` method on the export class (Maatwebsite calls it for queued exports) → reset flag (Task 4 plan) + notify. The per-sheet exports (`XlsformSurveyExport`, `XlsformChoicesExport`, `XlsformSettingsExport`) are sheets of this workbook; the parent's hook covers them. |
| `NotifyUserThatXlsformFileIsDeployedAsDraft` (`:12`), `NotifyUserThatXlsformFileIsUpdated` (`:13`) | No | Leave without `failed()` — a failed notification-about-a-notification goes to the log/Sentry; adding meta-notifications is noise. |

### packages/filament-odk-link — template import chain

All dispatched from `HandleXlsformTemplateAdded.php:78-87`; none has any error handling. Recipients: Super Admins. Each `failed()` also resets `processing` per the Task 4 plan.

| Class | Change |
| --- | --- |
| `XlsformTemplateWorkbookImport` (queued Maatwebsite import, chain head) | Add an `ImportFailed` handler via `registerEvents()` (it already uses `WithEvents`/`RegistersEventListeners` — add a `failed()`/`importFailed` hook) → reset flag + notify with the template name in the title. |
| `PrepareSurveyRowPaths`, `FinishSurveyRowImport`, `FinishChoiceListEntryImport`, `LinkModuleVersionToLocales`, `ImportAllLanguageStrings`, `FinishXlsformTemplateImport` | Add `failed()` to each → reset flag + notify. `$this->model` (XlsformTemplate/XlsformModuleVersion) gives the name for the message. |
| `XlsformTemplateChoiceListImport` (fire-and-forget dispatch at `HandleXlsformTemplateAdded.php:76`) | Add the same failure hook → notify (no flag involvement). |

`ImportAllLanguageStrings` runs `FinishLanguageStringImport`/`AddMissingChoiceListStrings` via `dispatchSync` (`:60,64`), so their exceptions surface as `ImportAllLanguageStrings` failures — a `failed()` on the parent covers them; still add cheap `failed()` methods to both in case they are ever dispatched async.

### packages/filament-odk-link — submissions

| Class | Change |
| --- | --- |
| `PullSubmissionsFromXlsform` (`src/Jobs/PullSubmissionsFromXlsform.php:13`) | Add `failed()` → notify Super Admins with `$this->xlsform->title` and owner team in the body. Note for the message: `OdkSubmissionService.php:177/180` throws with user-facing remediation text for unknown form versions — that text finally reaches someone. |
| `PullSubmissionsFromXlsformQuietly` (`:19`) | Same. Replace the `abort(500, …)` at `:73` with a thrown exception — `abort()` in a queued job is an `HttpException` with a misleading shape. |
| `ProcessOdkSubmission` (`src/Jobs/OdkSubmissions/ProcessOdkSubmission.php:11`) | Add `failed()` → notify Super Admins, including submission id and form title. |

### Listeners

| Class | Change |
| --- | --- |
| `RegisterNewUserToOdkCentral` (`app/Listeners/RegisterNewUserToOdkCentral.php:16`) | Imports `ShouldQueue` (`:9`) but never implements it — it runs synchronously and its catch-all (`:33-37`) swallows ODK failures silently. Make it actually implement `ShouldQueue`, remove the swallow (let it fail), and add `failed()` → notify Super Admins that the user's ODK Central registration failed. |
| `HandleXlsformTemplateAdded` (package, `:23`) | Not queued; dispatches the chains. No `failed()` needed. Delete or wire up the never-set `public ?User $importedBy` property (`:25`) while in here — currently dead. |

### Already correct (no change)

`LocationImport` and `FarmEntityImport` failure paths already `sendToDatabase + broadcast`; their remaining gaps (deep links, success durability) are in the import-surface plan.

## Implementation order

1. `NotifiesOnJobFailure` trait + `superAdmins()` resolver in the package (submodule commit).
2. Panel providers: `->databaseNotifications()` on App + Admin.
3. Package jobs, grouped by chain (deployment, template import, submissions) — coordinate `failed()` bodies with the Task 4 flag resets.
4. App jobs + listener.
5. Submodule pointer bump in the app repo.

## Tests

- Trait unit test: `notifyJobFailure()` with a null exception, a single user, a collection of users — assert `notifications` table rows and payload shape (title, truncated body, action URL).
- Representative per-chain tests (in `tests/Feature/Jobs/JobFailureNotificationTest.php`): construct each key job (`DeployDraftXlsformToOdkCentral`, `PullSubmissionsFromXlsform`, `PrepareSurveyRowPaths`, `UpdateXlsformFile`), call `failed(new Exception('boom'))` directly, assert a database notification row exists for the expected recipient. Assert against the `notifications` table, not `Notification::assertNotified()` (session-only).
- `UpdateXlsformFile`: assert a missing file now fails the job rather than letting the chain continue.
- Panel smoke: existing panel tests still green with `databaseNotifications()` enabled.

## Verification

1. `./vendor/bin/pest`, `./vendor/bin/phpstan analyse`, `./vendor/bin/pint` (app and submodule).
2. `grep -rn "ShouldQueue" app/Jobs packages/filament-odk-link/src/Jobs` cross-checked against `grep -rln "function failed"` — every job class accounted for (either has `failed()` or is on the explicit leave-alone list above).
3. Manual: with Reverb running and a user on a panel page, kill a deployment mid-flight (bad ODK credentials) — toast appears live; log out/in — the bell shows the stored notification.
4. Deployment checklist: confirm `BROADCAST_CONNECTION=reverb` in every deployed `.env`; without it broadcasts are silently dropped (`config/broadcasting.php:18`).

## Out of scope

- Success notifications beyond those that already exist (failure durability is the requirement; success stays broadcast-only where it is today, except `NotifyUserThatLanguageImportIsFailed`'s body which is itself a failure report).
- Chain `->catch()` callbacks — per-job `failed()` covers notification and flag-reset needs; chain-level orchestration is September-rewrite territory.
- Horizon's failed-job notification routing (`HorizonServiceProvider.php:18-20`, commented out) — could be enabled later as an ops backstop; not user-facing.
