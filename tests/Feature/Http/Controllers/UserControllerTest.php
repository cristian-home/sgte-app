<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::ADMIN->value);
    actingAs($this->admin);
});

test('admin can list users', function (): void {
    User::factory()->count(3)->create();

    $response = get(route('users.index'));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('users/index')
            ->has('users')
            ->has('availableRoles')
    );
});

test('admin can render the create form', function (): void {
    $response = get(route('users.create'));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('users/create')
            ->has('availableRoles')
    );
});

test('admin can store a new user with a role', function (): void {
    $response = post(route('users.store'), [
        'name' => 'New Operator',
        'email' => 'new@sgte.app',
        'password' => 'Operator123!',
        'role' => Role::OPERATOR->value,
    ]);

    $response->assertRedirect(route('users.index'));

    $user = User::where('email', 'new@sgte.app')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole(Role::OPERATOR->value))->toBeTrue();
});

test('storing a user with duplicate email fails', function (): void {
    User::factory()->create(['email' => 'dup@sgte.app']);

    $response = post(route('users.store'), [
        'name' => 'Dup',
        'email' => 'dup@sgte.app',
        'password' => 'Password123!',
        'role' => Role::OPERATOR->value,
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('admin can update an existing user and change their role', function (): void {
    $target = User::factory()->create();
    $target->assignRole(Role::DRIVER->value);

    $response = put(route('users.update', $target), [
        'name' => 'Updated Name',
        'email' => $target->email,
        'password' => '',
        'role' => Role::ACCOUNTING->value,
    ]);

    $response->assertRedirect(route('users.index'));
    $target->refresh();
    expect($target->name)->toBe('Updated Name');
    expect($target->hasRole(Role::ACCOUNTING->value))->toBeTrue();
    expect($target->hasRole(Role::DRIVER->value))->toBeFalse();
});

test('admin can delete another user', function (): void {
    $target = User::factory()->create();

    $response = delete(route('users.destroy', $target));

    $response->assertRedirect(route('users.index'));
    expect(User::find($target->id))->toBeNull();
});

test('admin cannot delete their own account', function (): void {
    $response = delete(route('users.destroy', $this->admin));

    $response->assertRedirect(route('users.index'));
    $response->assertSessionHasErrors(['user']);
    expect(User::find($this->admin->id))->not->toBeNull();
});

test('operator cannot access the users module', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole(Role::OPERATOR->value);
    actingAs($operator);

    get(route('users.index'))->assertForbidden();
    get(route('users.create'))->assertForbidden();
    post(route('users.store'), [
        'name' => 'X',
        'email' => 'x@sgte.app',
        'password' => 'Password123!',
        'role' => Role::OPERATOR->value,
    ])->assertForbidden();
});

test('driver cannot access the users module', function (): void {
    $driver = User::factory()->create();
    $driver->assignRole(Role::DRIVER->value);
    actingAs($driver);

    get(route('users.index'))->assertForbidden();
});
