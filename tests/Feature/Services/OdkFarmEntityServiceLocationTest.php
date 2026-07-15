<?php

use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;

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

/**
 * Builds a minimal Xlsform (owned by $team) with `loc{n}` ChoiceLists/ChoiceListEntries -
 * the real, per-team, non-hardcoded source resolveChoiceIdForLocation() reads from (see
 * docs/plans/map-loc-attributes-to-location-levels.md). Inserted at the DB level (not via
 * Eloquent create()) to skip form-hierarchy model events, matching the filament-odk-link
 * package's own test convention (packages/filament-odk-link/tests/Pest.php).
 *
 * @param  array<string, array<int, array{name: string, label: string, cascadeFilter: ?string}>>  $listNamesToEntries
 */
function makeXlsformWithChoiceLists(Team $team, array $listNamesToEntries): Xlsform
{
    $templateId = DB::table('xlsform_templates')->insertGetId([
        'title' => 'Test Template',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $moduleId = DB::table('xlsform_modules')->insertGetId([
        'xlsform_template_id' => $templateId,
        'name' => 'Test Module',
        'label' => 'Test Module',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $moduleVersionId = DB::table('xlsform_module_versions')->insertGetId([
        'xlsform_module_id' => $moduleId,
        'name' => 'Global Test Module',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $xlsformId = DB::table('xlsforms')->insertGetId([
        'xlsform_template_id' => $templateId,
        'owner_id' => $team->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('selected_xlsform_module_versions')->insert([
        'xlsform_module_version_id' => $moduleVersionId,
        'xlsform_id' => $xlsformId,
        'order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $labelTypeId = LanguageStringType::where('name', 'label')->value('id');
    $localeId = DB::table('locales')->insertGetId([
        'language_id' => Language::query()->value('id'),
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($listNamesToEntries as $listName => $entries) {
        $choiceListId = DB::table('choice_lists')->insertGetId([
            'xlsform_module_version_id' => $moduleVersionId,
            'list_name' => $listName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($entries as $entry) {
            $entryId = DB::table('choice_list_entries')->insertGetId([
                'choice_list_id' => $choiceListId,
                'name' => $entry['name'],
                'cascade_filter' => $entry['cascadeFilter'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('language_strings')->insert([
                'locale_id' => $localeId,
                'language_string_type_id' => $labelTypeId,
                'linked_entry_id' => $entryId,
                'linked_entry_type' => ChoiceListEntry::class,
                'text' => $entry['label'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    return Xlsform::find($xlsformId);
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

    test('walks the full parent chain regardless of depth, not hardcoded to any fixed number of levels', function () {
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
            'loc1' => '1',
            'loc1_name' => 'North Region',
            'loc1_type' => 'Loc1 name',
            'loc2' => '1',
            'loc2_name' => 'Central District',
            'loc2_type' => 'Loc2 name',
            'loc3' => '1',
            'loc3_name' => 'Green Village',
            'loc3_type' => 'Loc3 name',
        ]);
    });

    test('returns an empty array for a non-existent location id', function () {
        expect(odkFarmEntityService()->buildLocationAttributes(999999))->toBe([]);
    });

    test('resolves loc{n} from the team\'s own Xlsform choice lists, not any hardcoded table', function () {
        $cluster = makeLevel($this->team, 'Cluster');
        $group = makeLevel($this->team, 'Group', $cluster, hasFarms: true);

        $clusterLocation = makeLocation($this->team, $cluster, 'C1', 'Some Cluster');
        $groupLocation = makeLocation($this->team, $group, 'G1', 'Golmadevi Mahila Krishak Samuha', $clusterLocation);

        makeXlsformWithChoiceLists($this->team, [
            'loc1' => [
                ['name' => '7', 'label' => 'Some Cluster', 'cascadeFilter' => null],
            ],
            'loc2' => [
                ['name' => '9', 'label' => 'Golmadevi Mahila Krishak Samuha', 'cascadeFilter' => '7'],
            ],
        ]);

        $attributes = odkFarmEntityService()->buildLocationAttributes($groupLocation->id);

        expect($attributes['loc1'])->toBe('7')
            ->and($attributes['loc2'])->toBe('9');
    });

    test('matches the Xlsform choice list label case-insensitively', function () {
        $group = makeLevel($this->team, 'Group', hasFarms: true);
        $groupLocation = makeLocation($this->team, $group, 'G1', 'golmadevi mahila krishak samuha');

        makeXlsformWithChoiceLists($this->team, [
            'loc1' => [
                ['name' => '9', 'label' => 'Golmadevi Mahila Krishak Samuha', 'cascadeFilter' => null],
            ],
        ]);

        $attributes = odkFarmEntityService()->buildLocationAttributes($groupLocation->id);

        expect($attributes['loc1'])->toBe('9');
    });

    test('generalizes to however many levels a team has, not just 2', function () {
        $district = makeLevel($this->team, 'District');
        $subDistrict = makeLevel($this->team, 'Sub-district', $district);
        $village = makeLevel($this->team, 'Village', $subDistrict, hasFarms: true);

        $districtLocation = makeLocation($this->team, $district, 'D1', 'Kathmandu District');
        $subDistrictLocation = makeLocation($this->team, $subDistrict, 'SD1', 'Kirtipur', $districtLocation);
        $villageLocation = makeLocation($this->team, $village, 'V1', 'Panga', $subDistrictLocation);

        makeXlsformWithChoiceLists($this->team, [
            'loc1' => [['name' => '101', 'label' => 'Kathmandu District', 'cascadeFilter' => null]],
            'loc2' => [['name' => '202', 'label' => 'Kirtipur', 'cascadeFilter' => '101']],
            'loc3' => [['name' => '303', 'label' => 'Panga', 'cascadeFilter' => '202']],
        ]);

        $attributes = odkFarmEntityService()->buildLocationAttributes($villageLocation->id);

        expect($attributes['loc1'])->toBe('101')
            ->and($attributes['loc2'])->toBe('202')
            ->and($attributes['loc3'])->toBe('303');
    });

    test('falls back to the "1" placeholder when no matching Xlsform choice list exists', function () {
        $cluster = makeLevel($this->team, 'Cluster');
        $group = makeLevel($this->team, 'Group', $cluster, hasFarms: true);

        $clusterLocation = makeLocation($this->team, $cluster, 'C1', 'Some Cluster');
        $groupLocation = makeLocation($this->team, $group, 'G1', 'A Group With No Xlsform Yet', $clusterLocation);

        $attributes = odkFarmEntityService()->buildLocationAttributes($groupLocation->id);

        expect($attributes['loc1'])->toBe('1')
            ->and($attributes['loc2'])->toBe('1');
    });

    test('falls back to the "1" placeholder when the label doesn\'t match any choice list entry', function () {
        $group = makeLevel($this->team, 'Group', hasFarms: true);
        $groupLocation = makeLocation($this->team, $group, 'G1', 'Unmatched Group Name');

        makeXlsformWithChoiceLists($this->team, [
            'loc1' => [
                ['name' => '9', 'label' => 'A Totally Different Name', 'cascadeFilter' => null],
            ],
        ]);

        $attributes = odkFarmEntityService()->buildLocationAttributes($groupLocation->id);

        expect($attributes['loc1'])->toBe('1');
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
