<?php

use Stats4sd\FilamentTeamManagement\Models\Program;

describe('Program panel routes load for Program Admin', function () {

    beforeEach(function () {
        $this->program = Program::create(['name' => 'Smoke Test Program']);
        $this->programAdmin = createProgramAdmin($this->program);
    });

    test('program panel dashboard loads', function () {
        $this->actingAs($this->programAdmin)
            ->get("/program/{$this->program->id}")
            ->assertOk();
    });

    test('programs list loads', function () {
        $this->actingAs($this->programAdmin)
            ->get("/program/{$this->program->id}/programs")
            ->assertOk();
    });

});
