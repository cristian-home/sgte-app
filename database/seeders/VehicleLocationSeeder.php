<?php

namespace Database\Seeders;

use App\Enums\ServiceStatus;
use App\Jobs\FetchServiceRoute;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds vehicle location data so the /gps/map view renders meaningful
 * markers immediately after `migrate:fresh --seed`.
 *
 * Strategy:
 *   1. Promote up to 4 existing services to today + open status (if no
 *      services already exist for today). Without this, /gps/map is
 *      empty because it filters by `service_date = today() AND
 *      service_status = open`.
 *   2. Create a service-scoped VehicleLocation per promoted service,
 *      placed *along that service's own origin→destination route* so the
 *      GPS marker sits on the line the map plots — not at an unrelated
 *      city.
 *   3. Also keep "vehicle-scoped historical" rows as a fallback dataset
 *      for the 24h fallback path, likewise placed on a real route.
 */
class VehicleLocationSeeder extends Seeder
{
    public function run(): void
    {
        if (VehicleLocation::query()->exists()) {
            return;
        }

        $vehicles = Vehicle::where('status', 'active')->get();

        if ($vehicles->isEmpty()) {
            return;
        }

        $admin = User::query()->where('email', 'admin@sgte.app')->first();

        // 1. Ensure there are 4 open services for today the map can plot.
        $tz = (string) config('app.operation_tz', 'America/Bogota');
        $today = Carbon::now($tz)->toDateString();
        $services = Service::query()
            ->where('service_status', ServiceStatus::Open)
            ->whereDate('service_date_local', $today)
            ->get();

        if ($services->count() < 4) {
            $needed = 4 - $services->count();
            $promoted = Service::query()
                ->whereNotIn('id', $services->pluck('id'))
                ->orderByDesc('id')
                ->limit($needed)
                ->get();

            foreach ($promoted as $service) {
                $newPlannedAt = CarbonImmutable::createFromFormat(
                    'Y-m-d H:i',
                    $today.' '.($service->planned_start_local ?? '08:00'),
                    $service->timezone ?: $tz,
                )->utc();

                $service->update([
                    'service_date_local' => $today,
                    'planned_start_at' => $newPlannedAt,
                    'service_status' => ServiceStatus::Open,
                ]);
            }

            $services = $services->merge($promoted);
        }

        // 1b. Fetch each service's road route up-front so the markers
        //     below sit on the real polyline. The route fetch is normally
        //     queued by the Service `created` hook and processed later by
        //     a worker — too late for this seeder — so run it inline now.
        //     Degrades gracefully: if the Routes API is unavailable (no
        //     key / offline) `route_geometry` stays null and the marker
        //     falls back to the straight origin→destination chord.
        foreach ($services as $service) {
            if (! empty($service->route_geometry)
                || empty($service->origin_coordinates)
                || empty($service->destination_coordinates)) {
                continue;
            }

            try {
                FetchServiceRoute::dispatchSync($service);
                $service->refresh();
            } catch (\Throwable $e) {
                // A failed route lookup must not abort seeding.
                report($e);
            }
        }

        // 2. Service-scoped current location, sitting somewhere along the
        //    service's own route so the marker reads as a vehicle in
        //    transit rather than a pin dropped on a random city.
        foreach ($services->values() as $index => $service) {
            $point = $this->pointAlongService(
                $service,
                fake()->randomFloat(2, 0.2, 0.8),
            );

            if ($point === null) {
                continue;
            }

            $isManual = $index % 3 === 2;

            VehicleLocation::create([
                'vehicle_id' => $service->vehicle_id,
                'service_id' => $service->id,
                'recorded_at' => Carbon::now()->subMinutes($index * 7),
                'latitude' => $point['lat'],
                'longitude' => $point['lng'],
                'accuracy' => $isManual ? null : fake()->randomFloat(2, 6, 30),
                'is_manual' => $isManual,
                'captured_by' => $admin?->id,
            ]);
        }

        // 3. Vehicle-scoped historical rows (no service_id) so the
        //    "Ubicaciones" index has variety + the 24h fallback path is
        //    populated. Placed earlier along the same routes so no row
        //    lands far from a service the vehicle actually ran.
        foreach ($services->values() as $index => $service) {
            $point = $this->pointAlongService(
                $service,
                fake()->randomFloat(2, 0.05, 0.4),
            );

            if ($point === null) {
                continue;
            }

            VehicleLocation::create([
                'vehicle_id' => $service->vehicle_id,
                'recorded_at' => Carbon::now()->subHours(fake()->numberBetween(2, 18)),
                'latitude' => $point['lat'],
                'longitude' => $point['lng'],
                'accuracy' => fake()->randomFloat(2, 6, 40),
                'is_manual' => false,
                'captured_by' => $admin?->id,
            ]);
        }
    }

    /**
     * A coordinate somewhere along the service's route — a vertex of the
     * real fetched polyline when one exists, otherwise a point on the
     * straight origin→destination chord. `$fraction` (0..1) picks how far
     * along the route the point sits. Returns null when the service has
     * no usable coordinates.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function pointAlongService(Service $service, float $fraction): ?array
    {
        $geometry = $service->route_geometry;

        if (is_array($geometry) && count($geometry) >= 2) {
            $vertex = $geometry[(int) round($fraction * (count($geometry) - 1))];

            if (is_array($vertex) && count($vertex) >= 2) {
                // route_geometry stores GeoJSON [lng, lat] pairs.
                return [
                    'lat' => round((float) $vertex[1], 8),
                    'lng' => round((float) $vertex[0], 8),
                ];
            }
        }

        $origin = $this->parseCoordinates($service->origin_coordinates);
        $destination = $this->parseCoordinates($service->destination_coordinates);

        if ($origin !== null && $destination !== null) {
            return [
                'lat' => round($origin['lat'] + ($destination['lat'] - $origin['lat']) * $fraction, 8),
                'lng' => round($origin['lng'] + ($destination['lng'] - $origin['lng']) * $fraction, 8),
            ];
        }

        return $origin ?? $destination;
    }

    /**
     * Parse a stored "lat,lng" coordinate string into a float pair.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function parseCoordinates(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parts = explode(',', $value);

        if (count($parts) !== 2) {
            return null;
        }

        $lat = trim($parts[0]);
        $lng = trim($parts[1]);

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }
}
