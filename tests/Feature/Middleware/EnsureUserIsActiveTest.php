<?php

namespace Tests\Feature\Middleware;

use App\Enums\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('logged-in user gets logged out and redirected when deactivated mid-session', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(Role::OPERATOR->value);

    actingAs($user);
    get(route('dashboard'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $response = get(route('dashboard'));
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['email']);
    expect(auth()->check())->toBeFalse();
});

test('active user passes the middleware untouched', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(Role::ADMIN->value);

    actingAs($user);
    get(route('dashboard'))->assertOk();
    expect(auth()->check())->toBeTrue();
});
