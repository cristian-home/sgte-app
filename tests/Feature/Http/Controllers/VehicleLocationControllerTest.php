<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Illuminate\Support\Carbon;

use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('index behaves as expected', function (): void {
    $vehicleLocations = VehicleLocation::factory()->count(3)->create();

    $response = get(route('vehicle-locations.index'));

    $response->assertOk();
});

test('create behaves as expected', function (): void {
    $response = get(route('vehicle-locations.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\VehicleLocationController::class,
        'store',
        \App\Http\Requests\VehicleLocationStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $vehicle = Vehicle::factory()->create();
    $recorded_at = Carbon::parse(fake()->dateTime());
    $latitude = fake()->latitude();
    $longitude = fake()->longitude();
    $is_manual = fake()->boolean();

    $response = post(route('vehicle-locations.store'), [
        'vehicle_id' => $vehicle->id,
        'recorded_at' => $recorded_at,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'is_manual' => $is_manual,
    ]);

    $vehicleLocations = VehicleLocation::query()
        ->where('vehicle_id', $vehicle->id)
        ->where('recorded_at', $recorded_at)
        ->where('latitude', $latitude)
        ->where('longitude', $longitude)
        ->where('is_manual', $is_manual)
        ->get();
    expect($vehicleLocations)->toHaveCount(1);
    $vehicleLocation = $vehicleLocations->first();

    $response->assertRedirect(route('vehicle-locations.index'));
});

test('show behaves as expected', function (): void {
    $vehicleLocation = VehicleLocation::factory()->create();

    $response = get(route('vehicle-locations.show', $vehicleLocation));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $vehicleLocation = VehicleLocation::factory()->create();

    $response = get(route('vehicle-locations.edit', $vehicleLocation));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\VehicleLocationController::class,
        'update',
        \App\Http\Requests\VehicleLocationUpdateRequest::class
    );

test('update redirects', function (): void {
    $vehicleLocation = VehicleLocation::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $recorded_at = Carbon::parse(fake()->dateTime());
    $latitude = fake()->latitude();
    $longitude = fake()->longitude();
    $is_manual = fake()->boolean();

    $response = put(route('vehicle-locations.update', $vehicleLocation), [
        'vehicle_id' => $vehicle->id,
        'recorded_at' => $recorded_at,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'is_manual' => $is_manual,
    ]);

    $vehicleLocation->refresh();

    $response->assertRedirect(route('vehicle-locations.index'));

    expect($vehicle->id)->toEqual($vehicleLocation->vehicle_id);
    expect($recorded_at->timestamp)->toEqual($vehicleLocation->recorded_at);
    expect($latitude)->toEqual($vehicleLocation->latitude);
    expect($longitude)->toEqual($vehicleLocation->longitude);
    expect($is_manual)->toEqual($vehicleLocation->is_manual);
});

test('destroy deletes and redirects', function (): void {
    $vehicleLocation = VehicleLocation::factory()->create();

    $response = delete(route('vehicle-locations.destroy', $vehicleLocation));

    $response->assertRedirect(route('vehicle-locations.index'));

    assertModelMissing($vehicleLocation);
});
