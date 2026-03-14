<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

test('authenticated user receives their permissions and roles via inertia', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::OPERATOR->value);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('auth.permissions')
        ->has('auth.roles')
        ->where('auth.roles', [Role::OPERATOR->value])
    );
});

test('user permissions match assigned role permissions', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::DRIVER->value);

    $expectedPermissions = [
        Permission::VIEW_DASHBOARD->value,
        Permission::VIEW_SETTINGS->value,
        Permission::VIEW_SERVICES->value,
        Permission::REGISTER_SERVICE_TIMES->value,
        Permission::VIEW_INCIDENTS->value,
        Permission::CREATE_INCIDENTS->value,
        Permission::RECEIVE_NOTIFICATIONS->value,
    ];

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(function ($page) use ($expectedPermissions) {
        $permissions = $page->toArray()['props']['auth']['permissions'];

        foreach ($expectedPermissions as $expected) {
            expect($permissions)->toContain($expected);
        }

        expect(count($permissions))->toBe(count($expectedPermissions));
    });
});

test('super admin receives no direct permissions but has the role', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN->value);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('auth.roles', [Role::SUPER_ADMIN->value])
        ->where('auth.permissions', [])
    );
});

test('guest receives no permissions or roles', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});
