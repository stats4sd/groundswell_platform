<?php

use App\Filament\Admin\Resources\DomainResource\Pages\CreateDomain;
use App\Filament\Admin\Resources\DomainResource\Pages\EditDomain;
use App\Filament\Admin\Resources\DomainResource\Pages\ListDomains;
use App\Filament\Admin\Resources\GlobalIndicatorResource\Pages\CreateGlobalIndicator;
use App\Filament\Admin\Resources\GlobalIndicatorResource\Pages\EditGlobalIndicator;
use App\Filament\Admin\Resources\GlobalIndicatorResource\Pages\ListGlobalIndicators;
use App\Filament\Admin\Resources\TeamResource\Pages\CreateTeam;
use App\Filament\Admin\Resources\TeamResource\Pages\ListTeams;
use App\Filament\Admin\Resources\ThemeResource\Pages\CreateTheme;
use App\Filament\Admin\Resources\ThemeResource\Pages\EditTheme;
use App\Filament\Admin\Resources\ThemeResource\Pages\ListThemes;
use App\Models\Holpa\Domain;
use App\Models\Holpa\GlobalIndicator;
use App\Models\Holpa\Theme;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\DatasetResource\Pages\CreateDataset;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleResource\Pages\ManageXlsformModule;
use Stats4sd\FilamentOdkLink\Filament\OdkAdmin\Resources\XlsformModuleVersionResource\Pages\ManageXlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\UserResource\Pages\EditUser;
use Stats4sd\FilamentTeamManagement\Filament\Admin\Resources\UserResource\Pages\ListUsers;

use function Pest\Livewire\livewire;

describe('Admin panel CRUD — Domain', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        $user = new User();

        withAdminPanel();
    });

    test('domain list shows existing records', function () {
        $domain = Domain::create(['name' => 'Existing Domain']);

        livewire(ListDomains::class)
            ->assertCanSeeTableRecords([$domain]);
    });

    test('domain create page loads', function () {
        $this->get('/admin/domains/create')->assertOk();
    });

    test('can create domain', function () {
        livewire(CreateDomain::class)
            ->fillForm(['name' => 'New Domain'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('domains', ['name' => 'New Domain']);
    });

    test('create domain requires name', function () {
        livewire(CreateDomain::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    });

    test('domain edit page loads', function () {
        $domain = Domain::create(['name' => 'Edit Target']);
        $this->get("/admin/domains/{$domain->id}/edit")->assertOk();
    });

    test('can edit domain', function () {
        $domain = Domain::create(['name' => 'Original Domain']);

        livewire(EditDomain::class, ['record' => $domain->id])
            ->fillForm(['name' => 'Updated Domain'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('domains', ['id' => $domain->id, 'name' => 'Updated Domain']);
    });

    test('can bulk delete domain', function () {
        $domain = Domain::create(['name' => 'Delete Me']);

        livewire(ListDomains::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$domain]);

        $this->assertDatabaseMissing('domains', ['id' => $domain->id]);
    });

});

// ---------------------------------------------------------------------------

describe('Admin panel CRUD — Theme', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        withAdminPanel();
    });

    test('theme list shows existing records', function () {
        $theme = Theme::create(['name' => 'Unique Visible Theme XYZ', 'module' => 'Test']);

        livewire(ListThemes::class)
            ->searchTable('Unique Visible Theme XYZ')
            ->assertCanSeeTableRecords([$theme]);
    });

    test('theme create page loads', function () {
        $this->get('/admin/themes/create')->assertOk();
    });

    test('can create theme', function () {
        livewire(CreateTheme::class)
            ->fillForm(['name' => 'New Theme', 'module' => 'TestModule', 'domain_id' => Domain::first()->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('themes', ['name' => 'New Theme']);
    });

    test('create theme requires name', function () {
        livewire(CreateTheme::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    });

    test('theme edit page loads', function () {
        $theme = Theme::first();
        $this->get("/admin/themes/{$theme->id}/edit")->assertOk();
    });

    test('can edit theme', function () {
        $theme = Theme::create(['name' => 'Original Theme', 'module' => 'Mod']);

        livewire(EditTheme::class, ['record' => $theme->id])
            ->fillForm(['name' => 'Updated Theme'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('themes', ['id' => $theme->id, 'name' => 'Updated Theme']);
    });

    test('can bulk delete theme', function () {
        $theme = Theme::create(['name' => 'Delete Theme', 'module' => 'Mod']);

        livewire(ListThemes::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$theme]);

        $this->assertDatabaseMissing('themes', ['id' => $theme->id]);
    });

});

// ---------------------------------------------------------------------------

describe('Admin panel CRUD — GlobalIndicator', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);

        $this->theme = Theme::first();
        withAdminPanel();
    });

    test('global indicator list shows existing records', function () {
        livewire(ListGlobalIndicators::class)
            ->assertSuccessful();
    });

    test('global indicator create page loads', function () {
        $this->get('/admin/global-indicators/create')->assertOk();
    });

    test('can create global indicator', function () {
        livewire(CreateGlobalIndicator::class)
            ->fillForm(['name' => 'New Indicator', 'theme_id' => $this->theme->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('global_indicators', ['name' => 'New Indicator']);
    });

    test('create global indicator requires theme', function () {
        livewire(CreateGlobalIndicator::class)
            ->fillForm(['name' => 'No Theme Indicator', 'theme_id' => null])
            ->call('create')
            ->assertHasFormErrors(['theme_id' => 'required']);
    });

    test('global indicator edit page loads', function () {
        $indicator = GlobalIndicator::factory()->create();
        $this->get("/admin/global-indicators/{$indicator->id}/edit")->assertOk();
    });

    test('can edit global indicator', function () {
        $indicator = GlobalIndicator::factory()->create();

        livewire(EditGlobalIndicator::class, ['record' => $indicator->id])
            ->fillForm(['name' => 'Updated Indicator'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('global_indicators', ['id' => $indicator->id, 'name' => 'Updated Indicator']);
    });

    test('can bulk delete global indicator', function () {
        $indicator = GlobalIndicator::factory()->create();

        livewire(ListGlobalIndicators::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$indicator]);

        $this->assertDatabaseMissing('global_indicators', ['id' => $indicator->id]);
    });

});

// ---------------------------------------------------------------------------

describe('Admin panel CRUD — Program (list only in admin panel)', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        withAdminPanel();
    });

    test('program list page loads', function () {
        $this->get('/admin/programs')->assertOk();
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

describe('Admin panel CRUD — User', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        withAdminPanel();
    });

    test('user list shows existing users', function () {
        livewire(ListUsers::class)->assertSuccessful();
    });

    test('user create page loads', function () {
        $this->get('/admin/users/create')->assertOk();
    });

    test('can create user', function () {
        livewire(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'testuser@example.com',
                'password' => 'password123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'testuser@example.com']);
    });

    test('create user requires name', function () {
        livewire(CreateUser::class)
            ->fillForm(['name' => '', 'email' => 'x@example.com', 'password' => 'password123'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    });

    test('create user requires email', function () {
        livewire(CreateUser::class)
            ->fillForm(['name' => 'Test', 'email' => '', 'password' => 'password123'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'required']);
    });

    test('user edit page loads', function () {
        $user = User::factory()->create();
        $this->get("/admin/users/{$user->id}/edit")->assertOk();
    });

    test('can edit user name', function () {
        $user = User::factory()->create();

        livewire(EditUser::class, ['record' => $user->id])
            ->fillForm(['name' => 'Edited Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Edited Name']);
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
            ->fillForm(['name' => 'Test Dataset'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('datasets', ['name' => 'Test Dataset']);
    });

    test('dataset edit page loads', function () {
        $dataset = Dataset::forceCreate(['name' => 'Editable Dataset', 'primary_key' => 'id']);
        $this->get("/admin/datasets/{$dataset->id}/edit")->assertOk();
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
        $this->xlsformTemplate = \Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate::withoutEvents(
            fn () => \Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate::forceCreate(['title' => 'Test Template'])
        );
    });

    test('xlsform module list page loads', function () {
        $this->get('/admin/xlsform-modules')->assertOk();
    });

    test('can create xlsform module', function () {
        livewire(ManageXlsformModule::class)
            ->callAction(CreateAction::class, data: [
                'xlsform_template_id' => $this->xlsformTemplate->id,
                'label' => 'Test Module',
                'name' => 'test_module',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('xlsform_modules', ['name' => 'test_module']);
    });

    test('create xlsform module requires name', function () {
        livewire(ManageXlsformModule::class)
            ->callAction(CreateAction::class, data: [
                'xlsform_template_id' => $this->xlsformTemplate->id,
                'label' => 'Missing Name',
                'name' => '',
            ])
            ->assertHasActionErrors(['name' => 'required']);
    });

    test('can delete xlsform module', function () {
        $module = XlsformModule::forceCreate([
            'xlsform_template_id' => $this->xlsformTemplate->id,
            'label' => 'Delete Me',
            'name' => 'delete_me',
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
        $xlsformTemplate = \Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate::withoutEvents(
            fn () => \Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate::forceCreate(['title' => 'Test Template'])
        );
        $this->xlsformModule = XlsformModule::forceCreate([
            'xlsform_template_id' => $xlsformTemplate->id,
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

});
