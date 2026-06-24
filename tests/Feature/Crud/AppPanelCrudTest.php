<?php

use function Pest\Livewire\livewire;

use App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\Pages\CreateLocationLevel;
use App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\Pages\ListLocationLevels;
use App\Filament\App\Resources\TeamResource\Pages\CreateTeam;
use App\Filament\App\Resources\TeamResource\Pages\EditTeam;
use App\Filament\App\Resources\TeamResource\Pages\ListTeams;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Support\Facades\Http;

describe('App panel CRUD — Team', function () {

    beforeEach(function () {
        Http::fake();
        $this->team = Team::factory()->create();
        $this->user = createAppUser($this->team);
        $this->actingAs($this->user);
    });

    test('team list shows current team', function () {
        withAppTenant($this->team);

        livewire(ListTeams::class)
            ->assertCanSeeTableRecords([$this->team]);
    });

    test('team edit page loads', function () {
        $this->get("/app/{$this->team->id}/teams/{$this->team->id}/edit")->assertOk();
    });

    test('can edit team via app panel', function () {
        withAppTenant($this->team);

        livewire(EditTeam::class, ['record' => $this->team->id])
            ->fillForm(['name' => 'Renamed Team'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teams', ['id' => $this->team->id, 'name' => 'Renamed Team']);
    });

});

// ---------------------------------------------------------------------------

describe('App panel CRUD — LocationLevel', function () {

    beforeEach(function () {
        Http::fake();
        $this->team = Team::factory()->create();
        $this->user = createAppUser($this->team);
        $this->actingAs($this->user);
    });

    test('location level list page loads', function () {
        $this->get("/app/{$this->team->id}/location-levels/location-levels")->assertOk();
    });

    test('can create location level via table action', function () {
        withAppTenant($this->team);

        livewire(ListLocationLevels::class)
            ->callTableAction(\Filament\Tables\Actions\CreateAction::class, data: [
                'name'     => 'Region',
                'owner_id' => $this->team->id,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('location_levels', ['name' => 'Region', 'owner_id' => $this->team->id]);
    });

    test('location level view page loads', function () {
        withAppTenant($this->team);
        $level = new LocationLevel();
        $level->name = 'Test Level';
        $level->owner_id = $this->team->id;
        $level->save();

        $this->get("/app/{$this->team->id}/location-levels/location-levels/{$level->slug}")->assertOk();
    });

    test('can bulk delete location level', function () {
        withAppTenant($this->team);
        $level = new LocationLevel();
        $level->name = 'Delete Level';
        $level->owner_id = $this->team->id;
        $level->save();

        livewire(ListLocationLevels::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$level]);

        $this->assertDatabaseMissing('location_levels', ['id' => $level->id]);
    });

});

// ---------------------------------------------------------------------------

describe('App panel CRUD — Farm', function () {

    beforeEach(function () {
        Http::fake();
        $this->team = Team::factory()->create();
        $this->user = createAppUser($this->team);
        $this->actingAs($this->user);
    });

    test('farm list page loads', function () {
        $this->get("/app/{$this->team->id}/location-levels/farms")->assertOk();
    });

});

// ---------------------------------------------------------------------------

describe('App panel CRUD — ChoiceListEntry', function () {

    beforeEach(function () {
        Http::fake();
        $this->team = Team::factory()->create();

        // ChoiceList requires the full parent chain: Template → Module → ModuleVersion
        $xlsformTemplate = \Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate::withoutEvents(
            fn () => \Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate::forceCreate(['title' => 'Test Template'])
        );
        $xlsformModule = \Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule::forceCreate([
            'xlsform_template_id' => $xlsformTemplate->id,
            'label' => 'Test Module',
            'name'  => 'test_module',
        ]);
        $xlsformModuleVersion = \Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion::forceCreate([
            'xlsform_module_id' => $xlsformModule->id,
            'name'              => 'v1',
            'is_default'        => true,
        ]);
        \Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList::forceCreate([
            'xlsform_module_version_id' => $xlsformModuleVersion->id,
            'list_name'                 => 'smoke_test_list',
            'is_localisable'            => true,
            'has_custom_handling'       => false,
        ]);

        $this->user = createAppUser($this->team);
        $this->actingAs($this->user);
    });

    test('choice list entry list page loads', function () {
        $this->get("/app/{$this->team->id}/localisations/choice-list-entries")->assertOk();
    });

});


describe('App Panel CRUD - Create New Team', function() {

    beforeEach(function () {
        Http::fake();
        $this->team = Team::factory()->create();
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
    });


    test('team create page loads', function () {
        $this->get("/app/{$this->team->id}/teams/create")->assertOk();
    });

    test('can create team via app panel', function () {
        Http::fake();
        withAppTenant($this->team);

        livewire(CreateTeam::class)
            ->fillForm(['name' => 'Brand New Team'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teams', ['name' => 'Brand New Team']);
    });

    test('create team requires name', function () {
        withAppTenant($this->team);

        livewire(CreateTeam::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    });

});