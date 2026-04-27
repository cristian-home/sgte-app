<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

dataset('templates', [
    'users' => ['users', 'plantilla_users.csv'],
    'third_parties' => ['third-parties', 'plantilla_third_parties.csv'],
    'drivers' => ['drivers', 'plantilla_drivers.csv'],
    'vehicles' => ['vehicles', 'plantilla_vehicles.csv'],
]);

test('super admin can download each template', function (string $type, string $filename): void {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN->value);
    actingAs($user);

    $response = get(route('admin.imports.templates.show', $type));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))
        ->toContain($filename);
})->with('templates');

test('invalid template slug returns 404', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN->value);
    actingAs($user);

    get('/admin/imports/templates/xyz')->assertNotFound();
});

test('non super admin cannot download templates', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);
    actingAs($user);

    get(route('admin.imports.templates.show', 'users'))->assertForbidden();
});
