# MVP Release Testing Plan — Team Localisation Workflow & Shiny Apps

**Status:** Not Started
**Date:** 2026-07-22
**Context:** The platform must be published next week so teams can start survey preparation (localisation, piloting). Live data collection starts ~October. Deep architectural work on filament-odk-link (see the package-vs-app appraisal of 2026-07-06) is deferred; this plan covers getting a minimum viable, hard-to-break release out.

---

## Part 0 — Pre-flight fixes (do these BEFORE manual testing)

These are confirmed-still-present bugs from the 2026-07-06 package review that either endanger production data or will corrupt the testing itself. All verified against the in-tree package copy (`packages/filament-odk-link`, HEAD 62a9c3a, 2026-07-20). "Review ref" IDs refer to the findings in [2026-07-06-package-review.md](/Users/dave/Projects/PhpPackages/filament-odk-link/docs/code-reviews/2026-07-06-package-review.md) (Section 3 — Actual bugs), so completion can be tracked in the core package plan as well as here.

| Priority | Review ref | Item | Why it blocks release | Where |
|---|---|---|---|---|
| 1 | **B1** (🔴 Critical) | **Remove or gate `odk:trs` (`TestRemoveSub`)** — the "Specific" branch falls through to deleting ALL submissions/entities + `Cache::flush()`, and the command auto-registers in production | One mistyped artisan command on prod destroys every team's data | `packages/filament-odk-link/src/Commands/TestRemoveSub.php:53-58`, registered at `FilamentOdkLinkServiceProvider.php:95-103` |
| 2 | **B3** (🔴 High) | **Fix numeric-zero drop in repeat groups** (`$value != null` loose comparison) | Silently discards legitimate `0` answers in repeats — pilot testers will see "missing" data and you'll chase ghosts; HDDS-style score data is exactly where zeros matter | `packages/filament-odk-link/src/Services/OdkLinkServices/OdkSubmissionService.php:464` (root check at `:366` is already strict) |
| 3 | **B14** (⚠️ Medium) | **`publishForm` proceeds on any non-404 error** (401/500 from ODK Central still "publishes") | Platform will claim a form is live when it isn't — direct hit on the pilot→publish flow teams will exercise | `packages/filament-odk-link/src/Services/OdkLinkServices/OdkFormService.php:192-201` |
| 4 | **B4** (🟠 High) | **`ChoiceListEntry` owner scope leak** — top-level `orWhereNull` unwrapped | One team's customised choice-list entries can leak into another team's view — Area 1 step 6 tests exactly this surface | `packages/filament-odk-link/src/Models/OdkLink/ChoiceListEntry.php:45-46` |
| 5 | **B6** (🟠 High) | **`UpdateXlsformDrafts` TEMP hack** — `Xlsform::find(1)->update(...)` runs every invocation, fatals if id 1 missing | Touches an arbitrary team's form on every run; fatal on fresh DBs | `packages/filament-odk-link/src/Commands/UpdateXlsformDrafts.php:29-30` |
| 6 | — (not in package review; from Shiny integration mapping) | **Set `SHINY_AUTH_KEY`** (defaults to `change-me`, absent from `.env.example`) and resolve `config('services.shiny.rdmt-demo-url')` being null (leftover from another project) — the Shiny handshake may not even form its callback URL correctly | Area 2 is untestable / insecure without this | `packages/laravel-shiny-loader/src/Http/Controllers/ShinyController.php:36,40`, `config/shiny-loader.php` |
| 7 | **B2** (🔴 Critical, partially fixed app-side) | Verify B2 mitigation holds: team-panel access to XlsformTemplates destructive actions is blocked only by the app-side policy (`app/Policies/XlsformTemplatePolicy.php`); the package's inline `available` checkbox and "Replace XLSForm" action are NOT auto-authorized by the policy. The package-side fix (safe team table, package-level authz) remains open in the core plan | If the resource is reachable at all, non-standard actions remain live | Test explicitly in Part 1, step 10 |

Deferred-but-acceptable for MVP (log as known issues, still open in the package review): **B7** `EntityExport` wrong column name (`dataset_variable_id`), **B8** `hasCompletedLookupList()` SQL error, **B5** `ChoiceListEntriesRelationManager` Filament API mismatch (admin-side), and the **token-never-invalidated** finding (review "Lower / latent" section — 20h cache, no 401-retry, `OdkLinkService.php:38`).

---

## Part 1 — Area 1: Team localisation workflow checklist

### How to run it

- **Run the full script twice**: once now to find breakage, once clean after fixes, on staging against a real (staging) ODK Central.
- **Create at least TWO teams** and run key steps in both — several bugs (choice-list scope, owner-scoped module versions) only manifest cross-tenant.
- **Never test as Super Admin** — `Gate::before` in `app/Providers/AppServiceProvider.php:57-60` bypasses every permission check and will mask authorization bugs. Use a real Team Admin account. There is no read-only team role in the seeder; if view-only users are expected at launch, that's a gap to note.
- Tick every box; where a step says "verify in ODK Central", actually open Central and look.

### Step 0 — Environment preconditions

- [ ] Queue worker / Horizon running (nearly every operation below is queued; feedback is broadcast-only)
- [ ] Reverb/websockets running and Echo connecting in the browser console (otherwise import/deploy status silently never updates)
- [ ] ODK Central reachable with the configured credentials; `ODK_URL` set
- [ ] `odk:poll-for-odk-data` is scheduled (cron/Horizon) — **no schedule entry exists in-repo**; without it, submissions only arrive via manual buttons
- [ ] Admin-side prep done: templates uploaded & available, `locations` + `farm info` modules `can_be_replaced`, correct ChoiceLists `is_localisable`, optional XlsformModuleVersions present, teams created
- [ ] `SUBMISSION_PROCESS_CLASS/METHOD` env vars point at `SubmissionController::process`

### Step 1 — Team creation & onboarding surface

- [ ] Create a fresh team → verify (a) ODK project + app user created in Central (this happens **synchronously** at team creation — `HasXlsformTemplates.php:26-34`), (b) English locale auto-added, (c) one Xlsform per available template created, (d) "Local Context" module version created
- [ ] Team Admin logs in → Survey Dashboard renders with all cards `not_started`
- [ ] QR code renders on the Pilot page (null `odk_qr_code` means project/app-user creation failed — there is **no self-heal UI**; if this happens the team is bricked, note it)

### Step 2 — Country, languages & translations

- [ ] Select country + language(s) on Survey Languages page; auto-save works; each language picks up its default locale
- [ ] Add a brand-new locale for a language
- [ ] Download the empty translation template (per template × locale); confirm it opens and its structure is fillable
- [ ] Fill a subset of translations, upload → success notification, `processing_count` badge, table refreshes when the queued import finishes (Echo event `LanguageImportIsComplete`)
- [ ] Upload a deliberately malformed file → validation error shown, file deleted, no partial import
- [ ] Download the "existing translations" export after import → round-trip is lossless
- [ ] Mark languages complete → dashboard card flips to complete; unmark works

### Step 3 — Locations & farm list

- [ ] Create location levels, with the lowest level flagged `has_farms` (required before farm import)
- [ ] Run the 3-step Import Locations & Farm Entities wizard: header parsing (step 1), location-level column mapping (step 2), farm code + identifiers/properties mapping with GPS auto-detection (step 3)
- [ ] Locations appear immediately (synchronous import); farm entities appear after the queued job, and **exist as entities in ODK Central** (`bulkCreateFarms`) — verify in Central, not just in the platform ("import complete" notification fires even if the Central push partially failed)
- [ ] Import a file with a bad row → `ImportFailed` writes errors to the Import record and the user gets a danger notification
- [ ] `team.has_updated_locations` is now true (this gates the module rebuild in step 7 — if it's not set, deployed forms keep stale locations with no error)

### Step 4 — HDDS question hints

- [ ] First visit to HDDS Hints page: verify the global module version is cloned for the team AND every team xlsform pivot is re-pointed to the clone (`HddsHints.php:76-89` — multi-write, no transaction; check form↔module linkage is consistent afterwards)
- [ ] Edit a hint in each team locale; saved as LanguageStrings; `draft_needs_update` flagged on affected forms
- [ ] **Known limitation to confirm/accept**: rows with no pre-existing hint in any locale have the edit action hidden — teams cannot ADD a hint where none existed. Decide if acceptable for MVP or needs a fix.

### Step 5 — Localisable choice lists

- [ ] Every choice list marked `is_localisable` (and not `has_custom_handling`) appears as a navigation item; no others do
- [ ] Edit labels of an existing entry per locale; add a new custom entry (owner-scoped); "remove from context" dims the row
- [ ] **Cross-tenant check (targets the scope bug)**: create/edit entries as Team A, then log in as Team B — Team A's custom entries must NOT be visible
- [ ] Change team languages AFTER editing entries → re-open an entry; the per-locale repeater must not be broken/mismatched

### Step 6 — Optional modules

- [ ] For each eligible form (indicators, women's — the farm-reg form is excluded by title match), the expected optional modules are listed. **If the list is empty, the config keyword→form-title match failed silently** (`config/optional_modules.*` matches on title substrings) — verify each form title actually matches its configured keyword
- [ ] Add 1+ modules → attached in order, `draft_needs_update` set; remove works; reorder persists

### Step 7 — Build, deploy draft & pilot

- [ ] Open the Initial Pilot page — **merely opening it triggers `deployDraftForms()`**: rebuilds "Local locations"/"Local farm info" module versions from the team's data, swaps them into the forms (swap only happens while the form still points at the default version — name-coupled to `"Local " + module name`), generates the xlsx, and deploys drafts to Central
- [ ] Download/open the deployed draft in Central or Collect and verify ALL localisations landed: location cascade selects match the imported hierarchy (per locale), farm list, uploaded translations, edited HDDS hints, choice-list changes, optional modules present in order
- [ ] Scan the QR in ODK Collect; submit test submissions **in each language**, including at least one submission with `0` answers inside a repeat group (zero-drop regression) and one with consent declined
- [ ] Pull submissions via the manual "Get Submissions" button AND via the poll command; submissions appear flagged `test_data` (because `pilot_complete` is false), completion status/duration/consent parsed
- [ ] **Edit-after-localise loop**: change a location + a hint, revisit Pilot → new draft deploys with the changes (this exercises the `has_updated_locations` gate and re-localise path)

### Step 8 — Publish & go-live

- [ ] "Publish changes" on a form with pending draft changes → deploys draft then publishes; `XlsformWasPublished` event updates the UI; version visible in Central
- [ ] With ODK Central credentials broken (temporarily), publish → the platform must NOT report success (this is pre-flight fix 3; verify the fix)
- [ ] Mark pilot complete on Set Up Survey → subsequent submissions ingest as live (not `test_data`); `markPilotIncomplete` reverts to test mode
- [ ] Verify the "unpublished changes" red banner shows when appropriate — note it warns but does not block going live with stale forms (accept for MVP or add a hard gate)

### Step 9 — Failure-mode spot checks (~30 min, do once)

- [ ] Stop the queue worker, upload a translation and trigger a deploy → confirm what the user sees (expected: silent stall — decide if a "processing…" hint is needed for launch)
- [ ] Manually set an xlsform's `processing = true` in the DB → deploys silently no-op (`deployDraft()` short-circuits); confirm and document the unstick procedure (it will happen in production)
- [ ] Kill websockets only → actions complete but UI never updates until refresh; confirm a page refresh does show final state

### Step 10 — Permission & tenancy checks

- [ ] Team Admin cannot reach the XlsformTemplate resource at all (lacks `view xlsform templates`), and specifically cannot toggle `available`, Edit, "Replace XLSForm", or bulk-delete templates (B2 — the app policy is the only line of defence)
- [ ] Team A user cannot access Team B's tenant URLs directly (paste Team B resource URLs while logged in as Team A)
- [ ] All dashboard "mark as complete" buttons respect `maintain <section>` permissions

---

## Part 2 — Area 2: Shiny apps (Monitoring & Results) testing approach

### Finding: this is a build gap, not just a testing gap

The apps have never consumed real team identity. Concretely:

- **Analysis app** (`packages/groundswell_analysis/app.R:36`): `project_code <- "Senegal"` is **hardcoded**, resolving to ODK project 116 via a static 16-country `.env` list. The Laravel page posts `['foo' => 'bar']` — no tenancy at all (`data-analysis-index.blade.php:20`). A new team is not representable in this app, full stop.
- **Monitoring app**: Laravel posts only `odk_project_id` + `language` (`MonitorDataCollection.php:63-81` — the form-id keys are commented out), so form xml ids come from the Shiny `.env`, and `OVERRIDE_LARAVEL_AUTH=true` + `AUTH_odk_project_id=116` has been swapping in Senegal anyway. A team with no ODK project posts the literal string `'none'` as the project id.
- Both apps read ODK Central directly via ruODK/OData — the Laravel DB is not involved — and both assume full-schema data from all three forms; empty OData frames will produce NA-riddled joins or hard errors.

So "test the Shiny apps with team data" requires closing the tenancy plumbing first. Recommended sequence:

### Step 1 — Close the minimum tenancy gap (dev work, ~days)

1. **Monitoring**: uncomment/complete the form-id keys in `MonitorDataCollection::$shinyData` so `reg/indicators/womens` xml ids come from the team's actual xlsforms, and make the R app prefer posted values over `.env`. Handle `odk_project_id === 'none'` in R with a clean "no survey project yet" screen instead of a ruODK failure.
2. **Analysis**: replace `project_code <- "Senegal"` + the static country list with the posted project id (pass real `post_data` from `data-analysis-index.blade.php` the same way monitoring does). If that refactor is too deep for this week, apply the MVP scope cut below instead.
3. Turn `OVERRIDE_LARAVEL_AUTH` **off** in every environment except a dedicated regression instance.

### Step 2 — MVP scope decision (recommended)

Data collection starts ~October; until then teams only have pilot submissions. **Ship Monitoring against real team data; put Results/Analysis behind a graceful gate** ("Results will be available once data collection begins" / minimum-submissions threshold). Rationale: Monitoring is what teams need during preparation and piloting (are submissions arriving?); Analysis assumes complete-survey data (entity lists, indicator caches, `data_objects/` RDS) and hardening it for sparse data is exactly the work you've scheduled for the architecture window before October. Descoping it now removes the highest-risk surface from next week's launch without removing anything teams need yet.

If Results must ship: the same three-tier matrix below applies, but budget significantly more R-side hardening (empty `entity_list`, missing `data_directory` rows for unknown project codes, `readRDS` cache misses).

### Step 3 — Three-tier data test matrix

Run the (fixed) apps against a local/staging Shiny pair pointed at staging ODK Central, using the teams created in Part 1:

| Tier | Setup | Expected behaviour to verify |
|---|---|---|
| **Empty** | Fresh team, ODK project exists, forms published, 0 submissions | App loads, shows explicit "no data yet" (not error/blank); no ruODK crash on empty OData frames |
| **Sparse** | The Part 1 pilot team: 5–20 test submissions across the three forms, at least one form with 0 | Counts correct; per-form/per-location breakdowns handle missing forms; language toggle works with team locales; no NA-explosions in tables/charts |
| **Full (regression)** | Dedicated instance with `OVERRIDE_LARAVEL_AUTH=true` → project 116 (Senegal) | Everything that worked before still works — this is the only tier that's been tested to date; keep it as the baseline |

Also verify the handshake itself in staging (it has only ever run with overrides, so it may never have been exercised end-to-end): postMessage origin check passes, `.sessions/<session>` file is readable (Laravel and Shiny **must share a filesystem** — confirm the deployment co-locates them), `SHINY_AUTH_KEY` matches on both sides, and the callback URL is formed despite `services.shiny.rdmt-demo-url` being undefined in `config/services.php`.

### Step 4 — Tenancy isolation check

With two teams from Part 1: log in as Team A, open Monitoring → only Team A's project data. Then Team B. Because the apps query ODK Central with **platform-level credentials** and the only scoping is the posted project id, any bug here shows another team's raw data — test it explicitly, don't assume.

---

## Suggested order of work this week

1. Pre-flight fixes 1–6 (Part 0) — roughly a day, all small and independent of the architecture work.
2. First full pass of the Part 1 checklist on staging (two teams) — capture failures as issues in docs/issues.
3. Monitoring tenancy plumbing + `'none'` handling (Part 2 step 1.1); make the Results scope decision.
4. Fix what pass 1 found; second clean pass of Part 1; Part 2 test matrix.
5. Publish.
