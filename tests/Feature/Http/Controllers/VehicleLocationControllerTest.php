<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function (): void {
    config()->set('sgte.gps_enabled', true);
});

test('admin can list vehicle locations with eager-loaded relations', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    VehicleLocation::factory()->count(3)->create();

    get(route('vehicle-locations.index'))
        ->assertOk()
        ->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('vehicle-locations/index')
                ->has('vehicleLocations.data')
                ->has('vehicles')
        );
});

test('index filter by vehicle_id exact works', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $vehicle = Vehicle::factory()->create();
    VehicleLocation::factory()->create(['vehicle_id' => $vehicle->id]);
    VehicleLocation::factory()->count(2)->create();

    $response = get(route('vehicle-locations.index', [
        'filter' => ['vehicle_id' => $vehicle->id],
    ]))->assertOk();

    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page->has('vehicleLocations.data', 1)
    );
});

test('index filter by recorded_from/recorded_to date range', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    VehicleLocation::factory()->create(['recorded_at' => Carbon::now()->subDays(10)]);
    $target = VehicleLocation::factory()->create(['recorded_at' => Carbon::now()->subDays(2)]);
    VehicleLocation::factory()->create(['recorded_at' => Carbon::now()->addDays(2)]);

    $response = get(route('vehicle-locations.index', [
        'filter' => [
            'recorded_from' => Carbon::now()->subDays(3)->toDateString(),
            'recorded_to' => Carbon::now()->subDays(1)->toDateString(),
        ],
    ]))->assertOk();

    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('vehicleLocations.data', 1)
            ->where('vehicleLocations.data.0.id', $target->id)
    );
});

test('admin can store a manual location', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $vehicle = Vehicle::factory()->create();

    post(route('vehicle-locations.store'), [
        'vehicle_id' => $vehicle->id,
        'recorded_at' => now()->toIso8601String(),
        'latitude' => 6.2518,
        'longitude' => -75.5636,
        'is_manual' => true,
    ])->assertRedirect(route('vehicle-locations.index'));

    expect(VehicleLocation::where('vehicle_id', $vehicle->id)->exists())->toBeTrue();
});

test('operator can store but cannot delete', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole(Role::OPERATOR->value);
    actingAs($operator);

    $vehicle = Vehicle::factory()->create();

    post(route('vehicle-locations.store'), [
        'vehicle_id' => $vehicle->id,
        'recorded_at' => now()->toIso8601String(),
        'latitude' => 6.2518,
        'longitude' => -75.5636,
        'is_manual' => true,
    ])->assertRedirect();

    $location = VehicleLocation::latest('id')->first();

    delete(route('vehicle-locations.destroy', $location))->assertForbidden();
});

test('driver receives 403 on index (no VIEW grant)', function (): void {
    $driver = User::factory()->create();
    $driver->assignRole(Role::DRIVER->value);
    actingAs($driver);

    get(route('vehicle-locations.index'))->assertForbidden();
});

test('accounting receives 403 on index', function (): void {
    $accounting = User::factory()->create();
    $accounting->assignRole(Role::ACCOUNTING->value);
    actingAs($accounting);

    get(route('vehicle-locations.index'))->assertForbidden();
});

test('feature flag disabled 404s the index', function (): void {
    config()->set('sgte.gps_enabled', false);

    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    get(route('vehicle-locations.index'))->assertNotFound();
});

test('invalid latitude is rejected with 422', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $vehicle = Vehicle::factory()->create();

    post(route('vehicle-locations.store'), [
        'vehicle_id' => $vehicle->id,
        'recorded_at' => now()->toIso8601String(),
        'latitude' => 200,
        'longitude' => -75.5636,
        'is_manual' => true,
    ])->assertSessionHasErrors('latitude');
});
