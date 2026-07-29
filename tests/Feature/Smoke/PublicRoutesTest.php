<?php

describe('Public routes are accessible without authentication', function () {

    test('root path redirects to app login', function () {
        $this->get('/')
            ->assertRedirect('app');
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
