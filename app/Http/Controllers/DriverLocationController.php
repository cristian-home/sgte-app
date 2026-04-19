<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Service;
use App\Models\VehicleLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Driver-side vehicle-location registration. Drivers register from
 * the inline "Ubicación GPS" card on the /driver dashboard. Cross-
 * driver access is rejected with 403 (matches the pattern in
 * DriverDashboardController::confirmStart).
 */
class DriverLocationController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize(Permission::REGISTER_VEHICLE_LOCATION->value);

        $user = $request->user();
        $driver = $user?->driver;
        abort_if(! $driver || $service->driver_id !== $driver->id, 403);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_manual' => ['required', 'boolean'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        VehicleLocation::create([
            'vehicle_id' => $service->vehicle_id,
            'service_id' => $service->id,
            'recorded_at' => now(),
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? null,
            'is_manual' => (bool) $data['is_manual'],
            'captured_by' => $user->id,
        ]);

        return redirect()->route('driver.dashboard');
    }
}
