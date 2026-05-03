<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\post;

test('inactive user cannot login even with correct credentials', function (): void {
    User::factory()->create([
        'email' => 'inactive@sgte.app',
        'password' => Hash::make('Password123!'),
        'is_active' => false,
    ]);

    $response = post('/login', [
        'email' => 'inactive@sgte.app',
        'password' => 'Password123!',
    ]);

    $response->assertSessionHasErrors(['email']);
    expect(session('errors')->get('email'))->toContain(
        'Esta cuenta está desactivada. Contacta a un administrador.'
    );
    expect(auth()->check())->toBeFalse();
});

test('inactive user wrong password gets the same generic error message style', function (): void {
    User::factory()->create([
        'email' => 'inactive2@sgte.app',
        'password' => Hash::make('Password123!'),
        'is_active' => false,
    ]);

    $response = post('/login', [
        'email' => 'inactive2@sgte.app',
        'password' => 'Wrong!',
    ]);

    $response->assertSessionHasErrors(['email']);
    expect(auth()->check())->toBeFalse();
});

test('active user logs in successfully and last_login_at is updated', function (): void {
    $user = User::factory()->create([
        'email' => 'active@sgte.app',
        'password' => Hash::make('Password123!'),
        'is_active' => true,
        'last_login_at' => null,
    ]);

    post('/login', [
        'email' => 'active@sgte.app',
        'password' => 'Password123!',
    ])->assertRedirect();

    $user->refresh();
    expect(auth()->check())->toBeTrue();
    expect($user->last_login_at)->not->toBeNull();
});
