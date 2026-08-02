<?php

use App\Models\Team;
use Illuminate\Support\Facades\Http;

describe('App panel routes load for authenticated team member', function () {

    beforeEach(function () {
        Http::fake();
        $this->team = Team::factory()->create();
        $this->user = createAppUser($this->team);
    });

    // Custom pages

    test('survey dashboard loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/survey-dashboard")
            ->assertOk();
    });

    test('set up survey loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/set-up-survey")
            ->assertOk();
    });

    test('monitor data collection loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/monitor-data-collection")
            ->assertOk();
    });

    test('data analysis index loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/data-analysis-index")
            ->assertOk();
    });

    test('context questions loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/context-questions")
            ->assertOk();
    });

    test('survey locations index loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/survey-locations-index")
            ->assertOk();
    });

    test('survey languages index loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/survey-languages-index")
            ->assertOk();
    });

    test('survey country loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/survey-country")
            ->assertOk();
    });

    test('survey translations loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/survey-translations")
            ->assertOk();
    });

    test('optional modules loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/optional-modules")
            ->assertOk();
    });

    test('pilot index loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/pilot-index")
            ->assertOk();
    });

    test('place adaptations index loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/place-adaptations-index")
            ->assertOk();
    });

    test('initial pilot loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/initial-pilot")
            ->assertOk();
    });

    // Resources

    test('submissions list loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/submissions")
            ->assertOk();
    });

    test('teams list loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/teams")
            ->assertOk();
    });

    test('location levels list loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/location-levels/location-levels")
            ->assertOk();
    });

    // The team has no OdkProject, so ListFarmEntities::mount()'s refreshFromCentral()
    // short-circuits to an empty live feed without reaching ODK Central at all.
    test('farms list loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/location-levels/farms")
            ->assertOk();
    });

    test('past imports list loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/location-levels/imports")
            ->assertOk();
    });

    test('choice list entries list loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/localisations/choice-list-entries")
            ->assertOk();
    });

    // HddsHints (/app/{team}/hdds-hints) has no smoke test: its mount() aborts 404
    // unless the team has an HDDS module version linked to a deployed xlsform.

});
