<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Service;
use App\Models\VehicleLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DriverDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::REGISTER_SERVICE_TIMES->value);

        $driver = $request->user()->driver;

        $services = $driver
            ? Service::with([
                'vehicle',
                'contract.thirdParty',
                'originMunicipality',
                'destinationMunicipality',
                'recentLocations' => fn ($q) => $q->orderByDesc('recorded_at')->limit(5),
                'recentLocations.capturedBy:id,name',
            ])
                ->withCount('serviceIncidents')
                ->where('driver_id', $driver->id)
                ->whereDate('service_date', today())
                ->orderBy('planned_start_time')
                ->get()
            : collect();

        return Inertia::render('driver/index', [
            'services' => $services,
            'driver' => $driver,
        ]);
    }

    public function confirmStart(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize(Permission::REGISTER_SERVICE_TIMES->value);

        $driver = $request->user()->driver;
        abort_unless($driver && $service->driver_id === $driver->id, 403);

        $service->update([
            'actual_start_time' => now()->format('H:i:s'),
        ]);

        $this->persistLocationIfProvided($service, $request);

        return redirect()->route('driver.dashboard');
    }

    public function confirmEnd(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize(Permission::REGISTER_SERVICE_TIMES->value);

        $driver = $request->user()->driver;
        abort_unless($driver && $service->driver_id === $driver->id, 403);

        $service->update([
            'actual_end_time' => now()->format('H:i:s'),
        ]);

        $this->persistLocationIfProvided($service, $request);

        return redirect()->route('driver.dashboard');
    }

    /**
     * Opportunistically persist a VehicleLocation if the confirmation
     * request carries coordinates. Failures never block the
     * confirmation — SRS §REQ-010 AC#4 explicitly mandates that GPS
     * unavailability does not block the operation.
     */
    protected function persistLocationIfProvided(Service $service, Request $request): void
    {
        if (! config('sgte.gps_enabled')) {
            return;
        }

        $lat = $request->input('latitude');
        $lng = $request->input('longitude');
        $isManual = $request->input('is_manual');

        if ($lat === null || $lng === null || $isManual === null) {
            return;
        }

        try {
            VehicleLocation::create([
                'vehicle_id' => $service->vehicle_id,
                'service_id' => $service->id,
                'recorded_at' => now(),
                'latitude' => $lat,
                'longitude' => $lng,
                'accuracy' => $request->input('accuracy'),
                'is_manual' => (bool) $isManual,
                'captured_by' => $request->user()?->id,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to persist GPS location alongside service confirmation', [
                'service_id' => $service->id,
                'user_id' => $request->user()?->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
