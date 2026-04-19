<?php

namespace Tests\Feature\Permissions;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * Regression for accounting-master-data-asymmetry. Resolution:
 * grant Contabilidad read-only access to the vehicle + driver
 * first-class lists so they can drill into master data when
 * investigating billing. Write operations remain forbidden.
 */
beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::ACCOUNTING->value);
    actingAs($user);
});

test('accounting can list vehicles', function (): void {
    get(route('vehicles.index'))->assertOk();
});

test('accounting can view a vehicle detail', function (): void {
    $vehicle = Vehicle::factory()->create();
    get(route('vehicles.show', $vehicle))->assertOk();
});

test('accounting cannot create a vehicle', function (): void {
    post(route('vehicles.store'), [])->assertForbidden();
});

test('accounting cannot update a vehicle', function (): void {
    $vehicle = Vehicle::factory()->create();
    put(route('vehicles.update', $vehicle), [])->assertForbidden();
});

test('accounting cannot delete a vehicle', function (): void {
    $vehicle = Vehicle::factory()->create();
    delete(route('vehicles.destroy', $vehicle))->assertForbidden();
});

test('accounting can list drivers', function (): void {
    get(route('drivers.index'))->assertOk();
});

test('accounting can view a driver detail', function (): void {
    $driver = Driver::factory()->create();
    get(route('drivers.show', $driver))->assertOk();
});

test('accounting cannot create a driver', function (): void {
    post(route('drivers.store'), [])->assertForbidden();
});

test('accounting cannot update a driver', function (): void {
    $driver = Driver::factory()->create();
    put(route('drivers.update', $driver), [])->assertForbidden();
});

test('accounting cannot delete a driver', function (): void {
    $driver = Driver::factory()->create();
    delete(route('drivers.destroy', $driver))->assertForbidden();
});
