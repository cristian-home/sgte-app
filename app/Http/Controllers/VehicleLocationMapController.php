<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\VehicleLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dedicated admin/operator map view at /gps/map. Renders a Google
 * map with one marker per active service for today, using the most
 * recent VehicleLocation as the marker coordinates. Service-scoped
 * location preferred; falls back to the last 24 hours of any
 * location for the vehicle.
 */
class VehicleLocationMapController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_VEHICLE_LOCATIONS->value);

        $operationTz = (string) config('app.operation_tz', 'America/Bogota');
        $today = Carbon::now($operationTz)->toDateString();

        $services = Service::query()
            ->where('service_status', ServiceStatus::Open)
            ->whereDate('service_date_local', $today)
            ->with([
                'vehicle:id,plate',
                'driver:id,first_name,first_lastname',
            ])
            ->orderByDesc('service_date_local')
            ->get([
                'id',
                'service_date_local',
                'planned_start_at',
                'timezone',
                'vehicle_id',
                'driver_id',
                'origin_coordinates',
                'destination_coordinates',
                'route_geometry',
                'route_distance_m',
                'route_duration_s',
            ]);

        $activeServices = $services->map(function (Service $service): array {
            $location = $this->resolveLatestLocation($service);

            return [
                'service_id' => $service->id,
                'vehicle_plate' => $service->vehicle?->plate,
                'driver_name' => $service->driver
                    ? trim(($service->driver->first_name ?? '').' '.($service->driver->first_lastname ?? ''))
                    : null,
                'location' => $location ? [
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'accuracy' => $location->accuracy !== null ? (float) $location->accuracy : null,
                    'is_manual' => (bool) $location->is_manual,
                    'recorded_at' => $location->recorded_at?->toIso8601String(),
                ] : null,
                'origin' => $this->parseCoordPair($service->origin_coordinates),
                'destination' => $this->parseCoordPair($service->destination_coordinates),
                'route' => $this->geometryToLatLngs($service->route_geometry),
                'route_distance_m' => $service->route_distance_m,
                'route_duration_s' => $service->route_duration_s,
            ];
        })->values()->all();

        return Inertia::render('gps/map', [
            'activeServices' => $activeServices,
        ]);
    }

    /**
     * Service-scoped location first; fall back to any location for
     * this vehicle within the last 24 hours.
     */
    protected function resolveLatestLocation(Service $service): ?VehicleLocation
    {
        $scoped = VehicleLocation::query()
            ->where('service_id', $service->id)
            ->orderByDesc('recorded_at')
            ->first();

        if ($scoped) {
            return $scoped;
        }

        return VehicleLocation::query()
            ->where('vehicle_id', $service->vehicle_id)
            ->where('recorded_at', '>=', now()->subDay())
            ->orderByDesc('recorded_at')
            ->first();
    }

    /**
     * Parse a `lat,lng` string into a { latitude, longitude } pair,
     * returning null when the value is missing or malformed.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    private function parseCoordPair(?string $value): ?array
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

        return ['latitude' => (float) $lat, 'longitude' => (float) $lng];
    }

    /**
     * Convert a GeoJSON LineString (array of [lng, lat] pairs) into
     * { latitude, longitude } pairs for the client map. Returns null
     * when the geometry hasn't been fetched yet.
     *
     * @param  array<int, array{0: float|int, 1: float|int}>|null  $geometry
     * @return array<int, array{latitude: float, longitude: float}>|null
     */
    private function geometryToLatLngs(?array $geometry): ?array
    {
        if (! $geometry) {
            return null;
        }

        $pairs = [];
        foreach ($geometry as $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }
            $pairs[] = [
                'latitude' => (float) $point[1],
                'longitude' => (float) $point[0],
            ];
        }

        return $pairs === [] ? null : $pairs;
    }
}
