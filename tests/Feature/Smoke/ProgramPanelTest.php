<?php

use App\Filament\Program\ManageProgram\ManageProgramMembers;
use App\Filament\Program\ManageProgram\ManageProgramProjects;
use Livewire\Livewire;
use Stats4sd\FilamentTeamManagement\Filament\Program\Pages\ManageProgram\ManageProgramInvites;
use Stats4sd\FilamentTeamManagement\Models\Program;

describe('Program panel routes load for Program Admin', function () {

    beforeEach(function () {
        $this->program = Program::create(['name' => 'Smoke Test Program']);
        $this->programAdmin = createProgramAdmin($this->program);
        $this->actingAs($this->programAdmin);
    });

    test('program panel dashboard loads', function () {
        $this->get("/program/{$this->program->id}")
            ->assertOk();
    });

    test('manage program profile page loads', function () {
        $this->get("/program/{$this->program->id}/profile")
            ->assertOk();
    });

    // The ManageProgram tenant-profile page embeds the members/projects/invites
    // relation managers as Livewire components. Smoke-test that each renders.

    test('manage program projects component renders', function () {
        withProgramTenant($this->program);

        Livewire::test(ManageProgramProjects::class)
            ->assertOk();
    });

    test('manage program members component renders', function () {
        withProgramTenant($this->program);

        Livewire::test(ManageProgramMembers::class)
            ->assertOk();
    });

    test('manage program invites component renders', function () {
        withProgramTenant($this->program);

        Livewire::test(ManageProgramInvites::class)
            ->assertOk();
    });

});
