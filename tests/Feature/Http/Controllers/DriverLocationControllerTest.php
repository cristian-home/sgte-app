<?php

use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Models\Driver;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function (): void {
    config()->set('sgte.gps_enabled', true);
});

function driverLocationMakeDriverUserService(): array
{
    $user = User::factory()->create();
    $user->assignRole(Role::DRIVER->value);
    $driver = Driver::factory()->create(['user_id' => $user->id]);
    $vehicle = Vehicle::factory()->create(['is_third_party' => false]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'service_date' => Carbon::today()->toDateString(),
        'service_status' => ServiceStatus::Open,
    ]);

    return [$user, $driver, $service];
}

test('driver registers a GPS location for their own service', function (): void {
    [$user, $driver, $service] = driverLocationMakeDriverUserService();
    actingAs($user);

    post(route('driver.location.store', $service), [
        'latitude' => 6.2518,
        'longitude' => -75.5636,
        'is_manual' => false,
        'accuracy' => 12.5,
    ])->assertRedirect(route('driver.dashboard'));

    $location = VehicleLocation::query()
        ->where('service_id', $service->id)
        ->first();

    expect($location)->not->toBeNull()
        ->and($location->captured_by)->toBe($user->id)
        ->and($location->vehicle_id)->toBe($service->vehicle_id)
        ->and($location->is_manual)->toBeFalse()
        ->and((float) $location->accuracy)->toBe(12.5);
});

test('driver registers a manual location when GPS is unavailable', function (): void {
    [$user, $driver, $service] = driverLocationMakeDriverUserService();
    actingAs($user);

    post(route('driver.location.store', $service), [
        'latitude' => 6.2518,
        'longitude' => -75.5636,
        'is_manual' => true,
    ])->assertRedirect();

    $location = VehicleLocation::query()->where('service_id', $service->id)->first();
    expect($location)->not->toBeNull()
        ->and($location->is_manual)->toBeTrue()
        ->and($location->accuracy)->toBeNull();
});

test('driver cannot register a location for a service assigned to another driver', function (): void {
    [$user, $driver, $_ignore] = driverLocationMakeDriverUserService();
    actingAs($user);

    $otherDriver = Driver::factory()->create();
    $otherService = Service::factory()->create([
        'driver_id' => $otherDriver->id,
        'service_status' => ServiceStatus::Open,
    ]);

    post(route('driver.location.store', $otherService), [
        'latitude' => 6.2518,
        'longitude' => -75.5636,
        'is_manual' => false,
    ])->assertForbidden();
});

test('driver register request rejects invalid latitude', function (): void {
    [$user, $driver, $service] = driverLocationMakeDriverUserService();
    actingAs($user);

    post(route('driver.location.store', $service), [
        'latitude' => 200,
        'longitude' => -75.5636,
        'is_manual' => false,
    ])->assertSessionHasErrors('latitude');
});

test('driver register request rejects invalid longitude', function (): void {
    [$user, $driver, $service] = driverLocationMakeDriverUserService();
    actingAs($user);

    post(route('driver.location.store', $service), [
        'latitude' => 6.2518,
        'longitude' => -200,
        'is_manual' => false,
    ])->assertSessionHasErrors('longitude');
});

test('feature flag disabled 404s the driver endpoint', function (): void {
    [$user, $driver, $service] = driverLocationMakeDriverUserService();
    config()->set('sgte.gps_enabled', false);
    actingAs($user);

    post(route('driver.location.store', $service), [
        'latitude' => 6.2518,
        'longitude' => -75.5636,
        'is_manual' => false,
    ])->assertNotFound();
});
