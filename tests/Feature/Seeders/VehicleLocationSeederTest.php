<?php

use App\Enums\ServiceStatus;
use App\Enums\VehicleStatus;
use App\Jobs\FetchServiceRoute;
use App\Models\Service;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Database\Seeders\VehicleLocationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

// Fake the bus so creating a service doesn't run FetchServiceRoute — at
// real `migrate:fresh --seed` time that job is queued (not run), so
// `route_geometry` is still null when VehicleLocationSeeder executes.
beforeEach(fn () => Bus::fake());

test('service-scoped locations land on the origin-to-destination chord', function (): void {
    // Four open services for today, each with a known origin/destination
    // and no fetched route_geometry — the realistic state at seed time.
    $coords = [
        ['4.60971,-74.08175', '4.65000,-74.05000'],
        ['6.25184,-75.56359', '6.30000,-75.50000'],
        ['3.45160,-76.53200', '4.71099,-74.07210'],
        ['7.11935,-73.12270', '6.25184,-75.56359'],
    ];

    $services = collect($coords)->map(fn (array $pair): Service => Service::factory()->create([
        'vehicle_id' => Vehicle::factory()->create(['status' => VehicleStatus::Active]),
        'service_status' => ServiceStatus::Open,
        'service_date' => Carbon::today()->toDateString(),
        'origin_coordinates' => $pair[0],
        'destination_coordinates' => $pair[1],
    ]));

    expect(VehicleLocation::query()->count())->toBe(0);

    $this->seed(VehicleLocationSeeder::class);

    // The seeder fetches each service's route inline so markers can be
    // placed on the real polyline (faked here, hence the chord fallback).
    Bus::assertDispatchedSync(FetchServiceRoute::class);

    foreach ($services as $service) {
        $location = VehicleLocation::query()
            ->where('service_id', $service->id)
            ->first();

        expect($location)->not->toBeNull();

        [$oLat, $oLng] = array_map('floatval', explode(',', $service->origin_coordinates));
        [$dLat, $dLng] = array_map('floatval', explode(',', $service->destination_coordinates));

        // A point interpolated on the origin→destination chord always
        // falls inside their lat/lng bounding box.
        expect((float) $location->latitude)
            ->toBeGreaterThanOrEqual(min($oLat, $dLat) - 1e-6)
            ->toBeLessThanOrEqual(max($oLat, $dLat) + 1e-6);
        expect((float) $location->longitude)
            ->toBeGreaterThanOrEqual(min($oLng, $dLng) - 1e-6)
            ->toBeLessThanOrEqual(max($oLng, $dLng) + 1e-6);
    }
});

test('service-scoped location snaps to a vertex of the fetched route polyline', function (): void {
    $service = Service::factory()->create([
        'vehicle_id' => Vehicle::factory()->create(['status' => VehicleStatus::Active]),
        'service_status' => ServiceStatus::Open,
        'service_date' => Carbon::today()->toDateString(),
        'origin_coordinates' => '4.60971,-74.08175',
        'destination_coordinates' => '4.71099,-74.07210',
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
