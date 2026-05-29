<?php

describe('Admin panel routes load for Super Admin', function () {

    beforeEach(function () {
        $this->superAdmin = createSuperAdmin();
    });

    test('admin dashboard loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/dashboard')
            ->assertOk();
    });

    test('domains list loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/domains')
            ->assertOk();
    });

    test('global indicators list loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/global-indicators')
            ->assertOk();
    });

    test('themes list loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/themes')
            ->assertOk();
    });

    test('programs list loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/programs')
            ->assertOk();
    });

    test('teams list loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/teams')
            ->assertOk();
    });

    test('users list loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/users')
            ->assertOk();
    });

    test('datasets list loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/datasets')
            ->assertOk();
    });

    test('xlsform templates list loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/xlsform-templates')
            ->assertOk();
    });

    test('xlsform modules list loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/xlsform-modules')
            ->assertOk();
    });

    test('xlsform module versions list loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/xlsform-module-versions')
            ->assertOk();
    });

    test('choice lists list loads', function () {
        $this->actingAs($this->superAdmin)
            ->get('/admin/choice-lists')
            ->assertOk();
    });

});
