<?php

use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    config()->set('sgte.gps_enabled', true);
});

test('admin sees the map page with active services payload', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $service = Service::factory()->create([
        'service_status' => ServiceStatus::Open,
        'service_date' => Carbon::today()->toDateString(),
    ]);
    VehicleLocation::factory()->gps()->create([
        'service_id' => $service->id,
        'vehicle_id' => $service->vehicle_id,
        'recorded_at' => Carbon::now(),
    ]);

    $response = get(route('gps.map'))->assertOk();

    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('gps/map')
            ->has('activeServices.0', fn ($entry) => $entry
                ->where('service_id', $service->id)
                ->has('vehicle_plate')
                ->has('driver_name')
                ->has('location.latitude')
                ->has('location.longitude')
                ->etc()
            )
    );
});

test('operator sees the map page', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole(Role::OPERATOR->value);
    actingAs($operator);

    get(route('gps.map'))->assertOk();
});

test('driver receives 403 on the map page', function (): void {
    $driver = User::factory()->create();
    $driver->assignRole(Role::DRIVER->value);
    actingAs($driver);

    get(route('gps.map'))->assertForbidden();
});

test('accounting receives 403 on the map page', function (): void {
    $accounting = User::factory()->create();
    $accounting->assignRole(Role::ACCOUNTING->value);
    actingAs($accounting);

    get(route('gps.map'))->assertForbidden();
});

test('feature flag disabled 404s the map page', function (): void {
    config()->set('sgte.gps_enabled', false);

    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    get(route('gps.map'))->assertNotFound();
});

test('service without any location emits null location in payload', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $service = Service::factory()->create([
        'service_status' => ServiceStatus::Open,
        'service_date' => Carbon::today()->toDateString(),
    ]);

    $response = get(route('gps.map'))->assertOk();

    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('activeServices.0.service_id', $service->id)
            ->where('activeServices.0.location', null)
    );
});

test('vehicle-scoped 24h fallback works when no service-scoped location exists', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $service = Service::factory()->create([
        'service_status' => ServiceStatus::Open,
        'service_date' => Carbon::today()->toDateString(),
    ]);
    // No service_scoped location; a vehicle-scoped one within 24h.
    VehicleLocation::factory()->gps()->create([
        'service_id' => null,
        'vehicle_id' => $service->vehicle_id,
        'recorded_at' => Carbon::now()->subHours(2),
    ]);

    $response = get(route('gps.map'))->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('activeServices.0.service_id', $service->id)
            ->has('activeServices.0.location.latitude')
    );
});
