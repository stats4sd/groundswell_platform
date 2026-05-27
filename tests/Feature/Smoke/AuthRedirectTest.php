<?php

describe('Protected routes redirect unauthenticated guests', function () {

    test('downloads route redirects guest', function () {
        $this->get('/downloads/test.pdf')
            ->assertRedirect();
    });

    test('admin dashboard redirects guest', function () {
        $this->get('/admin/dashboard')
            ->assertRedirect();
    });

    test('app panel redirects guest', function () {
        $this->get('/app/1/survey-dashboard')
            ->assertRedirect();
    });

    test('program panel redirects guest', function () {
        $this->get('/program/1')
            ->assertRedirect();
    });

});
