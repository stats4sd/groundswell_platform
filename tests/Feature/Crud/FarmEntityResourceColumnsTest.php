<?php

use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages\ListFarmEntities;
use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;

use function Pest\Livewire\livewire;

// Confirms the farm-list dynamic property columns exclude the location cascade attributes
// (loc{n}/loc{n}_name/loc{n}_type) and GPS - they are owned by the dedicated Location column
// and the GPS form fields respectively. See code review F5
// (docs/code-reviews/2026-07-19-map-location-to-entity-list-attribute.md).

beforeEach(function () {
    Http::fake([
        '*/sessions' => Http::response(['token' => 'fake-token'], 200),
        // No local OdkDataset row is created, so refreshFromCentral()'s findOdkDataset()
        // self-heal hits Central; a 404 makes it return [] without needing a feed fake. The
        // columns are driven purely by DatasetVariable rows, not by the live feed.
        '*/datasets/Farm_Summary' => Http::response([], 404),
    ]);

    $this->team = Team::factory()->create();
    OdkProject::create(['id' => 1, 'owner_type' => Team::class, 'owner_id' => $this->team->id, 'name' => 'Project 1']);

    $this->user = createAppUser($this->team);
    $this->actingAs($this->user);

    $dataset = app(OdkFarmEntityService::class)->ensureDataset($this->team);

    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'household_head', 'label' => 'Household head', 'type' => 'string', 'description' => 'property']);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'farm_name', 'label' => 'Farm name', 'type' => 'string', 'description' => 'identifier']);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'loc1', 'label' => 'loc1', 'type' => 'string', 'description' => 'loc']);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'loc1_name', 'label' => 'loc1_name', 'type' => 'string', 'description' => 'loc']);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'loc1_type', 'label' => 'loc1_type', 'type' => 'string', 'description' => 'loc']);
    DatasetVariable::create(['dataset_id' => $dataset->id, 'name' => 'geometry', 'label' => 'geometry', 'type' => 'string', 'description' => 'loc']);
});

test('farm list shows identifier/property columns but not the location cascade or GPS columns', function () {
    withAppTenant($this->team);

    livewire(ListFarmEntities::class)
        ->assertTableColumnExists('property_household_head')
        ->assertTableColumnExists('property_farm_name')
        ->assertTableColumnDoesNotExist('property_loc1')
        ->assertTableColumnDoesNotExist('property_loc1_name')
        ->assertTableColumnDoesNotExist('property_loc1_type')
        ->assertTableColumnDoesNotExist('property_geometry');
});
