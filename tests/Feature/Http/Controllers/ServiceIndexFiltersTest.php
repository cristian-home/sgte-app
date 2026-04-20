<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Contract;
use App\Models\Driver;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

/**
 * Regression for services-index-filter-expansion. Each filter is
 * exercised in isolation to confirm it narrows the result set exactly
 * as declared in ServiceController::index.
 */
beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    actingAs($user);
});

test('index filters by contract_id', function (): void {
    $contractA = Contract::factory()->create();
    $contractB = Contract::factory()->create();
    Service::factory()->count(2)->create(['contract_id' => $contractA->id]);
    Service::factory()->count(3)->create(['contract_id' => $contractB->id]);

    $response = getJson(route('services.index', [
        'filter[contract_id]' => $contractA->id,
    ]));

    $response->assertOk();
    expect(count($response->json('data')))->toBe(2);
});

test('index accepts comma-separated contract_id for multi-select filtering', function (): void {
    // Regression for services-index-filter-toolbar-migration: the toolbar
    // DataTableFacetedFilter is multi-select and joins selected values
    // with commas. AllowedFilter::exact must resolve "1,2" as
    // WHERE contract_id IN (1, 2).
    $contractA = Contract::factory()->create();
    $contractB = Contract::factory()->create();
    $contractC = Contract::factory()->create();

    Service::factory()->count(2)->create(['contract_id' => $contractA->id]);
    Service::factory()->count(3)->create(['contract_id' => $contractB->id]);
    Service::factory()->count(4)->create(['contract_id' => $contractC->id]);

    $response = getJson(route('services.index', [
        'filter[contract_id]' => $contractA->id.','.$contractB->id,
    ]));

    $response->assertOk();
    // A + B = 5 services; C's 4 are filtered out.
    expect(count($response->json('data')))->toBe(5);

    $returnedContractIds = array_unique(array_column($response->json('data'), 'contract_id'));
    sort($returnedContractIds);
    $expected = [$contractA->id, $contractB->id];
    sort($expected);
    expect($returnedContractIds)->toBe($expected);
});

test('index filters by driver_id', function (): void {
    $driverA = Driver::factory()->create();
    $driverB = Driver::factory()->create();
    Service::factory()->count(1)->create(['driver_id' => $driverA->id]);
    Service::factory()->count(4)->create(['driver_id' => $driverB->id]);

    $response = getJson(route('services.index', [
        'filter[driver_id]' => $driverB->id,
    ]));

    expect(count($response->json('data')))->toBe(4);
});

test('index filters by vehicle_id', function (): void {
    $vehicleA = Vehicle::factory()->create();
    $vehicleB = Vehicle::factory()->create();
    Service::factory()->count(3)->create(['vehicle_id' => $vehicleA->id]);
    Service::factory()->count(2)->create(['vehicle_id' => $vehicleB->id]);

    $response = getJson(route('services.index', [
        'filter[vehicle_id]' => $vehicleA->id,
    ]));

    expect(count($response->json('data')))->toBe(3);
});

test('index filters by origin_municipality_id', function (): void {
    $municipalityA = Municipality::factory()->create();
    $municipalityB = Municipality::factory()->create();
    Service::factory()->count(2)->create(['origin_municipality_id' => $municipalityA->id]);
    Service::factory()->count(1)->create(['origin_municipality_id' => $municipalityB->id]);

    $response = getJson(route('services.index', [
        'filter[origin_municipality_id]' => $municipalityA->id,
    ]));

    expect(count($response->json('data')))->toBe(2);
});

test('index filters by destination_municipality_id', function (): void {
    $municipalityA = Municipality::factory()->create();
    $municipalityB = Municipality::factory()->create();
    Service::factory()->count(1)->create(['destination_municipality_id' => $municipalityA->id]);
    Service::factory()->count(3)->create(['destination_municipality_id' => $municipalityB->id]);

    $response = getJson(route('services.index', [
        'filter[destination_municipality_id]' => $municipalityB->id,
    ]));

    expect(count($response->json('data')))->toBe(3);
});

test('index filters by date_from', function (): void {
    Service::factory()->create(['service_date' => '2026-01-05']);
    Service::factory()->create(['service_date' => '2026-02-15']);
    Service::factory()->create(['service_date' => '2026-03-20']);

    $response = getJson(route('services.index', [
        'filter[date_from]' => '2026-02-01',
    ]));

    expect(count($response->json('data')))->toBe(2);
});

test('index filters by date_to', function (): void {
    Service::factory()->create(['service_date' => '2026-01-05']);
    Service::factory()->create(['service_date' => '2026-02-15']);
    Service::factory()->create(['service_date' => '2026-03-20']);

    $response = getJson(route('services.index', [
        'filter[date_to]' => '2026-02-28',
    ]));

    expect(count($response->json('data')))->toBe(2);
});

test('index filters by a combined date range', function (): void {
    Service::factory()->create(['service_date' => '2026-01-05']);
    Service::factory()->create(['service_date' => '2026-02-15']);
    Service::factory()->create(['service_date' => '2026-03-20']);
    Service::factory()->create(['service_date' => '2026-05-01']);

    $response = getJson(route('services.index', [
        'filter[date_from]' => '2026-02-01',
        'filter[date_to]' => '2026-04-30',
    ]));

    expect(count($response->json('data')))->toBe(2);
});

test('index ships filter options for the combobox filters', function (): void {
    Contract::factory()->create(['active' => true]);
    Driver::factory()->create(['active' => true]);
    Vehicle::factory()->create();

    $response = get(route('services.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('services/index')
        ->has('filterContracts')
        ->has('filterDrivers')
        ->has('filterVehicles')
        ->has('filterMunicipalities')
    );
});

test('index date range filter handles the "this week" preset client-side range', function (): void {
    // Simulate the "Esta semana" preset: from Monday of this week to
    // Sunday of this week. The callback filter should include services
    // whose service_date falls inside the range and exclude others.
    $mondayThisWeek = Carbon::now()->startOfWeek()->toDateString();
    $sundayThisWeek = Carbon::now()->endOfWeek()->toDateString();

    Service::factory()->create(['service_date' => $mondayThisWeek]);
    Service::factory()->create(['service_date' => $sundayThisWeek]);
    Service::factory()->create([
        'service_date' => Carbon::now()->startOfWeek()->subDay()->toDateString(),
    ]);
    Service::factory()->create([
        'service_date' => Carbon::now()->endOfWeek()->addDay()->toDateString(),
    ]);

    $response = getJson(route('services.index', [
        'filter[date_from]' => $mondayThisWeek,
        'filter[date_to]' => $sundayThisWeek,
    ]));

    expect(count($response->json('data')))->toBe(2);
});
