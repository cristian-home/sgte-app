<?php

namespace App\Support;

use App\Models\VehicleLocation;
use Illuminate\Support\Carbon;

/**
 * Resolves the latest known GPS location for a vehicle. Shared by the
 * /gps/map full view and the dashboard's live-vehicles widget so both
 * follow the same fallback rules.
 *
 * Lookup order: location scoped to a specific service (if provided) →
 * any location for the vehicle within `$fallbackHours` → null.
 */
class VehicleLocationResolver
{
    public static function latestForVehicle(
        int $vehicleId,
        ?int $serviceId = null,
        int $fallbackHours = 24,
    ): ?VehicleLocation {
        if ($serviceId !== null) {
            $scoped = VehicleLocation::query()
                ->where('service_id', $serviceId)
                ->orderByDesc('recorded_at')
                ->first();

            if ($scoped) {
                return $scoped;
            }
        }

        return VehicleLocation::query()
            ->where('vehicle_id', $vehicleId)
            ->where('recorded_at', '>=', Carbon::now()->subHours($fallbackHours))
            ->orderByDesc('recorded_at')
            ->first();
    }
}
