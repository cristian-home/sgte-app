<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\DriverDeclineServiceRequest;
use App\Models\IncidentType;
use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\User;
use App\Models\VehicleLocation;
use App\Notifications\DriverDeclinedServiceNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
     * REQ-012 pre-flight decline. The driver rejects the service before
     * confirmStart fires. We stamp driver_declined_at + the reason on the
     * service, write a ServiceIncident (severity=major, affects_billing=false)
     * pinned to the PREDECL incident type, and notify admin/ops so the row
     * can be reassigned. service_status stays 'open' — reassignment is a
     * separate ops action.
     */
    public function decline(DriverDeclineServiceRequest $request, Service $service): RedirectResponse
    {
        $driver = $request->user()->driver;
        abort_unless($driver && $service->driver_id === $driver->id, 403);
        abort_if($service->actual_start_time !== null, 422, 'El servicio ya tiene una hora de inicio registrada.');
        abort_if($service->driver_declined_at !== null, 422, 'Este servicio ya fue declinado.');

        $reason = trim((string) $request->input('reason_text'));
        $incidentTypeId = $request->integer('incident_type_id') ?: IncidentType::where('code', 'PREDECL')->value('id');

        $incident = DB::transaction(function () use ($service, $request, $reason, $incidentTypeId): ServiceIncident {
            $service->update([
                'driver_declined_at' => now(),
                'driver_decline_reason' => $reason,
            ]);

            return ServiceIncident::create([
                'service_id' => $service->id,
                'incident_type_id' => $incidentTypeId,
                'description' => $reason,
                'registrar_id' => $request->user()->id,
                'is_driver_report' => true,
                'reported_at' => now(),
                'affects_billing' => false,
                'additional_value' => null,
            ]);
        });

        $recipients = User::role([Role::SUPER_ADMIN->value, Role::ADMIN->value, Role::OPERATOR->value])->get();
        if ($recipients->isNotEmpty()) {
            $service->load(['vehicle', 'driver']);
            Notification::send($recipients, new DriverDeclinedServiceNotification($service, $reason));
        }

        activity()
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties([
                'source' => 'driver_preflight_decline',
                'incident_id' => $incident->id,
                'reason' => $reason,
            ])
            ->log('Conductor declinó el servicio antes del inicio');

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
