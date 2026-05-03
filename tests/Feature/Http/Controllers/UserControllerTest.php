<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::ADMIN->value);
    actingAs($this->admin);
});

test('admin can list users with paginated payload + filters', function (): void {
    User::factory()->count(3)->create();

    $response = get(route('users.index'));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('users/index')
            ->has('users.data')
            ->has('users.current_page')
            ->has('availableRoles', 4)
            ->has('filters')
    );
});

test('list filters by search and roles and is_active', function (): void {
    $opActive = User::factory()->create(['name' => 'Camila Test', 'is_active' => true]);
    $opActive->assignRole(Role::OPERATOR->value);
    $opInactive = User::factory()->create(['name' => 'Otro Operador', 'is_active' => false]);
    $opInactive->assignRole(Role::OPERATOR->value);
    $driver = User::factory()->create(['name' => 'Diego Driver']);
    $driver->assignRole(Role::DRIVER->value);

    $response = get(route('users.index', ['filter' => ['search' => 'camila']]));
    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page->where('users.total', 1)
    );

    $response = get(route('users.index', ['filter' => ['roles' => 'driver']]));
    $response->assertInertia(
        fn ($page) => $page->where('users.total', 1)
    );

    $response = get(route('users.index', ['filter' => ['is_active' => 'false']]));
    $response->assertInertia(
        fn ($page) => $page->where('users.total', 1)
    );
});

test('admin can store a new user with multiple roles', function (): void {
    $response = post(route('users.store'), [
        'name' => 'New Person',
        'email' => 'new@sgte.app',
        'password' => 'Operator123!',
        'roles' => [Role::OPERATOR->value, Role::ACCOUNTING->value],
        'is_active' => true,
        'send_welcome_email' => false,
    ]);

    $response->assertRedirect(route('users.index'));

    $user = User::where('email', 'new@sgte.app')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole(Role::OPERATOR->value))->toBeTrue();
    expect($user->hasRole(Role::ACCOUNTING->value))->toBeTrue();
    expect($user->is_active)->toBeTrue();
});

test('store dispatches welcome email when flag set', function (): void {
    Notification::fake();

    post(route('users.store'), [
        'name' => 'Welcome User',
        'email' => 'welcome@sgte.app',
        'password' => 'Whatever123!',
        'roles' => [Role::ACCOUNTING->value],
        'is_active' => true,
        'send_welcome_email' => true,
    ])->assertRedirect();

    $user = User::where('email', 'welcome@sgte.app')->first();
    expect($user->must_change_password)->toBeTrue();
    Notification::assertSentTo($user, WelcomeUserNotification::class);
});

test('store rejects super_admin in roles array', function (): void {
    post(route('users.store'), [
        'name' => 'Bad',
        'email' => 'bad@sgte.app',
        'password' => 'Password123!',
        'roles' => [Role::SUPER_ADMIN->value],
        'is_active' => true,
    ])->assertSessionHasErrors(['roles.0']);
});

test('store rejects empty roles array', function (): void {
    post(route('users.store'), [
        'name' => 'Bad',
        'email' => 'bad2@sgte.app',
        'password' => 'Password123!',
        'roles' => [],
        'is_active' => true,
    ])->assertSessionHasErrors(['roles']);
});

test('storing a user with duplicate email fails', function (): void {
    User::factory()->create(['email' => 'dup@sgte.app']);

    post(route('users.store'), [
        'name' => 'Dup',
        'email' => 'dup@sgte.app',
        'password' => 'Password123!',
        'roles' => [Role::OPERATOR->value],
        'is_active' => true,
    ])->assertSessionHasErrors(['email']);
});

test('admin can update user roles and one activity log row is written', function (): void {
    $target = User::factory()->create();
    $target->assignRole(Role::DRIVER->value);

    $before = Activity::query()->count();

    put(route('users.update', $target), [
        'name' => 'Updated Name',
        'email' => $target->email,
        'roles' => [Role::ACCOUNTING->value],
        'is_active' => true,
    ])->assertRedirect(route('users.index'));

    $target->refresh();
    expect($target->name)->toBe('Updated Name');
    expect($target->hasRole(Role::ACCOUNTING->value))->toBeTrue();
    expect($target->hasRole(Role::DRIVER->value))->toBeFalse();

    $synced = Activity::query()->where('event', 'roles_synced')->latest('id')->first();
    expect($synced)->not->toBeNull();
    expect($synced->properties->get('old_roles'))->toBe(['driver']);
    expect($synced->properties->get('new_roles'))->toBe(['accounting']);
});

test('update without role change writes no roles_synced activity row', function (): void {
    $target = User::factory()->create();
    $target->assignRole(Role::DRIVER->value);

    put(route('users.update', $target), [
        'name' => 'Same Roles',
        'email' => $target->email,
        'roles' => [Role::DRIVER->value],
        'is_active' => true,
    ])->assertRedirect();

    expect(Activity::query()->where('event', 'roles_synced')->count())->toBe(0);
});

test('admin cannot edit a super admin user', function (): void {
    $super = User::factory()->create();
    $super->assignRole(Role::SUPER_ADMIN->value);

    put(route('users.update', $super), [
        'name' => 'Hijack',
        'email' => $super->email,
        'roles' => [Role::ADMIN->value],
        'is_active' => true,
    ])->assertForbidden();
});

test('admin cannot remove last admin role from themselves', function (): void {
    // $this->admin is the only admin in DB.
    put(route('users.update', $this->admin), [
        'name' => $this->admin->name,
        'email' => $this->admin->email,
        'roles' => [Role::OPERATOR->value],
        'is_active' => true,
    ])->assertSessionHasErrors(['roles']);

    $this->admin->refresh();
    expect($this->admin->hasRole(Role::ADMIN->value))->toBeTrue();
});

test('admin can demote themselves when another admin exists', function (): void {
    $other = User::factory()->create();
    $other->assignRole(Role::ADMIN->value);

    put(route('users.update', $this->admin), [
        'name' => $this->admin->name,
        'email' => $this->admin->email,
        'roles' => [Role::OPERATOR->value],
        'is_active' => true,
    ])->assertRedirect();

    expect($this->admin->fresh()->hasRole(Role::ADMIN->value))->toBeFalse();
});

test('admin can delete another user', function (): void {
    $target = User::factory()->create();
    $target->assignRole(Role::OPERATOR->value);

    delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'));
    expect(User::find($target->id))->toBeNull();
});

test('admin cannot delete the last admin', function (): void {
    delete(route('users.destroy', $this->admin))
        ->assertSessionHasErrors(['user']);
    expect(User::find($this->admin->id))->not->toBeNull();
});

test('admin cannot delete their own account regardless', function (): void {
    $other = User::factory()->create();
    $other->assignRole(Role::ADMIN->value);

    delete(route('users.destroy', $this->admin))
        ->assertSessionHasErrors(['user']);
});

test('admin cannot delete a super admin', function (): void {
    $super = User::factory()->create();
    $super->assignRole(Role::SUPER_ADMIN->value);

    delete(route('users.destroy', $super))->assertForbidden();
});

test('admin can toggle active state on another user', function (): void {
    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole(Role::OPERATOR->value);

    patch(route('users.toggle-active', $target))->assertRedirect();
    expect($target->fresh()->is_active)->toBeFalse();

    patch(route('users.toggle-active', $target))->assertRedirect();
    expect($target->fresh()->is_active)->toBeTrue();
});

test('admin cannot deactivate themselves', function (): void {
    patch(route('users.toggle-active', $this->admin))
        ->assertSessionHasErrors(['is_active']);
    expect($this->admin->fresh()->is_active)->toBeTrue();
});

test('admin cannot toggle active on a super admin', function (): void {
    $super = User::factory()->create();
    $super->assignRole(Role::SUPER_ADMIN->value);

    patch(route('users.toggle-active', $super))->assertForbidden();
});

test('admin can reset password and flag must_change_password', function (): void {
    $target = User::factory()->create(['must_change_password' => false]);
    $target->assignRole(Role::OPERATOR->value);
    $oldPassword = $target->password;

    post(route('users.reset-password', $target))->assertRedirect();

    $target->refresh();
    expect($target->must_change_password)->toBeTrue();
    expect($target->password)->not->toBe($oldPassword);
});

test('admin cannot reset super admin password', function (): void {
    $super = User::factory()->create();
    $super->assignRole(Role::SUPER_ADMIN->value);

    post(route('users.reset-password', $super))->assertForbidden();
});

test('operator cannot access the users module', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole(Role::OPERATOR->value);
    actingAs($operator);

    get(route('users.index'))->assertForbidden();
    post(route('users.store'), [
        'name' => 'X',
        'email' => 'x@sgte.app',
        'password' => 'Password123!',
        'roles' => [Role::OPERATOR->value],
        'is_active' => true,
    ])->assertForbidden();
});

test('driver cannot access the users module', function (): void {
    $driver = User::factory()->create();
    $driver->assignRole(Role::DRIVER->value);
    actingAs($driver);

    get(route('users.index'))->assertForbidden();
});
