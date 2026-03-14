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

test('data migration creates one user per role', function (): void {
    expect(User::count())->toBe(5);

    foreach (Role::cases() as $role) {
        expect(User::role($role->value)->count())->toBe(1);
    }
});

test('data migration creates verified users', function (): void {
    expect(User::whereNotNull('email_verified_at')->count())->toBe(5);
});
