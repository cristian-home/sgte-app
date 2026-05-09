<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Contract;
use App\Models\Driver;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * Coords-source/accuracy storage and validation. Companion to the
 * Geocoding v6 + pin picker rollout (2026-05-09): origin/destination
 * coordinates now travel with a `_source` discriminator ('mapbox' or
 * 'manual') and an optional `_accuracy` string. Both columns are
 * nullable for legacy records.
 */
beforeEach(function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);
});

function buildStoreCoordsPayload(array $overrides = []): array
{
    $contract = Contract::factory()->create([
        'active' => true,
        'start_date' => Carbon::now()->subMonth(),
        'end_date' => Carbon::now()->addMonth(),
    ]);
    $vehicle = Vehicle::factory()->create(['is_third_party' => false]);
    $driver = Driver::factory()->create(['license_due_date' => Carbon::now()->addYear()]);
    $origin = Municipality::factory()->create();
    $destination = Municipality::factory()->create();

    return array_merge([
        'contract_id' => $contract->id,
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date' => Carbon::now()->toDateString(),
        'origin_municipality_id' => $origin->id,
        'origin_address' => 'Calle 41A Sur #83-17',
        'origin_coordinates' => '4.5816950,-74.1784720',
        'origin_coordinates_source' => 'mapbox',
        'origin_coordinates_accuracy' => 'rooftop',
        'destination_municipality_id' => $destination->id,
        'destination_address' => 'Carrera 11 #82-71',
        'destination_coordinates' => '4.6679000,-74.0541000',
        'destination_coordinates_source' => 'manual',
        'destination_coordinates_accuracy' => null,
        'planned_start_time' => '08:00',
        'planned_duration' => 120,
        'unit_value' => 250000,
        'quantity' => 1,
        'billing_group' => 'Grupo A',
        'payment_method' => 'credit',
        'service_status' => 'open',
    ], $overrides);
}

test('store accepts mapbox source with accuracy', function (): void {
    post(route('services.store'), buildStoreCoordsPayload())
        ->assertRedirect(route('services.index'));

    $service = Service::query()->latest('id')->first();
    expect($service->origin_coordinates_source)->toBe('mapbox');
    expect($service->origin_coordinates_accuracy)->toBe('rooftop');
});

test('store accepts manual source with null accuracy', function (): void {
    post(route('services.store'), buildStoreCoordsPayload())
        ->assertRedirect(route('services.index'));

    $service = Service::query()->latest('id')->first();
    expect($service->destination_coordinates_source)->toBe('manual');
    expect($service->destination_coordinates_accuracy)->toBeNull();
});

test('store rejects malformed coordinates with 422', function (): void {
    post(
        route('services.store'),
        buildStoreCoordsPayload(['origin_coordinates' => 'not-a-coord'])
    )->assertStatus(302)->assertSessionHasErrors(['origin_coordinates']);
});

test('store rejects unknown coordinate source with 422', function (): void {
    post(
        route('services.store'),
        buildStoreCoordsPayload(['origin_coordinates_source' => 'google'])
    )->assertStatus(302)->assertSessionHasErrors(['origin_coordinates_source']);
});

test('store accepts null source/accuracy for legacy compat', function (): void {
    post(
        route('services.store'),
        buildStoreCoordsPayload([
            'origin_coordinates' => null,
            'origin_coordinates_source' => null,
            'origin_coordinates_accuracy' => null,
            'destination_coordinates' => null,
            'destination_coordinates_source' => null,
            'destination_coordinates_accuracy' => null,
        ])
    )->assertRedirect(route('services.index'));

    $service = Service::query()->latest('id')->first();
    expect($service->origin_coordinates)->toBeNull();
    expect($service->origin_coordinates_source)->toBeNull();
});

test('update can switch source from mapbox to manual', function (): void {
    $service = Service::factory()->create([
        'origin_coordinates' => '4.5816950,-74.1784720',
        'origin_coordinates_source' => 'mapbox',
        'origin_coordinates_accuracy' => 'rooftop',
    ]);

    $payload = buildStoreCoordsPayload([
        'origin_coordinates' => '4.6000000,-74.1000000',
        'origin_coordinates_source' => 'manual',
        'origin_coordinates_accuracy' => null,
    ]);

    put(route('services.update', $service), $payload)
        ->assertRedirect();

    $service->refresh();
    expect($service->origin_coordinates_source)->toBe('manual');
    expect($service->origin_coordinates_accuracy)->toBeNull();
    expect($service->origin_coordinates)->toBe('4.6000000,-74.1000000');
});
