<?php

use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Regression for cross-role-ux-qa-audit F-007:
 * the framework-default 403 view rendered English copy ('Forbidden',
 * 'This action is unauthorized.') to every role on every gated route.
 * Fix adds the missing strings to lang/es.json so Laravel's __() lookup
 * in the minimal error view returns Spanish.
 */
it('renders the 403 page in Spanish when a non-admin hits an admin route', function (): void {
    SpatieRole::firstOrCreate(['name' => Role::OPERATOR->value, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole(Role::OPERATOR->value);

    $this->actingAs($user);

    $response = $this->get('/users');

    $response->assertStatus(403);
    $response->assertSeeText('Acceso denegado');
    $response->assertSeeText('No tiene permisos para realizar esta acción.');
    $response->assertDontSeeText('Forbidden');
    $response->assertDontSeeText('This action is unauthorized.');
});
