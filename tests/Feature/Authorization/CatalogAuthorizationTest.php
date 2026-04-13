<?php

namespace Tests\Feature\Authorization;

use App\Enums\Role;
use App\Models\DocumentType;
use App\Models\Eps;
use App\Models\PensionFund;
use App\Models\SeveranceFund;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * Verifies that the four "static" catalog modules (document types, EPS,
 * pension funds, severance funds) are gated behind the MANAGE_CATALOGS
 * permission. Admin, super-admin, and operator (via the Gestión/Catálogos
 * group) pass; driver and accounting are denied.
 */
dataset('catalogs', [
    'document-types' => [
        'document-types',
        fn () => DocumentType::factory()->create(),
        fn () => ['code' => 'XX', 'name' => 'Test', 'is_natural_person' => true, 'is_legal_person' => false],
    ],
    'eps' => [
        'eps',
        fn () => Eps::factory()->create(),
        fn () => ['code' => fake()->lexify('???'), 'name' => fake()->name()],
    ],
    'pension-funds' => [
        'pension-funds',
        fn () => PensionFund::factory()->create(),
        fn () => ['code' => fake()->lexify('???'), 'name' => fake()->name()],
    ],
    'severance-funds' => [
        'severance-funds',
        fn () => SeveranceFund::factory()->create(),
        fn () => ['code' => fake()->lexify('???'), 'name' => fake()->name()],
    ],
]);

dataset('denied_roles', [
    'driver' => [Role::DRIVER],
    'accounting' => [Role::ACCOUNTING],
]);

test('denied roles cannot view catalog index', function (string $prefix, $modelFactory, $validData, Role $role): void {
    $user = User::factory()->create();
    $user->assignRole($role->value);
    actingAs($user);

    get(route("$prefix.index"))->assertForbidden();
})->with('catalogs')->with('denied_roles');

test('denied roles cannot create catalog entries', function (string $prefix, $modelFactory, $validData, Role $role): void {
    $user = User::factory()->create();
    $user->assignRole($role->value);
    actingAs($user);

    get(route("$prefix.create"))->assertForbidden();
    post(route("$prefix.store"), $validData())->assertForbidden();
})->with('catalogs')->with('denied_roles');

test('denied roles cannot edit or delete catalog entries', function (string $prefix, $modelFactory, $validData, Role $role): void {
    $entity = $modelFactory();

    $user = User::factory()->create();
    $user->assignRole($role->value);
    actingAs($user);

    get(route("$prefix.show", $entity))->assertForbidden();
    get(route("$prefix.edit", $entity))->assertForbidden();
    put(route("$prefix.update", $entity), $validData())->assertForbidden();
    delete(route("$prefix.destroy", $entity))->assertForbidden();
})->with('catalogs')->with('denied_roles');

test('admin role can access catalog modules', function (string $prefix, $modelFactory, $validData): void {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);
    actingAs($user);

    get(route("$prefix.index"))->assertOk();
    get(route("$prefix.create"))->assertOk();
})->with('catalogs');

test('operator role can access catalog modules', function (string $prefix, $modelFactory, $validData): void {
    $user = User::factory()->create();
    $user->assignRole(Role::OPERATOR->value);
    actingAs($user);

    get(route("$prefix.index"))->assertOk();
    get(route("$prefix.create"))->assertOk();
})->with('catalogs');
