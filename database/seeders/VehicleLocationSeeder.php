<?php

namespace Database\Seeders;

use App\Enums\ServiceStatus;
use App\Jobs\FetchServiceRoute;
use App\Models\Service;
use App\Models\User;
use App\Models\VehicleLocation;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\Locations;
use Database\Seeders\Support\SeedClock;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Seeds vehicle GPS data deterministically so /gps/map and the 24h
 * fallback view both have meaningful markers right after
 * `migrate:fresh --seed` — no randomness.
 *
 * Strategy:
 *   1. Take today's services (ServiceSeeder already guarantees four).
 *      Ensure each has `route_geometry` cached so markers can sit on
 *      the real driving polyline; missing geometry falls back to the
 *      straight origin→destination chord (or the warehouse when the
 *      service has no coordinates).
 *   2. Service-scoped current location per today service:
 *        - Closed                 → at destination
 *        - In progress (actual_start_at set, end not reached)
 *                                 → fraction = elapsed / planned_duration
 *        - Open / future          → at the BODEGA_BOGOTA anchor
 *        - Every 3rd row (idx % 3 == 2) → forced to bodega + is_manual=true
 *   3. Historical vehicle-scoped rows (no service_id) for yesterday's
 *      and the day-before-yesterday's services, placed at the midpoint
 *      of their route — populates the 24h fallback list with realistic
 *      tracks.
 */
class VehicleLocationSeeder extends Seeder
{
    public function run(): void
    {
        if (VehicleLocation::query()->exists()) {
            return;
        }

        $admin = User::query()->where('email', 'admin@sgte.app')->first();

        $today = SeedClock::dateString(0);

        $todayServices = Service::query()
            ->whereDate('service_date_local', $today)
            ->orderBy('planned_start_at')
            ->get();

        if ($todayServices->isEmpty()) {
            return;
        }

        $this->ensureRoutes($todayServices);

        $now = CarbonImmutable::now('UTC');

        foreach ($todayServices->values() as $index => $service) {
            // Every 3rd row is a dispatcher-pinned manual location at
            // the warehouse — exercises the is_manual=true UI surface.
            $isManual = $index % 3 === 2;

            $point = $isManual
                ? Locations::bodegaCoordinates()
                : $this->positionForStatus($service, $now);

            if ($point === null) {
                continue;
            }

            VehicleLocation::create([
                'vehicle_id' => $service->vehicle_id,
                'service_id' => $service->id,
                'recorded_at' => $this->recordedAtFor($service, $now),
                'latitude' => round($point['lat'], 8),
                'longitude' => round($point['lng'], 8),
                'accuracy' => $isManual ? null : 12.0,
                'is_manual' => $isManual,
                'captured_by' => $admin?->id,
            ]);
        }

        // Historical vehicle-scoped (no service_id) rows for the last
        // two operation days.
        $historicalServices = Service::query()
            ->whereIn('service_date_local', [
                SeedClock::dateString(-1),
                SeedClock::dateString(-2),
            ])
            ->orderBy('planned_start_at')
            ->get();

        $this->ensureRoutes($historicalServices);

        foreach ($historicalServices as $service) {
            $point = $this->pointAlongService($service, 0.5);
            if ($point === null) {
                continue;
            }

            $midpoint = $service->planned_start_at
                ? CarbonImmutable::instance($service->planned_start_at)
                    ->addMinutes((int) (((int) $service->planned_duration) / 2))
                : $now;

            VehicleLocation::create([
                'vehicle_id' => $service->vehicle_id,
                'service_id' => null,
                'recorded_at' => $midpoint,
                'latitude' => round($point['lat'], 8),
                'longitude' => round($point['lng'], 8),
                'accuracy' => 18.0,
                'is_manual' => false,
                'captured_by' => $admin?->id,
            ]);
        }
    }

    /**
     * Inline FetchServiceRoute for any service still missing geometry,
     * so subsequent marker placement uses the real polyline. Degrades
     * gracefully when Google is unreachable (no key / offline).
     */
    private function ensureRoutes(iterable $services): void
    {
        foreach ($services as $service) {
            if (! empty($service->route_geometry)
                || empty($service->origin_coordinates)
                || empty($service->destination_coordinates)) {
                continue;
            }

            try {
                FetchServiceRoute::dispatchSync($service);
                $service->refresh();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Pick a marker position based on the service's lifecycle.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function positionForStatus(Service $service, CarbonImmutable $now): ?array
    {
        if ($service->service_status === ServiceStatus::Closed) {
            return $this->pointAlongService($service, 1.0);
        }

        $start = $service->actual_start_at ?? $service->planned_start_at;
        $duration = max(1, (int) $service->planned_duration);

        if ($start === null) {
            return Locations::bodegaCoordinates();
        }

        $startImmutable = CarbonImmutable::instance($start);
        if ($startImmutable->gt($now)) {
            // Future today: hasn't departed yet → at the warehouse.
            return Locations::bodegaCoordinates();
        }

        $elapsedMinutes = $now->diffInMinutes($startImmutable, true);
        $fraction = max(0.0, min(0.95, $elapsedMinutes / $duration));

        return $this->pointAlongService($service, $fraction);
    }

    private function recordedAtFor(Service $service, CarbonImmutable $now): CarbonImmutable
    {
        if ($service->service_status === ServiceStatus::Closed) {
            return $service->actual_end_at
                ? CarbonImmutable::instance($service->actual_end_at)
                : $now;
        }

        return $now;
    }

    /**
     * Vertex of the cached polyline (preferred) or a point along the
     * straight origin→destination chord. `$fraction ∈ [0, 1]`.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function pointAlongService(Service $service, float $fraction): ?array
    {
        $geometry = $service->route_geometry;

        if (is_array($geometry) && count($geometry) >= 2) {
            $vertex = $geometry[(int) round($fraction * (count($geometry) - 1))];

            if (is_array($vertex) && count($vertex) >= 2) {
                return [
                    'lat' => (float) $vertex[1],
                    'lng' => (float) $vertex[0],
                ];
            }
        }

        $origin = $this->parseCoords($service->origin_coordinates);
        $destination = $this->parseCoords($service->destination_coordinates);

        if ($origin !== null && $destination !== null) {
            return [
                'lat' => $origin['lat'] + ($destination['lat'] - $origin['lat']) * $fraction,
                'lng' => $origin['lng'] + ($destination['lng'] - $origin['lng']) * $fraction,
            ];
        }

        return $origin ?? $destination;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function parseCoords(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parts = explode(',', $value);
        if (count($parts) !== 2) {
            return null;
        }

        [$lat, $lng] = [trim($parts[0]), trim($parts[1])];

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }
}
