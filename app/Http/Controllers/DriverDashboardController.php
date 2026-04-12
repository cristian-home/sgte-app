<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DriverDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::REGISTER_SERVICE_TIMES->value);

        $driver = $request->user()->driver;

        $services = $driver
            ? Service::with(['vehicle', 'contract.thirdParty', 'originMunicipality', 'destinationMunicipality'])
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

        return redirect()->route('driver.dashboard');
    }
}
