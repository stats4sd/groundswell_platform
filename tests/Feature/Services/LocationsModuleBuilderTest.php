<?php

use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\XlsformModules\LocationsModuleBuilder;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

function makeLocationLevel(Team $team, string $name, ?LocationLevel $parent = null): LocationLevel
{
    return LocationLevel::create([
        'owner_id' => $team->id,
        'parent_id' => $parent?->id,
        'name' => $name,
    ]);
}

function makeChildLocation(Team $team, LocationLevel $level, string $code, string $name, ?Location $parent = null): Location
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
    return XlsformModuleVersion::where('owner_id', $team->id)->where('name', 'Local locations')->firstOrFail();
}

beforeEach(function () {
    Http::fake();
    $this->team = Team::factory()->create();
});

it('creates a Local Locations module version owned by the team', function () {
    LocationsModuleBuilder::populate($this->team);

    $this->assertDatabaseHas('xlsform_module_versions', [
        'owner_id' => $this->team->id,
        'name' => 'Local locations',
    ]);
});

it('builds a select_one + calculate row per location level, root-first', function () {
    $region = makeLocationLevel($this->team, 'Region');
    makeLocationLevel($this->team, 'District', $region);

    LocationsModuleBuilder::populate($this->team);

    $rows = localVersion($this->team)->surveyRows;

    expect($rows->pluck('name')->all())->toBe([
        'location', 'loc1', 'loc1_name', 'loc2', 'loc2_name', 'location',
    ]);

    // label:: keys are extracted from `properties` into LanguageString rows by the
    // package's HasLanguageStrings saved() hook, so labels are asserted via defaultLabel.
    $loc1 = $rows->firstWhere('name', 'loc1');
    expect($loc1->type)->toBe('select_one loc1');
    expect($loc1->required)->toBeTrue();
    expect($loc1->choice_filter)->toBe('');
    expect($loc1->defaultLabel->text)->toBe('Region');

    $loc2 = $rows->firstWhere('name', 'loc2');
    expect($loc2->type)->toBe('select_one loc2');
    expect($loc2->choice_filter)->toBe('filter=${loc1}');
    expect($loc2->defaultLabel->text)->toBe('District');

    $loc2Name = $rows->firstWhere('name', 'loc2_name');
    expect($loc2Name->type)->toBe('calculate');
    expect($loc2Name->calculation)->toBe('jr:choice-name(${loc2}, \'${loc2}\')');
});

it('builds a per-level choice list from the level\'s locations', function () {
    $region = makeLocationLevel($this->team, 'Region');
    $regionLocation = makeChildLocation($this->team, $region, 'R1', 'North');

    LocationsModuleBuilder::populate($this->team);

    $choiceList = localVersion($this->team)->choiceLists->firstWhere('list_name', 'loc1');

    expect($choiceList)->not->toBeNull();

    $entry = ChoiceListEntry::where('choice_list_id', $choiceList->id)->where('name', $regionLocation->id)->first();
    expect($entry)->not->toBeNull();
    expect($entry->cascade_filter)->toBeNull();
    expect($entry->defaultLabel->text)->toBe('North');
});

it('sets cascade_filter to the parent location id for a non-root level', function () {
    $region = makeLocationLevel($this->team, 'Region');
    $district = makeLocationLevel($this->team, 'District', $region);
    $regionLocation = makeChildLocation($this->team, $region, 'R1', 'North');
    $districtLocation = makeChildLocation($this->team, $district, 'D1', 'Central', $regionLocation);

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
    $region = makeLocationLevel($this->team, 'Region');
    $district = makeLocationLevel($this->team, 'District', $region);

    LocationsModuleBuilder::populate($this->team);

    $district->delete();

    LocationsModuleBuilder::populate($this->team);

    $version = localVersion($this->team);

    expect($version->surveyRows->pluck('name')->all())->toBe(['location', 'loc1', 'loc1_name', 'location']);
    expect($version->choiceLists->pluck('list_name')->all())->toBe(['loc1']);
});
