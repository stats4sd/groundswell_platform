<?php

use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Support\Facades\Http;

// Covers only the local, no-Central-API-call parts of the loc{n} <-> Location mapping
// (see docs/plans/map-loc-attributes-to-location-levels.md): resolveLocationFromAttributes()
// (read side, ODK -> Location) and buildLocationAttributes() (write side, Location -> ODK).

function odkFarmEntityService(): OdkFarmEntityService
{
    return app(OdkFarmEntityService::class);
}

function makeLevel(Team $team, string $name, ?LocationLevel $parent = null, bool $hasFarms = false): LocationLevel
{
    return LocationLevel::create([
        'owner_id' => $team->id,
        'parent_id' => $parent?->id,
        'name' => $name,
        'has_farms' => $hasFarms,
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

describe('OdkFarmEntityService::resolveLocationFromAttributes', function () {

    beforeEach(function () {
        Http::fake();
        $this->team = Team::factory()->create();
    });

    test('resolves the deepest loc{n}_name to the matching existing location', function () {
        $cluster = makeLevel($this->team, 'Cluster');
        $group = makeLevel($this->team, 'Group', $cluster, hasFarms: true);

        $clusterLocation = makeLocation($this->team, $cluster, 'C1', 'Alpha');
        $groupLocation = makeLocation($this->team, $group, 'G1', 'Beta', $clusterLocation);

        $locationId = odkFarmEntityService()->resolveLocationFromAttributes($this->team, [
            'loc1_name' => 'Alpha',
            'loc2_name' => 'Beta',
        ]);

        expect($locationId)->toBe($groupLocation->id);
    });

    test('caps at the team\'s configured chain length, ignoring deeper loc{n} positions', function () {
        $cluster = makeLevel($this->team, 'Cluster');
        $group = makeLevel($this->team, 'Group', $cluster, hasFarms: true);

        makeLocation($this->team, $cluster, 'C1', 'Alpha');
        $groupLocation = makeLocation($this->team, $group, 'G1', 'Beta');

        // loc3_name has no corresponding configured level (only 2 exist) - it must be
        // ignored, resolution should still succeed via loc2_name.
        $locationId = odkFarmEntityService()->resolveLocationFromAttributes($this->team, [
            'loc1_name' => 'Alpha',
            'loc2_name' => 'Beta',
            'loc3_name' => 'Household 1',
        ]);

        expect($locationId)->toBe($groupLocation->id);
    });

    test('matches case-insensitively', function () {
        $group = makeLevel($this->team, 'Group', hasFarms: true);
        $groupLocation = makeLocation($this->team, $group, 'G1', 'Beta Village');

        $locationId = odkFarmEntityService()->resolveLocationFromAttributes($this->team, [
            'loc1_name' => 'BETA village',
        ]);

        expect($locationId)->toBe($groupLocation->id);
    });

    test('returns null when the deepest loc{n}_name has no matching location', function () {
        $group = makeLevel($this->team, 'Group', hasFarms: true);
        makeLocation($this->team, $group, 'G1', 'Beta');

        $locationId = odkFarmEntityService()->resolveLocationFromAttributes($this->team, [
            'loc1_name' => 'Does Not Exist',
        ]);

        expect($locationId)->toBeNull();
    });

    test('returns null when the data has no usable loc{n}_name at all', function () {
        makeLevel($this->team, 'Group', hasFarms: true);

        $locationId = odkFarmEntityService()->resolveLocationFromAttributes($this->team, [
            'team_code' => 'FARM-1',
        ]);

        expect($locationId)->toBeNull();
    });

    test('returns null when the team has no has_farms level configured yet', function () {
        $locationId = odkFarmEntityService()->resolveLocationFromAttributes($this->team, [
            'loc1_name' => 'Alpha',
        ]);

        expect($locationId)->toBeNull();
    });

    test('does not match a name reused at a different hierarchy position', function () {
        $cluster = makeLevel($this->team, 'Cluster');
        $group = makeLevel($this->team, 'Group', $cluster, hasFarms: true);

        // Both levels happen to have a location confusingly named "Central".
        $clusterLocation = makeLocation($this->team, $cluster, 'C1', 'Central');
        makeLocation($this->team, $group, 'G1', 'Central', $clusterLocation);

        // Only loc1_name is present, so position 1 (Cluster) must be the one matched -
        // not the Group location, even though its name also matches.
        $locationId = odkFarmEntityService()->resolveLocationFromAttributes($this->team, [
            'loc1_name' => 'Central',
        ]);

        expect($locationId)->toBe($clusterLocation->id);
    });

    test('does not match a location belonging to a different team', function () {
        $otherTeam = Team::factory()->create();

        $group = makeLevel($this->team, 'Group', hasFarms: true);
        makeLevel($otherTeam, 'Group', hasFarms: true);

        makeLocation($this->team, $group, 'G1', 'Beta');
        $otherGroup = LocationLevel::where('owner_id', $otherTeam->id)->first();
        makeLocation($otherTeam, $otherGroup, 'G1', 'Beta');

        $locationId = odkFarmEntityService()->resolveLocationFromAttributes($otherTeam, [
            'loc1_name' => 'Beta',
        ]);

        $ownLocation = Location::where('owner_id', $otherTeam->id)->first();
        expect($locationId)->toBe($ownLocation->id);
    });

});

describe('OdkFarmEntityService::buildLocationAttributes', function () {

    beforeEach(function () {
        Http::fake();
        $this->team = Team::factory()->create();
    });

    test('walks the full parent chain regardless of depth, not hardcoded to any fixed number of levels or level names', function () {
        $region = makeLevel($this->team, 'Region');
        $district = makeLevel($this->team, 'District', $region);
        $village = makeLevel($this->team, 'Village', $district, hasFarms: true);

        $regionLocation = makeLocation($this->team, $region, 'R1', 'North Region');
        $districtLocation = makeLocation($this->team, $district, 'D1', 'Central District', $regionLocation);
        $villageLocation = makeLocation($this->team, $village, 'V1', 'Green Village', $districtLocation);

        $attributes = odkFarmEntityService()->buildLocationAttributes($villageLocation->id);

        // toEqual (not toBe): buildLocationAttributes() walks leaf-to-root, so keys land in
        // loc3/loc2/loc1 order - the map's content is what matters, not key order.
        expect($attributes)->toEqual([
            'loc1' => '1', // locations.id
            'loc1_name' => 'North Region',
            'loc1_type' => 'Region',
            'loc2' => '2',
            'loc2_name' => 'Central District',
            'loc2_type' => 'District',
            'loc3' => '3',
            'loc3_name' => 'Green Village',
            'loc3_type' => 'Village',
        ]);
    });

    test('returns an empty array for a non-existent location id', function () {
        expect(odkFarmEntityService()->buildLocationAttributes(999999))->toBe([]);
    });

    test('round-trips through resolveLocationFromAttributes back to the same location', function () {
        $cluster = makeLevel($this->team, 'Cluster');
        $group = makeLevel($this->team, 'Group', $cluster, hasFarms: true);

        $clusterLocation = makeLocation($this->team, $cluster, 'C1', 'Alpha');
        $groupLocation = makeLocation($this->team, $group, 'G1', 'Beta', $clusterLocation);

        $attributes = odkFarmEntityService()->buildLocationAttributes($groupLocation->id);
        $resolvedId = odkFarmEntityService()->resolveLocationFromAttributes($this->team, $attributes);

        expect($resolvedId)->toBe($groupLocation->id);
    });

});
