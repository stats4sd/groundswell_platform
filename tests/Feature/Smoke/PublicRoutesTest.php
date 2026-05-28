<?php

use App\Livewire\CoverPage;
use App\Livewire\ResultsPage;

describe('Public routes are accessible without authentication', function () {

    test('cover page loads', function () {
        $this->get('/')
            ->assertOk()
            ->assertSeeLivewire(CoverPage::class);
    });

    // page keeps loading, no result shows
    test('results page loads', function () {
        $this->get('/results')
            ->assertOk()
            ->assertSeeLivewire(ResultsPage::class);
    });

    // previous_agroecology_scores table is not existed in database.
    // this table existed in holpa staging database, but there is no migration file for this table in holpa repo.
    // I have copied this table from holpa staging database, this test still failed.
    // I created a migration file with id and timestamps columns only, the test is passed.
    test('temp results page loads', function () {
        $this->get('/temp-results')
            ->assertOk();
    });

    test('app login page loads', function () {
        $this->get('/app/login')
            ->assertOk();
    });

    // if user has not logged in, it will redirect user to app panel login page
    test('register team page loads', function () {
        $this->get('/app/new')
            ->assertRedirect();
    });

    test('password reset request page loads', function () {
        $this->get('/app/password-reset/request')
            ->assertOk();
    });

    // ValidateSignature middleware protects this route — direct access without a signed URL returns 403
    test('password reset reset page requires signed url', function () {
        $this->get('/app/password-reset/reset')
            ->assertForbidden();
    });

});
