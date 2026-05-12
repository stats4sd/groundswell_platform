<?php

use function Pest\Livewire\livewire;

use Stats4sd\FilamentTeamManagement\Filament\Program\Resources\ProgramResource\Pages\CreateProgram;
use Stats4sd\FilamentTeamManagement\Filament\Program\Resources\ProgramResource\Pages\EditProgram;
use Stats4sd\FilamentTeamManagement\Filament\Program\Resources\ProgramResource\Pages\ListPrograms;
use Stats4sd\FilamentTeamManagement\Models\Program;

describe('Program panel CRUD — Program', function () {

    //££
    return;
    //££

    beforeEach(function () {
        $this->program = Program::create(['name' => 'Test Program']);
        $this->programAdmin = createProgramAdmin($this->program);
        $this->actingAs($this->programAdmin);
        withProgramTenant($this->program);
    });

    test('program list shows current program', function () {
        livewire(ListPrograms::class)
            ->assertCanSeeTableRecords([$this->program]);
    });

    test('program create page loads', function () {
        $this->get("/program/{$this->program->id}/programs/create")->assertOk();
    });

    test('can create program', function () {
        livewire(CreateProgram::class)
            ->fillForm(['name' => 'New Program'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('programs', ['name' => 'New Program']);
    });

    test('create program requires name', function () {
        livewire(CreateProgram::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    });

    test('program edit page loads', function () {
        $this->get("/program/{$this->program->id}/programs/{$this->program->id}/edit")->assertOk();
    });

    test('can edit program', function () {
        livewire(EditProgram::class, ['record' => $this->program->id])
            ->fillForm(['name' => 'Updated Program'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('programs', ['id' => $this->program->id, 'name' => 'Updated Program']);
    });

});
