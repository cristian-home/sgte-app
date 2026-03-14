<?php

use App\Enums\Role;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users with permission can visit the dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated users without permission cannot visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertForbidden();
});
