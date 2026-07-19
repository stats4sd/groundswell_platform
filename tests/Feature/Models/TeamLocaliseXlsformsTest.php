<?php

use App\Models\Team;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();
    $this->team = Team::factory()->create();
    createLocationModules();
});

it('populates both Local Locations and Local Farm Info when has_updated_locations is true, then resets the flag', function () {
    $this->team->update(['has_updated_locations' => true]);

    $this->team->localiseXlsforms();

    $this->assertDatabaseHas('xlsform_module_versions', ['owner_id' => $this->team->id, 'name' => 'Local locations']);
    $this->assertDatabaseHas('xlsform_module_versions', ['owner_id' => $this->team->id, 'name' => 'Local farm info']);
    expect($this->team->fresh()->has_updated_locations)->toBeFalse();
});

it('does nothing when has_updated_locations is false', function () {
    $this->team->update(['has_updated_locations' => false]);

    $this->team->localiseXlsforms();

    $this->assertDatabaseMissing('xlsform_module_versions', ['owner_id' => $this->team->id, 'name' => 'Local locations']);
    $this->assertDatabaseMissing('xlsform_module_versions', ['owner_id' => $this->team->id, 'name' => 'Local farm info']);
});
