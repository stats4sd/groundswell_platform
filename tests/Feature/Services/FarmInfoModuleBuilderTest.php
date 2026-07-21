<?php

use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use App\Services\XlsformModules\FarmInfoModuleBuilder;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

function farmDataset(Team $team): Dataset
{
    return app(OdkFarmEntityService::class)->ensureDataset($team);
}

function localFarmInfoVersion(Team $team): XlsformModuleVersion
{
    return XlsformModuleVersion::where('owner_id', $team->id)->where('name', 'Local farm info')->firstOrFail();
}

beforeEach(function () {
    Http::fake();
    $this->team = Team::factory()->create();
    createLocationModules();
});

it('creates a Local Farm Info module version owned by the team', function () {
    FarmInfoModuleBuilder::populate($this->team);

    $this->assertDatabaseHas('xlsform_module_versions', [
        'owner_id' => $this->team->id,
        'name' => 'Local farm info',
    ]);
});

it('creates one Local Farm Info module version per location XlsformModule', function () {
    // beforeEach already created one; add two more for three location modules in total.
    createLocationModules(2);

    expect(XlsformModule::where('name', 'location')->count())->toBe(3);

    FarmInfoModuleBuilder::populate($this->team);

    $versions = XlsformModuleVersion::where('owner_id', $this->team->id)
        ->where('name', 'Local farm info')
        ->get();

    expect($versions)->toHaveCount(3);
    expect($versions->pluck('xlsform_module_id')->sort()->values()->all())
        ->toBe(XlsformModule::where('name', 'location')->pluck('id')->sort()->values()->all());
});

it('builds the farm picker row with no choice_filter when the team has no location levels', function () {
    FarmInfoModuleBuilder::populate($this->team);

    $idRow = localFarmInfoVersion($this->team)->surveyRows->firstWhere('name', 'id');

    expect($idRow->type)->toBe('select_one_from_file Farm_Summary.csv');
    expect($idRow->required)->toBeTrue();
    expect($idRow->choice_filter)->toBe('');
});

it('filters the farm picker on the deepest location level', function () {
    $region = LocationLevel::create(['owner_id' => $this->team->id, 'name' => 'Region']);
    LocationLevel::create(['owner_id' => $this->team->id, 'parent_id' => $region->id, 'name' => 'District']);

    FarmInfoModuleBuilder::populate($this->team);

    $idRow = localFarmInfoVersion($this->team)->surveyRows->firstWhere('name', 'id');

    expect($idRow->choice_filter)->toBe('loc2=${loc2}');
});

it('builds a calculate row per identifier and property variable, pulling from the selected entity', function () {
    $dataset = farmDataset($this->team);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'certificate_no', 'label' => 'Certificate Number', 'type' => 'string', 'description' => 'identifier']);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'field_size', 'label' => 'Field Size (ha)', 'type' => 'string', 'description' => 'property']);

    FarmInfoModuleBuilder::populate($this->team);

    $rows = localFarmInfoVersion($this->team)->surveyRows;

    $identifierRow = $rows->firstWhere('name', 'certificate_no');
    expect($identifierRow->type)->toBe('calculate');
    expect($identifierRow->calculation)->toBe('instance(\'Farm_Summary\')/root/item[name=${id}]/certificate_no');

    $propertyRow = $rows->firstWhere('name', 'field_size');
    expect($propertyRow->calculation)->toBe('instance(\'Farm_Summary\')/root/item[name=${id}]/field_size');
});

it('builds the farmer_note listing every identifier and property with its label', function () {
    $dataset = farmDataset($this->team);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'certificate_no', 'label' => 'Certificate Number', 'type' => 'string', 'description' => 'identifier']);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'field_size', 'label' => 'Field Size (ha)', 'type' => 'string', 'description' => 'property']);

    FarmInfoModuleBuilder::populate($this->team);

    $note = localFarmInfoVersion($this->team)->surveyRows->firstWhere('name', 'farmer_note');
    expect($note->type)->toBe('note');

    // label:: keys are extracted from `properties` into LanguageString rows by the
    // package's HasLanguageStrings saved() hook, so the label is asserted via defaultLabel.
    $text = $note->defaultLabel->text;

    expect($text)->toContain('Certificate Number: ${certificate_no},');
    expect($text)->toContain('Field Size (ha): ${field_size},');
    expect($text)->toContain('If this is not the correct farm, please go back and reselect.');
});

it('removes a stale calculate row when a variable is removed', function () {
    $dataset = farmDataset($this->team);
    $variable = DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'certificate_no', 'label' => 'Certificate Number', 'type' => 'string', 'description' => 'identifier']);

    FarmInfoModuleBuilder::populate($this->team);
    $variable->delete();
    FarmInfoModuleBuilder::populate($this->team);

    expect(localFarmInfoVersion($this->team)->surveyRows->firstWhere('name', 'farm_certificate_no'))->toBeNull();
});
