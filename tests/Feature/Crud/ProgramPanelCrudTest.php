<?php

use App\Filament\Admin\Resources\ProgramResource\Pages\ListPrograms;
use App\Filament\Program\ManageProgram\ManageProgram;
use Stats4sd\FilamentTeamManagement\Models\Program;

use function Pest\Livewire\livewire;

// The Program panel no longer exposes a ProgramResource. A program is managed via the
// ManageProgram tenant-profile page; programs themselves are created by admins.
describe('Program panel — manage program', function () {

    beforeEach(function () {
        $this->program = Program::create(['name' => 'Test Program']);
        $this->programAdmin = createProgramAdmin($this->program);
        $this->actingAs($this->programAdmin);
        withProgramTenant($this->program);
    });

    test('manage program page loads', function () {
        livewire(ManageProgram::class)
            ->assertSuccessful();
    });

    test('can edit program name', function () {
        livewire(ManageProgram::class)
            ->fillForm(['name' => 'Updated Program'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('programs', ['id' => $this->program->id, 'name' => 'Updated Program']);
    });
});

// Only admin users can create programs (done from the Admin panel ProgramResource).
describe('Program creation — Admin user', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
        $this->actingAs($this->superAdmin);
        withAdminPanel();
    });

    test('can create program', function () {
        livewire(ListPrograms::class)
            ->callAction('create', data: ['name' => 'New Program'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('programs', ['name' => 'New Program']);
    });

    test('create program requires name', function () {
        livewire(ListPrograms::class)
            ->callAction('create', data: ['name' => ''])
            ->assertHasActionErrors(['name' => 'required']);
    });
});
