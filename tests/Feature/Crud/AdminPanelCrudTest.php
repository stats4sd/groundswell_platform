<?php

use App\Filament\Admin\Resources\ProgramResource\Pages\ListPrograms;
use App\Filament\Admin\Resources\TeamResource\Pages\CreateTeam;
use App\Filament\Admin\Resources\TeamResource\Pages\ListTeams;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Pages\CreateDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\Datasets\Pages\EditDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModules\Pages\ManageXlsformModule;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersions\Pages\ManageXlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentTeamManagement\Models\Program;

use function Pest\Livewire\livewire;

describe('Admin panel CRUD — Program (list only in admin panel)', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        withAdminPanel();
    });

    test('program list page loads', function () {
        $this->get('/admin/programs')->assertOk();
    });

    test('program list links to program panel', function () {
        $program = Program::create(['name' => 'Linked Program']);

        livewire(ListPrograms::class)
            ->assertSeeHtml(url('program/'.$program->id));
    });

});

// ---------------------------------------------------------------------------

describe('Admin panel CRUD — Team', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        withAdminPanel();
    });

    test('team list shows existing teams', function () {
        livewire(ListTeams::class)->assertSuccessful();
    });

    test('team create page loads', function () {
        $this->get('/admin/teams/create')->assertOk();
    });

    test('can create team', function () {
        Http::fake();

        livewire(CreateTeam::class)
            ->fillForm(['name' => 'New Test Team'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('teams', ['name' => 'New Test Team']);
    });

    test('team view page loads', function () {
        $team = Team::withoutEvents(fn () => Team::factory()->create());
        $this->get("/admin/teams/{$team->id}")->assertOk();
    });

    test('team edit page loads', function () {
        $team = Team::withoutEvents(fn () => Team::factory()->create());
        $this->get("/admin/teams/{$team->id}/edit")->assertOk();
    });

});

// ---------------------------------------------------------------------------

// The package no longer ships full-page Create/Edit user pages — users are created
// via the "invite users" action on the list page and managed inline. The Admin
// UserResource exposes only the index (list) page.
describe('Admin panel CRUD — User', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        withAdminPanel();
    });

    test('user list shows existing users', function () {
        livewire(ListUsers::class)->assertSuccessful();
    });

    test('invite users action is available on the list page', function () {
        livewire(ListUsers::class)
            ->assertActionExists('invite users');
    });

});

// ---------------------------------------------------------------------------

describe('Admin panel CRUD — Dataset', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        withAdminPanel();
    });

    test('dataset list page loads', function () {
        $this->get('/admin/datasets')->assertOk();
    });

    test('dataset create page loads', function () {
        $this->get('/admin/datasets/create')->assertOk();
    });

    test('can create dataset', function () {
        livewire(CreateDataset::class)
            ->fillForm(['name' => 'Test Dataset', 'description' => 'Test description'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('datasets', ['name' => 'Test Dataset']);
    });

    test('create dataset requires name', function () {
        livewire(CreateDataset::class)
            ->fillForm(['name' => '', 'description' => 'Test description'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    });

    test('dataset edit page loads', function () {
        $dataset = Dataset::forceCreate(['name' => 'Editable Dataset', 'primary_key' => 'id']);
        $this->get("/admin/datasets/{$dataset->id}/edit")->assertOk();
    });

    test('can edit dataset', function () {
        $dataset = Dataset::forceCreate(['name' => 'Editable Dataset', 'primary_key' => 'id']);

        livewire(EditDataset::class, ['record' => $dataset->id])
            ->fillForm(['name' => 'Updated Dataset', 'description' => 'Updated description'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('datasets', ['id' => $dataset->id, 'name' => 'Updated Dataset']);
    });

});

// ---------------------------------------------------------------------------

describe('Admin panel CRUD — XlsformTemplate', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        withAdminPanel();
    });

    test('xlsform template list page loads', function () {
        $this->get('/admin/xlsform-templates')->assertOk();
    });

    test('xlsform template create page loads', function () {
        $this->get('/admin/xlsform-templates/create')->assertOk();
    });

});

// ---------------------------------------------------------------------------

describe('Admin panel CRUD — XlsformModule', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        withAdminPanel();
        $this->xlsformTemplate = XlsformTemplate::withoutEvents(
            fn () => XlsformTemplate::forceCreate(['title' => 'Test Template'])
        );
    });

    test('xlsform module list page loads', function () {
        $this->get('/admin/xlsform-modules')->assertOk();
    });

    test('can delete xlsform module', function () {
        $module = XlsformModule::forceCreate([
            'xlsform_template_id' => $this->xlsformTemplate->id,
            'label' => 'Delete Module',
            'name' => 'delete_module',
        ]);

        livewire(ManageXlsformModule::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$module]);

        $this->assertDatabaseMissing('xlsform_modules', ['id' => $module->id]);
    });

});

// ---------------------------------------------------------------------------

describe('Admin panel CRUD — XlsformModuleVersion', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        withAdminPanel();
        $this->xlsformTemplate = XlsformTemplate::withoutEvents(
            fn () => XlsformTemplate::forceCreate(['title' => 'Test Template'])
        );
        $this->xlsformModule = XlsformModule::forceCreate([
            'xlsform_template_id' => $this->xlsformTemplate->id,
            'label' => 'Test Module',
            'name' => 'test_module',
        ]);
    });

    test('xlsform module version list page loads', function () {
        $this->get('/admin/xlsform-module-versions')->assertOk();
    });

    test('can create default xlsform module version', function () {
        livewire(ManageXlsformModuleVersion::class)
            ->callAction(CreateAction::class, data: [
                'xlsform_module_id' => $this->xlsformModule->id,
                'name' => 'v1',
                'is_default' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('xlsform_module_versions', [
            'xlsform_module_id' => $this->xlsformModule->id,
            'name' => 'v1',
        ]);
    });

    test('create xlsform module requires name', function () {
        livewire(ManageXlsformModuleVersion::class)
            ->callAction(CreateAction::class, data: [
                'xlsform_template_id' => $this->xlsformTemplate->id,
                'label' => 'Missing Name',
                'name' => '',
            ])
            ->assertHasActionErrors(['name' => 'required']);
    });

    test('can delete xlsform module version', function () {
        $version = XlsformModuleVersion::withoutEvents(fn () => XlsformModuleVersion::forceCreate([
            'xlsform_module_id' => $this->xlsformModule->id,
            'name' => 'delete_me',
        ]));

        livewire(ManageXlsformModuleVersion::class)
            ->callTableAction(DeleteAction::class, $version);

        $this->assertDatabaseMissing('xlsform_module_versions', ['id' => $version->id]);
    });

});
