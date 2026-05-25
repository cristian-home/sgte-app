<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/**
 * Regression for the vehicle-locations-json-parse-error-investigation.
 *
 * The /vehicle-locations index must return raw paginator JSON (not the
 * full Inertia HTML page) when the browser fetch sends
 * `Accept: application/json` — that's the contract `useServerTable`
 * depends on for its filter/sort/pagination refetches. If the
 * `wantsJson()` branch regresses, the frontend explodes with
 * `SyntaxError: JSON.parse: unexpected character at line 1 column 1
 * of the JSON data`.
 */
beforeEach(function (): void {
    config()->set('sgte.gps_enabled', true);
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    actingAs($user);
});

test('vehicle-locations index returns raw JSON paginator when Accept is application/json', function (): void {
    $vehicle = Vehicle::factory()->create();
    VehicleLocation::factory()->count(3)->create(['vehicle_id' => $vehicle->id]);

    $response = getJson(route('vehicle-locations.index'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/json');
    expect($response->json())
        ->toHaveKey('data')
        ->toHaveKey('current_page')
        ->toHaveKey('last_page')
        ->toHaveKey('total');
    expect($response->json('data'))->toHaveCount(3);
});

test('vehicle-locations index applies filters on the JSON response', function (): void {
    $vehicleA = Vehicle::factory()->create();
    $vehicleB = Vehicle::factory()->create();
    VehicleLocation::factory()->count(2)->create(['vehicle_id' => $vehicleA->id]);
    VehicleLocation::factory()->count(3)->create(['vehicle_id' => $vehicleB->id]);

    $response = getJson(route('vehicle-locations.index', [
        'filter[vehicle_id]' => $vehicleA->id,
    ]));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('vehicle-locations index returns HTML for a plain browser GET', function (): void {
    $response = $this->get(route('vehicle-locations.index'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/html');
});
