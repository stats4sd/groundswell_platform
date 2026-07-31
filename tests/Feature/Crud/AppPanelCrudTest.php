<?php

use App\Filament\App\Clusters\Localisations\Resources\ChoiceListEntryResource\Pages\ListChoiceListEntries;
use App\Filament\App\Clusters\LocationLevels\Resources\FarmEntityResource\Pages\ListFarmEntities;
use App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\Pages\ListLocationLevels;
use App\Filament\App\Resources\TeamResource\Pages\CreateTeam;
use App\Filament\App\Resources\TeamResource\Pages\EditTeam;
use App\Filament\App\Resources\TeamResource\Pages\ListTeams;
use App\Models\SampleFrame\FarmEntity;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

use function Pest\Livewire\livewire;

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
            ->callTableAction(CreateAction::class, data: [
                'name' => 'Region',
                'owner_id' => $this->team->id,
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('location_levels', ['name' => 'Region', 'owner_id' => $this->team->id]);
    });

    test('location level view page loads', function () {
        withAppTenant($this->team);
        $level = new LocationLevel;
        $level->name = 'Test Level';
        $level->owner_id = $this->team->id;
        $level->save();

        $this->get("/app/{$this->team->id}/location-levels/location-levels/{$level->slug}")->assertOk();
    });

    test('can bulk delete location level', function () {
        withAppTenant($this->team);
        $level = new LocationLevel;
        $level->name = 'Delete Level';
        $level->owner_id = $this->team->id;
        $level->save();

        livewire(ListLocationLevels::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$level]);

        $this->assertDatabaseMissing('location_levels', ['id' => $level->id]);
    });

});

// ---------------------------------------------------------------------------

describe('App panel CRUD — FarmEntity', function () {

    beforeEach(function () {
        Http::fake([
            '*/sessions' => Http::response(['token' => 'fake-token'], 200),
            // ListFarmEntities::mount() self-heals the local OdkDataset row from Central;
            // a 404 short-circuits it to an empty live feed without needing an OData fake.
            '*/datasets/Farm_Summary' => Http::response([], 404),
            '*/entities/*' => Http::response([], 200),
        ]);

        $this->team = Team::factory()->create();
        OdkProject::create(['id' => 1, 'owner_type' => Team::class, 'owner_id' => $this->team->id, 'name' => 'Project 1']);

        $this->user = createAppUser($this->team);
        $this->actingAs($this->user);
    });

    test('farm list page loads', function () {
        $this->get("/app/{$this->team->id}/location-levels/farms")->assertOk();
    });

    test('can delete farm entity via table action', function () {
        withAppTenant($this->team);

        $level = new LocationLevel;
        $level->name = 'Village';
        $level->owner_id = $this->team->id;
        $level->has_farms = true;
        $level->save();

        $location = Location::create([
            'owner_id' => $this->team->id,
            'location_level_id' => $level->id,
            'name' => 'Test Village',
            'code' => 'tv1',
        ]);

        $farmEntity = FarmEntity::create([
            'owner_id' => $this->team->id,
            'location_id' => $location->id,
            'team_code' => 'farm-001',
            'odk_uuid' => 'uuid-farm-001',
            'odk_version' => 1,
        ]);

        livewire(ListFarmEntities::class)
            ->callTableAction(DeleteAction::class, $farmEntity);

        $this->assertSoftDeleted('farm_entities', ['id' => $farmEntity->id]);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), 'datasets/Farm_Summary/entities/uuid-farm-001'));
    });

});

// ---------------------------------------------------------------------------

describe('App panel CRUD — ChoiceListEntry', function () {

    beforeEach(function () {
        Http::fake();
        $this->team = Team::factory()->create();

        // ChoiceList requires the full parent chain: Template → Module → ModuleVersion
        $xlsformTemplate = XlsformTemplate::withoutEvents(
            fn () => XlsformTemplate::forceCreate(['title' => 'Test Template'])
        );
        $xlsformModule = XlsformModule::forceCreate([
            'xlsform_template_id' => $xlsformTemplate->id,
            'label' => 'Test Module',
            'name' => 'test_module',
        ]);
        $xlsformModuleVersion = XlsformModuleVersion::forceCreate([
            'xlsform_module_id' => $xlsformModule->id,
            'name' => 'v1',
            'is_default' => true,
        ]);
        $this->choiceList = ChoiceList::forceCreate([
            'xlsform_module_version_id' => $xlsformModuleVersion->id,
            'list_name' => 'smoke_test_list',
            'is_localisable' => true,
            'has_custom_handling' => false,
        ]);

        $this->user = createAppUser($this->team);
        $this->actingAs($this->user);
    });

    test('choice list entry list page loads', function () {
        $this->get("/app/{$this->team->id}/localisations/choice-list-entries")->assertOk();
    });

    test('can create choice list entry via table header action', function () {
        withAppTenant($this->team);

        livewire(ListChoiceListEntries::class)
            ->callTableAction(CreateAction::class, data: [
                'name' => 'new_entry',
                'languageStrings' => [
                    [
                        'locale_id' => $this->team->locales()->value('locales.id'),
                        'language_string_type_id' => LanguageStringType::where('name', 'label')->firstOrFail()->id,
                        'text' => 'New Entry',
                    ],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('choice_list_entries', [
            'choice_list_id' => $this->choiceList->id,
            'owner_id' => $this->team->id,
            'name' => 'new_entry',
        ]);
    });

    test('can edit choice list entry via table action', function () {
        withAppTenant($this->team);

        $entry = ChoiceListEntry::create([
            'choice_list_id' => $this->choiceList->id,
            'owner_id' => $this->team->id,
            'name' => 'editable_entry',
        ]);
        $entry->languageStrings()->create([
            'locale_id' => $this->team->locales()->value('locales.id'),
            'language_string_type_id' => LanguageStringType::where('name', 'label')->firstOrFail()->id,
            'text' => 'Editable Entry',
        ]);

        livewire(ListChoiceListEntries::class)
            ->callTableAction(EditAction::class, $entry, data: ['name' => 'updated_entry'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('choice_list_entries', ['id' => $entry->id, 'name' => 'updated_entry']);
    });

    test('can delete choice list entry via table action', function () {
        withAppTenant($this->team);

        $entry = ChoiceListEntry::create([
            'choice_list_id' => $this->choiceList->id,
            'owner_id' => $this->team->id,
            'name' => 'deletable_entry',
        ]);

        livewire(ListChoiceListEntries::class)
            ->callTableAction(DeleteAction::class, $entry);

        $this->assertDatabaseMissing('choice_list_entries', ['id' => $entry->id]);
    });

});

describe('App Panel CRUD - Create New Team', function () {

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
