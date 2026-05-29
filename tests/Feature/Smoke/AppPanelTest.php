<?php

use App\Models\Team;

describe('App panel routes load for authenticated team member', function () {

    beforeEach(function () {
        $this->team = Team::withoutEvents(fn () => Team::factory()->create());
        // Team::withoutEvents suppresses the 'created' boot hook, which normally creates localContextModuleVersion.
        // Pages like ContextQuestions call $team->localContextModuleVersion->load(...) and fatal-error if it is null.
        $this->team->localContextModuleVersion()->create(['name' => 'Local Context']);
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

    test('data collection index loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/data-collection-index")
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

    test('lisp index loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/lisp-index")
            ->assertOk();
    });

    test('lisp indicators loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/lisp-indicators")
            ->assertOk();
    });

    test('lisp workshop loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/lisp-workshop")
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

    test('time frame loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/time-frame")
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

    test('farms list loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/location-levels/farms")
            ->assertOk();
    });

    test('choice list entries list loads', function () {
        $this->actingAs($this->user)
            ->get("/app/{$this->team->id}/localisations/choice-list-entries")
            ->assertOk();
    });

});
