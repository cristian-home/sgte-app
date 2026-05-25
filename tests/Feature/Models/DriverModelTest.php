<?php

use App\Enums\Role;
use App\Models\Driver;
use App\Models\User;

test('propagates email change to linked user', function (): void {
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@sgte.app']);
    $user->assignRole(Role::DRIVER->value);

    $driver = Driver::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Carlos',
        'second_name' => null,
        'first_lastname' => 'Pérez',
        'second_lastname' => null,
        'email' => 'old@sgte.app',
    ]);

    $driver->update(['email' => 'nuevo@sgte.app']);

    $user->refresh();
    expect($user->email)->toBe('nuevo@sgte.app');
});

test('propagates name change to linked user', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);
    $user->assignRole(Role::DRIVER->value);

    $driver = Driver::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Carlos',
        'second_name' => null,
        'first_lastname' => 'Pérez',
        'second_lastname' => null,
    ]);

    $driver->update(['first_lastname' => 'Gómez']);

    $user->refresh();
    expect($user->name)->toBe('Carlos Gómez');
});

test('does not touch user when driver has no user_id', function (): void {
    $driver = Driver::factory()->create([
        'user_id' => null,
        'first_name' => 'Sin',
        'first_lastname' => 'Cuenta',
        'email' => 'sin@sgte.app',
    ]);

    // Forzamos un update — no debe explotar ni intentar tocar User.
    $driver->update(['email' => 'sin-otro@sgte.app']);

    expect($driver->fresh()->email)->toBe('sin-otro@sgte.app');
});

test('does not touch user when only unrelated fields change', function (): void {
    $user = User::factory()->create(['name' => 'Original', 'email' => 'original@sgte.app']);
    $user->assignRole(Role::DRIVER->value);

    $driver = Driver::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Original',
        'first_lastname' => 'Apellido',
        'email' => 'original@sgte.app',
    ]);

    $driver->update(['phone' => '3001234567']);

    $user->refresh();
    expect($user->name)->toBe('Original');
    expect($user->email)->toBe('original@sgte.app');
});

test('soft-deleting a driver revokes the driver role and deactivates the user', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(Role::DRIVER->value);

    $driver = Driver::factory()->create(['user_id' => $user->id]);

    $driver->delete();

    $user->refresh();
    expect($user->is_active)->toBeFalse();
    expect($user->hasRole(Role::DRIVER->value))->toBeFalse();
});

test('fullName composes the four name fields trimming empties', function (): void {
    $driver = Driver::factory()->make([
        'first_name' => 'Juan',
        'second_name' => null,
        'first_lastname' => 'Pérez',
        'second_lastname' => 'Gómez',
    ]);

    expect($driver->fullName())->toBe('Juan Pérez Gómez');
});

test('hasAccount returns true only when user_id is set', function (): void {
    $without = Driver::factory()->make(['user_id' => null]);
    expect($without->hasAccount())->toBeFalse();

    $user = User::factory()->create();
    $with = Driver::factory()->make(['user_id' => $user->id]);
    expect($with->hasAccount())->toBeTrue();
});
