# Location & Farm Info Module Rewrite Implementation Plan

**Status: Not Started**

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the legacy `App\Services\LocationSectionBuilder` with two focused builders — `LocationsModuleBuilder` and `FarmInfoModuleBuilder` — that populate two separate, team-owned XLSForm module versions, and teach the package's `Xlsform::syncWithTemplate()` a true full-replacement mode so a team's own version fully replaces the global one instead of sitting alongside it.

**Architecture:** One small package change (`can_be_replaced` support in `packages/filament-odk-link/src/Models/OdkLink/Xlsform.php::syncWithTemplate()`) plus two new app-level service classes under `app/Services/XlsformModules/`, each with a single static `populate(Team $team): void` entry point. `Team::localiseXlsforms()` calls both under the existing `has_updated_locations` flag, replacing its single legacy call.

**Tech Stack:** Laravel 11, Pest 3, Eloquent, the `stats4sd/filament-odk-link` path-repo package.

**Design doc:** `docs/plans/location-and-farm-info-module-rewrite.md` (read this first for the full rationale — known limitations, dropped legacy behavior, and why each decision was made).

## Global Constraints

- No new migrations. `can_be_replaced` already exists on `xlsform_modules` (boolean, default false) — confirmed in `packages/filament-odk-link/database/migrations/003_create_xlsform_modules_table.php`. `dataset_variables` has no `order` column — order identifier/property variables by `id` (creation order) instead.
- Creating the actual `XlsformModule` rows (`Locations`/`Farm Info`, `can_be_replaced = true`) against each `XlsformTemplate`, and the `Farm_Summary` `entities`-sheet row, are admin-panel tasks — explicitly out of scope. Nothing in this plan creates them.
- `DatasetVariable` rows for the `farm_entities` dataset are global (shared across every team, `owner_id = null`) — use the full global list split by `description` ('identifier'/'property'), per the design doc's explicit decision. Do not attempt to scope this per team.
- Match existing code style: single-quoted string concatenation for XLSForm calculation/expression strings (e.g. `'jr:choice-name(${loc' . $pos . '}, \'${loc' . $pos . '}\')'`), not double-quoted interpolation — this is the established convention in `LocationSectionBuilder` and avoids any `${...}` vs PHP string-interpolation ambiguity.
- Both new builders assume a single linear location hierarchy chain (one `LocationLevel` per position) — consistent with every existing convention in this codebase (`LocationLevel::getPos()`, `LocationLevel::farmLevelChain()`), not a new limitation introduced here.

---

## Task 1: `can_be_replaced` support in `Xlsform::syncWithTemplate()`

**Files:**
- Modify: `packages/filament-odk-link/src/Models/OdkLink/Xlsform.php:251-278`
- Test: `packages/filament-odk-link/tests/Unit/Models/XlsformSyncWithTemplateTest.php` (new)

**Interfaces:**
- Consumes: `XlsformModule->can_be_replaced` (bool, already exists), `XlsformModule->can_be_extended` (bool, already exists), `XlsformModule->default_order` (int), `XlsformModule->defaultXlsformVersion` (HasOne, already exists), `XlsformModule->name` (string).
- Produces: `Xlsform::syncWithTemplate(): void` — same public signature as today. New behavior: when a module's `can_be_replaced` is true, only the team's `firstOrCreate`'d `"Local {name}"` `XlsformModuleVersion` is attached to the xlsform's pivot (at the module's `default_order`) — the global default version's pivot row is never attached for that module. `can_be_extended` behavior (attach both, local at `default_order + 1`) is unchanged. A plain module (neither flag) still attaches only the global default, as today.

This task has no dependency on Tasks 2-4 and can be done first in isolation.

- [ ] **Step 1: Write the failing tests**

Create `packages/filament-odk-link/tests/Unit/Models/XlsformSyncWithTemplateTest.php`:

```php
<?php

use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;

// Xlsform's global scope calls Filament::hasTenancy(), which throws
// NoDefaultPanelSetException with no panel registered in this package's
// isolated Testbench environment - mock the registry so it returns false,
// matching the same pattern used in tests/Unit/Models/HasXlsformsTest.php.
beforeEach(function () {
    $mockPanel = Mockery::mock(Panel::class);
    $mockPanel->shouldReceive('hasTenancy')->andReturn(false);
    $mockPanel->shouldReceive('getTenantModel')->andReturn(null);

    $mockRegistry = Mockery::mock(PanelRegistry::class);
    $mockRegistry->shouldReceive('getDefault')->andReturn($mockPanel);

    app()->instance(PanelRegistry::class, $mockRegistry);

    $this->team = Team::factory()->create();
});

// Inserted at the DB level (not Xlsform::create()) to skip the model's heavy
// booted() hooks (ODK Central calls, media, auto-syncWithTemplate on save) -
// same convention as makeXlsformTemplate()/addModuleVersion() in tests/Pest.php.
function makeXlsformFor(XlsformTemplate $template, Team $team): Xlsform
{
    $id = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $template->id,
        'owner_id' => $team->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Xlsform::find($id);
}

it('attaches only the global default version for a plain module', function () {
    $template = makeXlsformTemplate();
    addModuleVersion($template, 'Metadata', ['default_order' => 1]);

    $xlsform = makeXlsformFor($template, $this->team);
    $xlsform->syncWithTemplate();

    $versions = $xlsform->xlsformModuleVersions()->get();

    expect($versions)->toHaveCount(1);
    expect($versions->first()->name)->toBe('Global Metadata');
    expect($versions->first()->pivot->order)->toBe(1);
});

it('attaches both the global and a local version when can_be_extended is true', function () {
    $template = makeXlsformTemplate();
    addModuleVersion($template, 'HDDS', ['default_order' => 1, 'can_be_extended' => true]);

    $xlsform = makeXlsformFor($template, $this->team);
    $xlsform->syncWithTemplate();

    $versions = $xlsform->xlsformModuleVersions()->get();

    expect($versions)->toHaveCount(2);
    expect($versions[0]->name)->toBe('Global HDDS');
    expect($versions[0]->pivot->order)->toBe(1);
    expect($versions[1]->name)->toBe('Local HDDS');
    expect($versions[1]->pivot->order)->toBe(2);
    expect($versions[1]->owner_id)->toBe($this->team->id);
});

it('attaches only a local version when can_be_replaced is true, not the global default', function () {
    $template = makeXlsformTemplate();
    addModuleVersion($template, 'Locations', ['default_order' => 1, 'can_be_replaced' => true]);

    $xlsform = makeXlsformFor($template, $this->team);
    $xlsform->syncWithTemplate();

    $versions = $xlsform->xlsformModuleVersions()->get();

    expect($versions)->toHaveCount(1);
    expect($versions->first()->name)->toBe('Local Locations');
    expect($versions->first()->owner_id)->toBe($this->team->id);
    expect($versions->first()->pivot->order)->toBe(1);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd packages/filament-odk-link && vendor/bin/pest tests/Unit/Models/XlsformSyncWithTemplateTest.php`
Expected: the `can_be_extended` and plain-module tests likely PASS already (existing behavior), but `'attaches only a local version when can_be_replaced is true...'` FAILS — it currently attaches the global default too, so `$versions` has count 2, not 1.

- [ ] **Step 3: Implement `can_be_replaced` handling**

Replace lines 251-278 of `packages/filament-odk-link/src/Models/OdkLink/Xlsform.php`:

```php
    // make sure the xlsform is using the latest template
    public function syncWithTemplate(): void
    {

        // check through the template modules; If this form is missing any, add the default version
        $this->xlsformTemplate->xlsformModules
            ->sortBy('default_order')

            // check for modules where the module version is not _already_ linked to this form (to avoid resetting custom ordering)
            ->filter(fn(XlsformModule $module) => $this->xlsformModuleVersions->doesntContain('xlsform_module_id', $module->id))
            ->each(function (XlsformModule $xlsformModule) {

                // `can_be_replaced` modules never attach the global default - only the
                // team's own local version, in the global version's place.
                if ($xlsformModule->can_be_replaced) {
                    $localModuleVersion = XlsformModuleVersion::firstOrCreate([
                        'owner_id' => $this->owner->id,
                        'name' => 'Local ' . $xlsformModule->name,
                    ]);

                    $this->xlsformModuleVersions()->sync([$localModuleVersion->id => ['order' => $xlsformModule->default_order]], detaching: false);

                    return;
                }

                $this->xlsformModuleVersions()->attach($xlsformModule->defaultXlsformVersion, ['order' => $xlsformModule->default_order]);

                // If the XlsformModule `can_be_extended` add a 'local' version of the module immediately after it
                if ($xlsformModule->can_be_extended) {
                    $localModuleVersion = XlsformModuleVersion::firstOrCreate([
                        'owner_id' => $this->owner->id,
                        'name' => 'Local ' . $xlsformModule->name,
                    ]);

                    $this->xlsformModuleVersions()->sync([$localModuleVersion->id => ['order' => $xlsformModule->default_order + 1]], detaching: false);
                }

            });

        $this->has_latest_template = true;
        $this->saveQuietly();
    }
```

(This also drops the unused `use (&$countModules)` closure capture from the original — it was never declared or read, dead code from an earlier version.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `cd packages/filament-odk-link && vendor/bin/pest tests/Unit/Models/XlsformSyncWithTemplateTest.php`
Expected: all 3 tests PASS.

- [ ] **Step 5: Run the full package suite to check for regressions**

Run: `cd packages/filament-odk-link && composer test`
Expected: PASS (no regressions elsewhere).

- [ ] **Step 6: Commit**

```bash
git add packages/filament-odk-link/src/Models/OdkLink/Xlsform.php packages/filament-odk-link/tests/Unit/Models/XlsformSyncWithTemplateTest.php
git commit -m "Add can_be_replaced support to Xlsform::syncWithTemplate()"
```

---

## Task 2: `LocationsModuleBuilder`

**Files:**
- Create: `app/Services/XlsformModules/LocationsModuleBuilder.php`
- Test: `tests/Feature/Services/LocationsModuleBuilderTest.php` (new)

**Interfaces:**
- Consumes: `Team->locationLevels` (HasMany `LocationLevel`, existing), `LocationLevel->pos` (int Attribute, existing, 1-indexed root-first), `LocationLevel->locations` (HasMany `Location`, existing), `Location->parent_id`/`->name`/`->id`, `Team->locales()` (BelongsToMany `Locale`, existing, via `HasXlsforms` trait), `Locale->language` (BelongsTo `Language`, existing), `Language->name`/`->iso_alpha2`.
- Produces: `LocationsModuleBuilder::populate(Team $team): void` — idempotent. Finds-or-creates an `XlsformModuleVersion` (`owner_id = $team->id`, `name = 'Local Locations'`) and rebuilds its `SurveyRow`s and `ChoiceList`s. Later tasks (Task 4) call this directly; no other task depends on its internals.

This task has no dependency on Task 1 or Task 3 and can run in parallel with either.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Services/LocationsModuleBuilderTest.php`:

```php
<?php

use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\XlsformModules\LocationsModuleBuilder;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

function makeLevel(Team $team, string $name, ?LocationLevel $parent = null): LocationLevel
{
    return LocationLevel::create([
        'owner_id' => $team->id,
        'parent_id' => $parent?->id,
        'name' => $name,
    ]);
}

function makeLocation(Team $team, LocationLevel $level, string $code, string $name, ?Location $parent = null): Location
{
    return Location::create([
        'owner_id' => $team->id,
        'location_level_id' => $level->id,
        'parent_id' => $parent?->id,
        'code' => $code,
        'name' => $name,
    ]);
}

function localVersion(Team $team): XlsformModuleVersion
{
    return XlsformModuleVersion::where('owner_id', $team->id)->where('name', 'Local Locations')->firstOrFail();
}

beforeEach(function () {
    Http::fake();
    $this->team = Team::factory()->create();
});

it('creates a Local Locations module version owned by the team', function () {
    LocationsModuleBuilder::populate($this->team);

    $this->assertDatabaseHas('xlsform_module_versions', [
        'owner_id' => $this->team->id,
        'name' => 'Local Locations',
    ]);
});

it('builds a select_one + calculate row per location level, root-first', function () {
    $region = makeLevel($this->team, 'Region');
    makeLevel($this->team, 'District', $region);

    LocationsModuleBuilder::populate($this->team);

    $rows = localVersion($this->team)->surveyRows;

    expect($rows->pluck('name')->all())->toBe([
        'location', 'loc1', 'loc1_name', 'loc2', 'loc2_name', 'location',
    ]);

    $loc1 = $rows->firstWhere('name', 'loc1');
    expect($loc1->type)->toBe('select_one loc1');
    expect($loc1->required)->toBeTrue();
    expect($loc1->choice_filter)->toBe('');
    expect($loc1->properties['label::English (en)'])->toBe('Region');

    $loc2 = $rows->firstWhere('name', 'loc2');
    expect($loc2->type)->toBe('select_one loc2');
    expect($loc2->choice_filter)->toBe('loc1=${loc1}');
    expect($loc2->properties['label::English (en)'])->toBe('District');

    $loc2Name = $rows->firstWhere('name', 'loc2_name');
    expect($loc2Name->type)->toBe('calculate');
    expect($loc2Name->calculation)->toBe('jr:choice-name(${loc2}, \'${loc2}\')');
});

it('builds a per-level choice list from the level\'s locations', function () {
    $region = makeLevel($this->team, 'Region');
    $regionLocation = makeLocation($this->team, $region, 'R1', 'North');

    LocationsModuleBuilder::populate($this->team);

    $choiceList = localVersion($this->team)->choiceLists->firstWhere('list_name', 'loc1');

    expect($choiceList)->not->toBeNull();

    $entry = ChoiceListEntry::where('choice_list_id', $choiceList->id)->where('name', $regionLocation->id)->first();
    expect($entry)->not->toBeNull();
    expect($entry->cascade_filter)->toBeNull();
    expect($entry->properties['label::English (en)'])->toBe('North');
});

it('sets cascade_filter to the parent location id for a non-root level', function () {
    $region = makeLevel($this->team, 'Region');
    $district = makeLevel($this->team, 'District', $region);
    $regionLocation = makeLocation($this->team, $region, 'R1', 'North');
    $districtLocation = makeLocation($this->team, $district, 'D1', 'Central', $regionLocation);

    LocationsModuleBuilder::populate($this->team);

    $choiceList = localVersion($this->team)->choiceLists->firstWhere('list_name', 'loc2');
    $entry = ChoiceListEntry::where('choice_list_id', $choiceList->id)->where('name', $districtLocation->id)->first();

    expect($entry->cascade_filter)->toBe((string) $regionLocation->id);
});

it('leaves an empty group when the team has no location levels yet', function () {
    LocationsModuleBuilder::populate($this->team);

    $version = localVersion($this->team);

    expect($version->surveyRows->pluck('name')->all())->toBe(['location', 'location']);
    expect($version->choiceLists)->toHaveCount(0);
});

it('removes stale rows and choice lists when a level is removed', function () {
    $region = makeLevel($this->team, 'Region');
    $district = makeLevel($this->team, 'District', $region);

    LocationsModuleBuilder::populate($this->team);

    $district->delete();

    LocationsModuleBuilder::populate($this->team);

    $version = localVersion($this->team);

    expect($version->surveyRows->pluck('name')->all())->toBe(['location', 'loc1', 'loc1_name', 'location']);
    expect($version->choiceLists->pluck('list_name')->all())->toBe(['loc1']);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Services/LocationsModuleBuilderTest.php`
Expected: FAIL with "Class App\Services\XlsformModules\LocationsModuleBuilder not found".

- [ ] **Step 3: Implement `LocationsModuleBuilder`**

Create `app/Services/XlsformModules/LocationsModuleBuilder.php`:

```php
<?php

namespace App\Services\XlsformModules;

use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class LocationsModuleBuilder
{
    public static function populate(Team $team): void
    {
        $moduleVersion = XlsformModuleVersion::firstOrCreate([
            'owner_id' => $team->id,
            'name' => 'Local Locations',
        ]);

        $levels = static::orderedLevels($team);

        static::buildSurveyRows($moduleVersion, $team, $levels);
        static::buildChoiceLists($moduleVersion, $team, $levels);
    }

    /** @return array<int, LocationLevel> keyed by 1-indexed position, root first */
    protected static function orderedLevels(Team $team): array
    {
        $byPos = [];

        foreach ($team->locationLevels as $level) {
            $byPos[$level->pos] = $level;
        }

        ksort($byPos);

        return $byPos;
    }

    /** @param array<int, LocationLevel> $levels */
    protected static function buildSurveyRows(XlsformModuleVersion $moduleVersion, Team $team, array $levels): void
    {
        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'location', 'type' => 'begin_group'],
            ['row_number' => 1],
        );

        $rowNumber = 2;

        foreach ($levels as $pos => $level) {
            $parentPos = $pos - 1;
            $choiceFilter = $parentPos >= 1 ? 'loc' . $parentPos . '=${loc' . $parentPos . '}' : '';

            $moduleVersion->surveyRows()->updateOrCreate(
                ['name' => 'loc' . $pos, 'type' => 'select_one loc' . $pos],
                [
                    'required' => true,
                    'choice_filter' => $choiceFilter,
                    'properties' => collect(static::labelProperties($team, $level->name)),
                    'row_number' => $rowNumber++,
                ],
            );

            $moduleVersion->surveyRows()->updateOrCreate(
                ['name' => 'loc' . $pos . '_name', 'type' => 'calculate'],
                [
                    'calculation' => 'jr:choice-name(${loc' . $pos . '}, \'${loc' . $pos . '}\')',
                    'row_number' => $rowNumber++,
                ],
            );
        }

        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'location', 'type' => 'end_group'],
            ['row_number' => $rowNumber++],
        );

        static::deleteStaleSurveyRows($moduleVersion, count($levels));
    }

    protected static function deleteStaleSurveyRows(XlsformModuleVersion $moduleVersion, int $maxPos): void
    {
        $staleIds = $moduleVersion->surveyRows()
            ->where('name', 'like', 'loc%')
            ->get()
            ->filter(function ($row) use ($maxPos) {
                if (! preg_match('/^loc(\d+)(_name)?$/', $row->name, $matches)) {
                    return false;
                }

                return (int) $matches[1] > $maxPos;
            })
            ->pluck('id');

        $moduleVersion->surveyRows()->whereIn('id', $staleIds)->delete();
    }

    /** @param array<int, LocationLevel> $levels */
    protected static function buildChoiceLists(XlsformModuleVersion $moduleVersion, Team $team, array $levels): void
    {
        foreach ($levels as $pos => $level) {
            $choiceList = ChoiceList::firstOrCreate([
                'xlsform_module_version_id' => $moduleVersion->id,
                'list_name' => 'loc' . $pos,
            ]);

            foreach ($level->locations as $location) {
                ChoiceListEntry::updateOrCreate(
                    [
                        'name' => $location->id,
                        'choice_list_id' => $choiceList->id,
                    ],
                    [
                        'owner_id' => $team->id,
                        'cascade_filter' => $location->parent_id,
                        'properties' => collect(static::labelProperties($team, $location->name)),
                    ],
                );
            }
        }

        static::deleteStaleChoiceLists($moduleVersion, count($levels));
    }

    protected static function deleteStaleChoiceLists(XlsformModuleVersion $moduleVersion, int $maxPos): void
    {
        $moduleVersion->choiceLists()
            ->where('list_name', 'like', 'loc%')
            ->get()
            ->filter(function (ChoiceList $choiceList) use ($maxPos) {
                if (! preg_match('/^loc(\d+)$/', $choiceList->list_name, $matches)) {
                    return false;
                }

                return (int) $matches[1] > $maxPos;
            })
            ->each(fn (ChoiceList $choiceList) => $choiceList->delete());
    }

    /** @return array<string, string> */
    protected static function labelProperties(Team $team, string $label): array
    {
        $properties = [];

        foreach ($team->locales()->with('language')->get() as $locale) {
            $language = $locale->language;
            $properties['label::' . $language->name . ' (' . $language->iso_alpha2 . ')'] = $label;
        }

        return $properties;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Services/LocationsModuleBuilderTest.php`
Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/XlsformModules/LocationsModuleBuilder.php tests/Feature/Services/LocationsModuleBuilderTest.php
git commit -m "Add LocationsModuleBuilder for the split locations XLSForm module"
```

---

## Task 3: `FarmInfoModuleBuilder`

**Files:**
- Create: `app/Services/XlsformModules/FarmInfoModuleBuilder.php`
- Test: `tests/Feature/Services/FarmInfoModuleBuilderTest.php` (new)

**Interfaces:**
- Consumes: `Team->locationLevels()` (HasMany, existing), `OdkFarmEntityService::LOCAL_DATASET_NAME` (string constant `'farm_entities'`, existing, `app/Services/OdkFarmEntityService.php`), `Dataset::firstWhere()`/`Dataset->variables()` (HasMany `DatasetVariable`, existing), `DatasetVariable->name`/`->label`/`->description`, `Team->locales()` (existing, same as Task 2).
- Produces: `FarmInfoModuleBuilder::populate(Team $team): void` — idempotent. Finds-or-creates an `XlsformModuleVersion` (`owner_id = $team->id`, `name = 'Local Farm Info'`) and rebuilds its `SurveyRow`s. No `ChoiceList`s are created (`Farm_Summary` is an external ODK Central entity list, out of scope per the design doc).

This task has no dependency on Task 1 or Task 2 and can run in parallel with either.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Services/FarmInfoModuleBuilderTest.php`:

```php
<?php

use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use App\Services\XlsformModules\FarmInfoModuleBuilder;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

function farmDataset(): Dataset
{
    return Dataset::firstOrCreate(
        ['name' => OdkFarmEntityService::LOCAL_DATASET_NAME, 'owner_id' => null],
        ['label' => 'team_code'],
    );
}

function localFarmInfoVersion(Team $team): XlsformModuleVersion
{
    return XlsformModuleVersion::where('owner_id', $team->id)->where('name', 'Local Farm Info')->firstOrFail();
}

beforeEach(function () {
    Http::fake();
    $this->team = Team::factory()->create();
});

it('creates a Local Farm Info module version owned by the team', function () {
    FarmInfoModuleBuilder::populate($this->team);

    $this->assertDatabaseHas('xlsform_module_versions', [
        'owner_id' => $this->team->id,
        'name' => 'Local Farm Info',
    ]);
});

it('builds the farm picker row with no choice_filter when the team has no location levels', function () {
    FarmInfoModuleBuilder::populate($this->team);

    $idRow = localFarmInfoVersion($this->team)->surveyRows->firstWhere('name', 'ID');

    expect($idRow->type)->toBe('select_one_from_file Farm_Summary.csv');
    expect($idRow->required)->toBeTrue();
    expect($idRow->choice_filter)->toBe('');
});

it('filters the farm picker on the deepest location level', function () {
    $region = LocationLevel::create(['owner_id' => $this->team->id, 'name' => 'Region']);
    LocationLevel::create(['owner_id' => $this->team->id, 'parent_id' => $region->id, 'name' => 'District']);

    FarmInfoModuleBuilder::populate($this->team);

    $idRow = localFarmInfoVersion($this->team)->surveyRows->firstWhere('name', 'ID');

    expect($idRow->choice_filter)->toBe('loc2=${loc2}');
});

it('builds a calculate row per identifier and property variable, pulling from the selected entity', function () {
    $dataset = farmDataset();
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'certificate_no', 'label' => 'Certificate Number', 'type' => 'string', 'description' => 'identifier']);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'field_size', 'label' => 'Field Size (ha)', 'type' => 'string', 'description' => 'property']);

    FarmInfoModuleBuilder::populate($this->team);

    $rows = localFarmInfoVersion($this->team)->surveyRows;

    $identifierRow = $rows->firstWhere('name', 'farm_certificate_no');
    expect($identifierRow->type)->toBe('calculate');
    expect($identifierRow->calculation)->toBe('instance(\'Farm_Summary\')/root/item[name=${ID}]/certificate_no');

    $propertyRow = $rows->firstWhere('name', 'farm_field_size');
    expect($propertyRow->calculation)->toBe('instance(\'Farm_Summary\')/root/item[name=${ID}]/field_size');
});

it('builds the farmer_note listing every identifier and property with its label', function () {
    $dataset = farmDataset();
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'certificate_no', 'label' => 'Certificate Number', 'type' => 'string', 'description' => 'identifier']);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'field_size', 'label' => 'Field Size (ha)', 'type' => 'string', 'description' => 'property']);

    FarmInfoModuleBuilder::populate($this->team);

    $note = localFarmInfoVersion($this->team)->surveyRows->firstWhere('name', 'farmer_note');
    expect($note->type)->toBe('note');

    $text = $note->properties['label::English (en)'];

    expect($text)->toContain('Certificate Number: ${farm_certificate_no},');
    expect($text)->toContain('Field Size (ha): ${farm_field_size},');
    expect($text)->toContain('If this is not the correct farm, please go back and reselect.');
});

it('removes a stale calculate row when a variable is removed', function () {
    $dataset = farmDataset();
    $variable = DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'certificate_no', 'label' => 'Certificate Number', 'type' => 'string', 'description' => 'identifier']);

    FarmInfoModuleBuilder::populate($this->team);
    $variable->delete();
    FarmInfoModuleBuilder::populate($this->team);

    expect(localFarmInfoVersion($this->team)->surveyRows->firstWhere('name', 'farm_certificate_no'))->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Services/FarmInfoModuleBuilderTest.php`
Expected: FAIL with "Class App\Services\XlsformModules\FarmInfoModuleBuilder not found".

- [ ] **Step 3: Implement `FarmInfoModuleBuilder`**

Create `app/Services/XlsformModules/FarmInfoModuleBuilder.php`:

```php
<?php

namespace App\Services\XlsformModules;

use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class FarmInfoModuleBuilder
{
    public static function populate(Team $team): void
    {
        $moduleVersion = XlsformModuleVersion::firstOrCreate([
            'owner_id' => $team->id,
            'name' => 'Local Farm Info',
        ]);

        $levelCount = $team->locationLevels()->count();
        $identifiers = static::variables('identifier');
        $properties = static::variables('property');

        static::buildSurveyRows($moduleVersion, $team, $levelCount, $identifiers, $properties);
    }

    /** @return Collection<int, DatasetVariable> */
    protected static function variables(string $description): Collection
    {
        $dataset = Dataset::firstWhere('name', OdkFarmEntityService::LOCAL_DATASET_NAME);

        if ($dataset === null) {
            return collect();
        }

        return $dataset->variables()->where('description', $description)->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, DatasetVariable>  $identifiers
     * @param  Collection<int, DatasetVariable>  $properties
     */
    protected static function buildSurveyRows(XlsformModuleVersion $moduleVersion, Team $team, int $levelCount, Collection $identifiers, Collection $properties): void
    {
        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'farms', 'type' => 'begin_group'],
            ['row_number' => 1],
        );

        $rowNumber = 2;

        $choiceFilter = $levelCount >= 1 ? 'loc' . $levelCount . '=${loc' . $levelCount . '}' : '';

        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'ID', 'type' => 'select_one_from_file Farm_Summary.csv'],
            [
                'required' => true,
                'choice_filter' => $choiceFilter,
                'properties' => collect(static::labelProperties($team, 'Please select the farm you are visiting')),
                'row_number' => $rowNumber++,
            ],
        );

        $allVariables = $identifiers->concat($properties);

        foreach ($allVariables as $variable) {
            $moduleVersion->surveyRows()->updateOrCreate(
                ['name' => 'farm_' . $variable->name, 'type' => 'calculate'],
                [
                    'calculation' => 'instance(\'Farm_Summary\')/root/item[name=${ID}]/' . $variable->name,
                    'row_number' => $rowNumber++,
                ],
            );
        }

        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'farmer_note', 'type' => 'note'],
            [
                'properties' => collect(static::labelProperties($team, static::noteText($identifiers, $properties))),
                'row_number' => $rowNumber++,
            ],
        );

        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'farms', 'type' => 'end_group'],
            ['row_number' => $rowNumber++],
        );

        static::deleteStaleCalculateRows($moduleVersion, $allVariables);
    }

    /** @param Collection<int, DatasetVariable> $variables */
    protected static function deleteStaleCalculateRows(XlsformModuleVersion $moduleVersion, Collection $variables): void
    {
        $currentNames = $variables->map(fn (DatasetVariable $variable) => 'farm_' . $variable->name);

        $moduleVersion->surveyRows()
            ->where('type', 'calculate')
            ->where('name', 'like', 'farm_%')
            ->whereNotIn('name', $currentNames)
            ->delete();
    }

    /**
     * @param  Collection<int, DatasetVariable>  $identifiers
     * @param  Collection<int, DatasetVariable>  $properties
     */
    protected static function noteText(Collection $identifiers, Collection $properties): string
    {
        $lines = ['You have selected the following farm:', ''];

        foreach ($identifiers as $variable) {
            $lines[] = $variable->label . ': ${farm_' . $variable->name . '},';
        }

        $lines[] = '';

        foreach ($properties as $variable) {
            $lines[] = $variable->label . ': ${farm_' . $variable->name . '},';
        }

        $lines[] = '';
        $lines[] = 'If this is not the correct farm, please go back and reselect.';

        return implode(PHP_EOL, $lines);
    }

    /** @return array<string, string> */
    protected static function labelProperties(Team $team, string $label): array
    {
        $properties = [];

        foreach ($team->locales()->with('language')->get() as $locale) {
            $language = $locale->language;
            $properties['label::' . $language->name . ' (' . $language->iso_alpha2 . ')'] = $label;
        }

        return $properties;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Services/FarmInfoModuleBuilderTest.php`
Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/XlsformModules/FarmInfoModuleBuilder.php tests/Feature/Services/FarmInfoModuleBuilderTest.php
git commit -m "Add FarmInfoModuleBuilder for the split farm-selection XLSForm module"
```

---

## Task 4: Wire up `Team::localiseXlsforms()` and remove the legacy builder

**Files:**
- Modify: `app/Models/Team.php:8` (import), `app/Models/Team.php:335-343` (`localiseXlsforms()`)
- Modify: `app/Console/Commands/test.php` (scratch dev command)
- Delete: `app/Services/LocationSectionBuilder.php`
- Test: `tests/Feature/Models/TeamLocaliseXlsformsTest.php` (new)

**Interfaces:**
- Consumes: `LocationsModuleBuilder::populate(Team $team): void` (Task 2), `FarmInfoModuleBuilder::populate(Team $team): void` (Task 3).
- Produces: `Team::localiseXlsforms(): void` — same public signature and `has_updated_locations` gating as before; internals now call both new builders instead of the legacy one.

Depends on Tasks 2 and 3 being complete (both builder classes must exist).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Models/TeamLocaliseXlsformsTest.php`:

```php
<?php

use App\Models\Team;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();
    $this->team = Team::factory()->create();
});

it('populates both Local Locations and Local Farm Info when has_updated_locations is true, then resets the flag', function () {
    $this->team->update(['has_updated_locations' => true]);

    $this->team->localiseXlsforms();

    $this->assertDatabaseHas('xlsform_module_versions', ['owner_id' => $this->team->id, 'name' => 'Local Locations']);
    $this->assertDatabaseHas('xlsform_module_versions', ['owner_id' => $this->team->id, 'name' => 'Local Farm Info']);
    expect($this->team->fresh()->has_updated_locations)->toBeFalse();
});

it('does nothing when has_updated_locations is false', function () {
    $this->team->update(['has_updated_locations' => false]);

    $this->team->localiseXlsforms();

    $this->assertDatabaseMissing('xlsform_module_versions', ['owner_id' => $this->team->id, 'name' => 'Local Locations']);
    $this->assertDatabaseMissing('xlsform_module_versions', ['owner_id' => $this->team->id, 'name' => 'Local Farm Info']);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Models/TeamLocaliseXlsformsTest.php`
Expected: FAIL — `localiseXlsforms()` still calls the legacy `LocationSectionBuilder`, so no `Local Locations`/`Local Farm Info` rows exist yet.

- [ ] **Step 3: Update `Team::localiseXlsforms()`**

In `app/Models/Team.php`, replace the import on line 8:

```php
use App\Services\LocationSectionBuilder;
```

with:

```php
use App\Services\XlsformModules\FarmInfoModuleBuilder;
use App\Services\XlsformModules\LocationsModuleBuilder;
```

Then replace `localiseXlsforms()` (lines 335-343):

```php
    public function localiseXlsforms(): void
    {
        if ($this->has_updated_locations) {
            LocationSectionBuilder::createCustomLocationModuleVersion($this);
        }

        $this->has_updated_locations = false;
        $this->saveQuietly();
    }
```

with:

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

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Models/TeamLocaliseXlsformsTest.php`
Expected: both tests PASS.

- [ ] **Step 5: Update the scratch dev command and delete the legacy builder**

In `app/Console/Commands/test.php`, replace:

```php
use App\Services\LocationSectionBuilder;
```

with:

```php
use App\Services\XlsformModules\FarmInfoModuleBuilder;
use App\Services\XlsformModules\LocationsModuleBuilder;
```

and replace the `handle()` body:

```php
    public function handle()
    {
       LocationSectionBuilder::createCustomLocationModuleVersion(Team::find(3));
    }
```

with:

```php
    public function handle()
    {
       LocationsModuleBuilder::populate(Team::find(3));
       FarmInfoModuleBuilder::populate(Team::find(3));
    }
```

Delete `app/Services/LocationSectionBuilder.php` (no other callers remain — confirmed via `grep -rl "LocationSectionBuilder" app/`).

- [ ] **Step 6: Run the full app test suite to check for regressions**

Run: `./vendor/bin/pest`
Expected: PASS (no regressions; no other file references `LocationSectionBuilder`).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Team.php app/Console/Commands/test.php tests/Feature/Models/TeamLocaliseXlsformsTest.php
git rm app/Services/LocationSectionBuilder.php
git commit -m "Wire Team::localiseXlsforms() to the new split module builders"
```
