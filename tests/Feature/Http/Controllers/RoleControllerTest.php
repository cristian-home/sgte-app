<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function (): void {
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::ADMIN->value);
    actingAs($this->admin);
});

test('admin sees the roles index with 5 cards', function (): void {
    $response = get(route('roles.index'));
    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('roles/index')
            ->has('roles', 5)
    );
});

test('admin sees role detail with permissions matrix', function (): void {
    $response = get(route('roles.show', RoleEnum::ADMIN->value));
    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('roles/show')
            ->where('role.name', RoleEnum::ADMIN->value)
            ->has('permissionGroups')
            ->has('assignedPermissions')
    );
});

test('admin can update role description and permissions and one activity row is written', function (): void {
    $role = Role::where('name', RoleEnum::ACCOUNTING->value)->first();

    $newPermissions = $role->permissions->pluck('name')->all();
    $newPermissions[] = Permission::DELETE_INVOICES->value;
    sort($newPermissions);

    put(route('roles.update', RoleEnum::ACCOUNTING->value), [
        'description' => 'Nueva descripción de prueba.',
        'permissions' => $newPermissions,
    ])->assertRedirect();

    $role->refresh();
    expect($role->description)->toBe('Nueva descripción de prueba.');
    expect($role->permissions->pluck('name')->contains(Permission::DELETE_INVOICES->value))->toBeTrue();

    $synced = Activity::query()->where('event', 'permissions_synced')->latest('id')->first();
    expect($synced)->not->toBeNull();
    expect($synced->properties->get('added'))->toContain(Permission::DELETE_INVOICES->value);
});

test('update without changes writes no permissions_synced activity', function (): void {
    $role = Role::where('name', RoleEnum::ACCOUNTING->value)->first();
    $current = $role->permissions->pluck('name')->all();

    put(route('roles.update', RoleEnum::ACCOUNTING->value), [
        'description' => $role->description,
        'permissions' => $current,
    ])->assertRedirect();

    expect(Activity::query()->where('event', 'permissions_synced')->count())->toBe(0);
});

test('admin cannot update super admin role', function (): void {
    put(route('roles.update', RoleEnum::SUPER_ADMIN->value), [
        'description' => 'Hijack',
        'permissions' => [Permission::VIEW_DASHBOARD->value],
    ])->assertForbidden();
});

test('non admin gets 403 on roles index', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole(RoleEnum::OPERATOR->value);
    actingAs($operator);

    get(route('roles.index'))->assertForbidden();
    get(route('roles.show', RoleEnum::ADMIN->value))->assertForbidden();
});

test('rejects invalid permission keys', function (): void {
    put(route('roles.update', RoleEnum::ACCOUNTING->value), [
        'description' => 'x',
        'permissions' => ['invalid.permission'],
    ])->assertSessionHasErrors(['permissions.0']);
});
