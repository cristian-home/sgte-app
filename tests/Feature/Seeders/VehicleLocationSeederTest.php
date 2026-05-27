<?php

use App\Enums\ServiceStatus;
use App\Enums\VehicleStatus;
use App\Models\Service;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\Locations;
use Database\Seeders\VehicleLocationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

// Fake the bus so the Service::booted() created-hook doesn't actually
// dispatch FetchServiceRoute during test setup. The seeder itself no
// longer calls the job — route geometry comes pre-populated from the
// CuratedRoutes cache (ServiceSeeder) or stays null for tests that
// exercise the straight-line fallback.
beforeEach(fn () => Bus::fake());

test('in-progress services place their marker on the origin-to-destination chord', function (): void {
    // Open service started 30 min ago with a 60-min planned duration —
    // i.e. roughly half-way through, so the seeder should interpolate a
    // point along the chord (no route_geometry → straight-line fallback).
    $startedAt = CarbonImmutable::now('UTC')->subMinutes(30);

    $service = Service::factory()->create([
        'vehicle_id' => Vehicle::factory()->create(['status' => VehicleStatus::Active]),
        'service_status' => ServiceStatus::Open,
        'service_date' => Carbon::today()->toDateString(),
        'origin_coordinates' => '4.60971,-74.08175',
        'destination_coordinates' => '4.71099,-74.07210',
        'planned_start_at' => $startedAt,
        'planned_duration' => 60,
        'actual_start_at' => $startedAt,
    ]);

    $this->seed(VehicleLocationSeeder::class);

    $location = VehicleLocation::query()
        ->where('service_id', $service->id)
        ->first();

    expect($location)->not->toBeNull();

    [$oLat, $oLng] = array_map('floatval', explode(',', $service->origin_coordinates));
    [$dLat, $dLng] = array_map('floatval', explode(',', $service->destination_coordinates));

    expect((float) $location->latitude)
        ->toBeGreaterThanOrEqual(min($oLat, $dLat) - 1e-6)
        ->toBeLessThanOrEqual(max($oLat, $dLat) + 1e-6);
    expect((float) $location->longitude)
        ->toBeGreaterThanOrEqual(min($oLng, $dLng) - 1e-6)
        ->toBeLessThanOrEqual(max($oLng, $dLng) + 1e-6);
});

test('services that have not departed yet are pinned at the bodega anchor', function (): void {
    // Open service planned for later today, with no `actual_start_at` —
    // a vehicle that hasn't left the warehouse yet. The seeder should
    // place its current location at BODEGA_BOGOTA rather than on a route
    // the vehicle isn't actually driving yet.
    $service = Service::factory()->create([
        'vehicle_id' => Vehicle::factory()->create(['status' => VehicleStatus::Active]),
        'service_status' => ServiceStatus::Open,
        'service_date' => Carbon::today()->toDateString(),
        'origin_coordinates' => '4.60971,-74.08175',
        'destination_coordinates' => '4.71099,-74.07210',
        'planned_start_at' => CarbonImmutable::now('UTC')->addHours(2),
        'planned_duration' => 60,
        'actual_start_at' => null,
    ]);

    $this->seed(VehicleLocationSeeder::class);

    $location = VehicleLocation::query()
        ->where('service_id', $service->id)
        ->first();

    expect($location)->not->toBeNull();

    $bodega = Locations::bodegaCoordinates();

    expect((float) $location->latitude)->toEqualWithDelta($bodega['lat'], 1e-6);
    expect((float) $location->longitude)->toEqualWithDelta($bodega['lng'], 1e-6);
});

test('closed services place the marker at the destination', function (): void {
    $start = CarbonImmutable::now('UTC')->subHours(3);

    $service = Service::factory()->create([
        'vehicle_id' => Vehicle::factory()->create(['status' => VehicleStatus::Active]),
        'service_status' => ServiceStatus::Closed,
        'service_date' => Carbon::today()->toDateString(),
        'origin_coordinates' => '4.60971,-74.08175',
        'destination_coordinates' => '4.71099,-74.07210',
        'planned_start_at' => $start,
        'planned_duration' => 60,
        'actual_start_at' => $start,
        'actual_end_at' => $start->addHour(),
    ]);

    $this->seed(VehicleLocationSeeder::class);

    $location = VehicleLocation::query()
        ->where('service_id', $service->id)
        ->first();

    expect($location)->not->toBeNull();
    expect((float) $location->latitude)->toEqualWithDelta(4.71099, 1e-4);
    expect((float) $location->longitude)->toEqualWithDelta(-74.07210, 1e-4);
});

test('service-scoped location snaps to a vertex of the fetched route polyline', function (): void {
    $start = CarbonImmutable::now('UTC')->subMinutes(15);

    $service = Service::factory()->create([
        'vehicle_id' => Vehicle::factory()->create(['status' => VehicleStatus::Active]),
        'service_status' => ServiceStatus::Open,
        'service_date' => Carbon::today()->toDateString(),
        'origin_coordinates' => '4.60971,-74.08175',
        'destination_coordinates' => '4.71099,-74.07210',
        'planned_start_at' => $start,
        'planned_duration' => 60,
        'actual_start_at' => $start,
    ]);

    // route_geometry must be written past the model's saving hook, which
    // wipes route cache fields on every save (see ServiceRouteCacheTest).
    $geometry = [
        [-74.08175, 4.60971],
        [-74.07800, 4.64000],
        [-74.07500, 4.68000],
        [-74.07210, 4.71099],
    ];
    Service::query()->whereKey($service->id)->update([
        'route_geometry' => json_encode($geometry),
    ]);

    $this->seed(VehicleLocationSeeder::class);

    $location = VehicleLocation::query()
        ->where('service_id', $service->id)
        ->first();

    expect($location)->not->toBeNull();

    $vertices = collect($geometry)->map(fn (array $v): array => [
        round($v[1], 8),
        round($v[0], 8),
    ]);

    expect($vertices->contains([
        round((float) $location->latitude, 8),
        round((float) $location->longitude, 8),
    ]))->toBeTrue();
});
