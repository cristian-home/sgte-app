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
 * Dedicated admin/operator map view at /gps/map. Renders a Leaflet
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
            ->get(['id', 'service_date_local', 'planned_start_at', 'timezone', 'vehicle_id', 'driver_id']);

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
}
