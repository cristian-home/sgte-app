<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

test('data migration creates roles and permissions', function (): void {
    expect(SpatieRole::count())->toBe(5);
    expect(SpatiePermission::count())->toBe(count(Permission::cases()));
});

test('catalog migration creates super admin user', function (): void {
    $superAdmin = User::role(Role::SUPER_ADMIN->value)->first();

    expect($superAdmin)->not->toBeNull();
    expect($superAdmin->email_verified_at)->not->toBeNull();
});

test('catalog migration does not create non-admin users in testing', function (): void {
    // In testing, only the super admin is created by the catalog migration.
    // Reference users (admin, operator, driver, accounting) are only created
    // by the demo migration which skips in testing environment.
    expect(User::count())->toBe(1);
    expect(User::role(Role::SUPER_ADMIN->value)->count())->toBe(1);
});
