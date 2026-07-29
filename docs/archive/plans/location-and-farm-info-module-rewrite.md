# Location & Farm Info Module Rewrite — Design

**Status: Completed** (design doc) — implemented via [location-and-farm-info-module-rewrite-implementation.md](location-and-farm-info-module-rewrite-implementation.md); see the [change log](../../change-logs/location-and-farm-info-module-rewrite.md). This file is the design/rationale record (dropped legacy behaviour, known limitations); the implementation plan and change log record what shipped. Two subsequent divergences from this design: module builders now create one local module version **per `XlsformModule` entry** rather than one per team (commit `29aa895`, 2026-07-19), and the global-`DatasetVariable` limitation was later addressed by `docs/change-logs/team-scoped-farm-entities-dataset.md`. Still open: `has_updated_locations` is never set to `true` in the codebase, so the builders don't yet run automatically.

## Context

`app/Services/LocationSectionBuilder.php` is legacy and is being fully rewritten. It currently builds one combined "locations + farm selection" XLSForm section per team, manually looping over every one of a team's Xlsforms and forcing a hardcoded pivot `order`.

Two things have changed since it was written:

1. Farm registration now happens in its own dedicated ODK form ("Farm Registration"). That form only needs the **locations** cascading-select section — it must not also show farm selection. Every other form needs **both** locations and farm selection.
2. Farms are no longer the legacy `App\Models\SampleFrame\Farm` model. They are `FarmEntity` — a thin structural link (`owner_id`, `location_id`, `team_code`, `odk_uuid`, `odk_version`) whose actual data lives on ODK Central as entities in an `OdkDataset`. Local `Entity`/`EntityValue` mirroring was deliberately removed (see `docs/archive/plans/farm-entities-simplify-and-gps-sync.md`); every read of a farm's actual identifiers/properties goes live to Central via `OdkFarmEntityService`.

Two example CSVs define the target shape:
- `docs/example-location-and-farminfo-module-survey-sheet.csv` — the `survey` sheet for both modules.
- `docs/example-location-module-choice-list-sheet.csv` — the `choices` sheet for the locations module.

## Goal

Replace `LocationSectionBuilder` with two focused builder classes that populate two separate, team-owned XLSForm module versions:

- **Locations** — cascading `select_one` questions, one per `LocationLevel`, for `n` levels.
- **Farm Info** — a `select_one_from_file` farm picker (against the `Farm_Summary` ODK Central entity list) plus a `farmer_note` summarizing the selected farm's identifiers/properties.

## Architecture

Two new classes replace the single legacy one:

- `app/Services/XlsformModules/LocationsModuleBuilder.php`
- `app/Services/XlsformModules/FarmInfoModuleBuilder.php`

Each exposes a single static entry point, e.g. `LocationsModuleBuilder::populate(Team $team): void`.

### Precondition (out of scope for this work — admin panel task)

An admin must already have created, per relevant `XlsformTemplate`, an `XlsformModule` row named **exactly** `Locations`, and (except on templates that are farm-registration-only) another named **exactly** `Farm Info` — both with `can_be_replaced = true`. These exact names are the contract between admin-panel setup and this code: `LocationsModuleBuilder`/`FarmInfoModuleBuilder` locate their target version via `owner_id = $team->id, name = 'Local Locations'` / `'Local Farm Info'` (the `'Local ' . $xlsformModule->name` convention the package already uses in `Xlsform::syncWithTemplate()`). If the underlying `XlsformModule` rows don't exist yet for a given template, this code has nothing to populate against for that template — no error, just nothing to do.

### Package change (in scope): `can_be_replaced` in `Xlsform::syncWithTemplate()`

`packages/filament-odk-link/src/Models/OdkLink/Xlsform.php::syncWithTemplate()` currently only understands `can_be_extended`: it attaches the global default module version, and if `can_be_extended`, *also* attaches a `firstOrCreate`'d `"Local {name}"` version immediately after it (additive — both show up in the exported form; this is the existing pattern used for e.g. HDDS hints, where the team supplements the global content).

`can_be_replaced` is already a declared boolean cast on `XlsformModule` but is never checked anywhere. Add: when a module's `can_be_replaced` is true, attach **only** the `firstOrCreate`'d `"Local {name}"` version, at the module's `default_order` — do not attach the global default version's pivot row at all. This is a small, targeted, reusable change (not specific to Locations/Farm Info) to `syncWithTemplate()`.

## `LocationsModuleBuilder::populate(Team $team): void`

1. `firstOrCreate` the `XlsformModuleVersion` where `owner_id = $team->id`, `name = 'Local Locations'`.
2. Rebuild its survey rows:
   - `begin_group location`
   - For each of the team's `LocationLevel`s, **ordered root-first** (walk the parent chain — fixes the legacy code's TODO about ordering; matches the root-first convention already used by `LocationLevel::getPos()` / `OdkFarmEntityService::buildLocationAttributes()`), at position `pos` (1-indexed):
     - `select_one loc{pos}` named `loc{pos}`, required, `choice_filter` = `"loc{parentPos}=${loc{parentPos}}"` when a parent level exists (omitted for the root level), label = that `LocationLevel`'s own name, generated for **every language the team has a Locale for** (see "Multi-language labels" below) — no lookup indirection through any `location_level`/`loc_level` field.
     - `calculate loc{pos}_name` with calculation `"jr:choice-name(${loc{pos}}, '${loc{pos}}')"` — the standard, ODK-documented form (confirmed against https://docs.getodk.org/form-operators-functions/ and the ODK forum; do **not** use a bare unquoted list name as a second argument).
   - `end_group`
3. Rebuild choice lists: one `ChoiceList` per level, **named `loc{pos}`** (positional, not the level's `slug` as the legacy code used — this must match the survey row's `select_one loc{pos}` type exactly), entries built from that level's `Location` rows, `cascade_filter` = parent `Location` id (same mechanism as today's `createCustomChoiceLists`).
4. Cleanup: if the team's level count has shrunk since the last run, delete survey rows / choice lists for positions beyond the current count.

Explicitly dropped vs. the legacy builder (per design review):
- No `-999` / "not found in list" choice entry + free-text fallback + calculate-with-coalesce. Locations are expected to be pre-loaded (via the location import feature) rather than created ad hoc during a survey.
- No `location_level`/`loc_level` field, no `loc{n}_type` calculate. (The example CSV's `jr:choice-name(${location_level}, 'loc1')` doesn't parse per ODK's documented syntax — second argument must be a quoted question reference like `'${loc1}'`, not a bare list name — and there's a list-name mismatch between the survey sheet's `loc_level` type and the choices sheet's `location_levels` list. Resolved by dropping the indirection entirely: each `select_one loc{pos}` question gets its label set directly from `LocationLevel::name` at generation time.)
- No GPS capture, no "Is this the correct farm?" confirm question, no reselect-note. (These belonged to the old combined module; GPS and farm confirmation now happen once, at registration time, in the separate Farm Registration form.)

A team with zero `LocationLevel`s yet gets an otherwise-empty `begin_group`/`end_group` pair — harmless no-op, no special-case branch needed.

## `FarmInfoModuleBuilder::populate(Team $team): void`

1. `firstOrCreate` the `XlsformModuleVersion` where `owner_id = $team->id`, `name = 'Local Farm Info'`.
2. Rebuild its survey rows:
   - `begin_group farms`
   - `select_one_from_file Farm_Summary.csv` named `ID`, required, label "Please select the farm you are visiting" (per language). `choice_filter` = `"loc{N}=${loc{N}}"`, where `N` is the team's total location-level count (the deepest level, e.g. `loc2=${loc2}` for a 2-level hierarchy) — this ties into the same `loc{pos}` property that `OdkFarmEntityService::buildLocationAttributes()` already writes onto every farm entity, and the same `loc{N}` field the Locations module's deepest question populates. Assumes the deepest `LocationLevel` is the one farms attach to (`has_farms = true`), matching every existing convention in `LocationLevel`/`OdkFarmEntityService`.
   - For every `DatasetVariable` on the shared `farm_entities` `Dataset` (`OdkFarmEntityService::LOCAL_DATASET_NAME`) with `description = 'identifier'` (ordered by `order`), a `calculate farm_{name}` row with calculation `"instance('Farm_Summary')/root/item[name=${ID}]/{name}"`. Repeat for `description = 'property'`.
   - `note farmer_note`: label per language, built by joining a header line, then `"{variable label}: ${farm_{name}},"` for each identifier (in order), then each property (in order), then the closing "If this is not the correct farm, please go back and reselect." line.
   - `end_group`
3. No `ChoiceList`s to build — `Farm_Summary` is Central's external entity list, surfaced via the template's `entities` sheet (`TemplateEntityList`), assumed already configured per template (same "admin panel task, out of scope" precondition as the module rows themselves).

Explicitly dropped vs. the legacy builder: no free-text "new farm" fallback (farm registration happens in its own form now), no GPS capture, no confirm/reselect question — matches the example CSV exactly (select farm → farmer_note → done).

### Variable scope (explicit known limitation)

`DatasetVariable` rows for the `farm_entities` dataset are global (one shared `Dataset`, `owner_id = null`), not scoped per team — confirmed no code anywhere filters them down to "this team's actual fields." Per your call: proceed using the full global list split by `description` tag, and flag that the real fix is migrating `farm_entities` to team-owned `Dataset`s, which would solve this automatically. A team's form referencing an identifier/property name that isn't actually on their Central entity list just evaluates empty via `instance()` lookup — cosmetic, not a functional break.

A related, not-fully-solved consequence: because the variable list is global, adding a new identifier/property (by any team) should ideally trigger every team's "Local Farm Info" to regenerate, not just the team that added it. This design does not solve that — flagged as follow-up work (e.g. hooking `DatasetVariable::saved()` to mark every team's `has_updated_locations`).

## Multi-language labels

Both builders write `SurveyRow.properties['label::{$language->name} ({$language->iso_alpha2})']` for every `Language` the team has a `Locale` for (via `$team->locales`) — not hardcoded English/Spanish. `HasLanguageStrings::bootHasLanguageStrings()` (confirmed to run on every model `saved()` event, not just template import) automatically converts these into proper `LanguageString` rows per locale on save — no additional plumbing needed beyond setting the right `properties` keys before save.

## Wiring

`Team::localiseXlsforms()` (`app/Models/Team.php`) currently calls `LocationSectionBuilder::createCustomLocationModuleVersion($this)` under the `has_updated_locations` flag. Replace with calls to both new builders:

```php
public function localiseXlsforms(): void
{
    if ($this->has_updated_locations) {
        LocationsModuleBuilder::populate($this);
        FarmInfoModuleBuilder::populate($this);
    }

    $this->has_updated_locations = false;
    $this->saveQuietly();
}
```

`app/Services/LocationSectionBuilder.php` is deleted. Its only other caller, `app/Console/Commands/test.php` (a scratch dev command), is updated to call the new builders instead.

## Known limitations / explicitly out of scope

1. `DatasetVariable` global sharing (see above) — proceeding as-is, flagged for a future team-owned-Dataset migration.
2. Farm list filtering degrades to "shows everything with the `'1'` placeholder" for any team/location not in `OdkFarmEntityService::GROUP_CLUSTER_LOOKUP`'s hardcoded table — a pre-existing, already-flagged gap that this change does not make worse, but also doesn't fix.
3. `FarmInfoModuleBuilder` content not auto-regenerating for other teams when a new global `DatasetVariable` is added elsewhere — flagged as follow-up.
4. The `instance('Farm_Summary')/root/item[name=${ID}]/{property}` calculate pattern has no precedent anywhere in this codebase (confirmed via search) — needs verification against a real ODK Central-deployed form before rollout.
5. Creating the `XlsformModule` rows (`Locations`/`Farm Info`, `can_be_replaced = true`) against each relevant `XlsformTemplate`, and the `Farm_Summary` `entities`-sheet `TemplateEntityList` row, are admin-panel tasks — explicitly out of scope for this code.

## Testing

- Pest feature tests seeding a team with varying `LocationLevel` counts (0, 1, 3) and `DatasetVariable` counts, asserting the generated `SurveyRow`/`ChoiceList`/`LanguageString` shape and count match expectations, including the level-shrinkage cleanup case.
- A package-level Pest test (in `packages/filament-odk-link`) for the new `can_be_replaced` behavior in `syncWithTemplate()` — asserting only the local version's pivot row is attached, not the global default's.
