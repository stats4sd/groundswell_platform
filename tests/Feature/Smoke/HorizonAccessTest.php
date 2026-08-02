<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

describe('Horizon dashboard access', function () {

    test('super admin can load the horizon dashboard', function () {
        $this->actingAs(createSuperAdmin())
            ->get('/horizon')
            ->assertOk();
    });

    test('non admin cannot load the horizon dashboard', function () {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get('/horizon')
            ->assertForbidden();
    });

    test('guest cannot load the horizon dashboard', function () {
        $this->get('/horizon')
            ->assertForbidden();
    });
});
