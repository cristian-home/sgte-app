<?php

namespace Tests\Feature\Middleware;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

test('user with must_change_password is redirected to password edit', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $user->assignRole(Role::ADMIN->value);
    actingAs($user);

    get('/dashboard')->assertRedirect(route('user-password.edit'));
});

test('user with flag can access the password edit route', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $user->assignRole(Role::ADMIN->value);
    actingAs($user);

    get(route('user-password.edit'))->assertOk();
});

test('user with flag can post the password update', function (): void {
    $user = User::factory()->create([
        'must_change_password' => true,
        'password' => Hash::make('password'),
    ]);
    $user->assignRole(Role::ADMIN->value);
    actingAs($user);

    put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'NewSecure2026!',
        'password_confirmation' => 'NewSecure2026!',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->must_change_password)->toBeFalse();
});

test('user with flag can hit logout without redirect loop', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $user->assignRole(Role::ADMIN->value);
    actingAs($user);

    post('/logout')->assertRedirect();
});

test('user without flag is unaffected', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole(Role::ADMIN->value);
    actingAs($user);

    get('/dashboard')->assertOk();
});

test('guest is unaffected', function (): void {
    get('/login')->assertOk();
});
