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
use App\Support\ServiceDocumentChecks;
use App\Support\Tz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $operationTz = Tz::operation();
        $today = Carbon::now($operationTz)->toDateString();
        $selectedDate = $this->resolveSelectedDate($request, $today);

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
                ->whereDate('service_date_local', $selectedDate)
                ->orderBy('planned_start_at')
                ->get()
            : collect();

        return Inertia::render('driver/index', [
            'services' => $services,
            'driver' => $driver,
            'selectedDate' => $selectedDate,
            'isToday' => $selectedDate === $today,
        ]);
    }

    /**
     * Read the optional `?date=Y-m-d` query param. Returns the request
     * value when valid; otherwise falls back to today in operation TZ.
     */
    protected function resolveSelectedDate(Request $request, string $today): string
    {
        $raw = $request->query('date');
        if (! is_string($raw)) {
            return $today;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            return $today;
        }
        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $raw, Tz::operation());
        } catch (\Throwable) {
            return $today;
        }
        if (! $parsed instanceof Carbon || $parsed->format('Y-m-d') !== $raw) {
            return $today;
        }

        return $parsed->format('Y-m-d');
    }

    /**
     * Reject confirm-start / confirm-end / decline when the service's
     * day (in its own timezone) doesn't match operation TZ today. Drivers
     * navigate past/future days for visibility only — retroactive
     * registration goes through the operator flow with
     * `manual_entry_justification`.
     */
    protected function assertActionAllowedToday(Service $service): void
    {
        $today = Carbon::now(Tz::operation())->toDateString();
        $serviceDate = $service->service_date_local instanceof \DateTimeInterface
            ? $service->service_date_local->format('Y-m-d')
            : (string) $service->service_date_local;

        if ($serviceDate !== $today) {
            abort(422, 'Solo puedes actuar sobre servicios del día actual.');
        }
    }

    public function confirmStart(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize(Permission::REGISTER_SERVICE_TIMES->value);

        $driver = $request->user()->driver;
        abort_unless($driver && $service->driver_id === $driver->id, 403);

        $this->assertActionAllowedToday($service);
        $this->assertDocumentsStillValid($service);

        $service->update([
            'actual_start_at' => now(),
        ]);

        $this->persistLocationIfProvided($service, $request);

        return redirect()->route('driver.dashboard');
    }

    /**
     * REQ-004 / REQ-005 execution-time re-check. Documents are validated
     * against service.service_date_local (NOT today) — a service scheduled
     * days ago might have been created while papers were valid, but the
     * driver must still have valid paperwork as-of the service date at
     * the moment they start. 422s with the first reason so the driver
     * sees a clear, actionable message.
     */
    protected function assertDocumentsStillValid(Service $service): void
    {
        $operationTz = $service->timezone ?: (string) config('app.operation_tz', 'America/Bogota');
        $serviceDate = $service->service_date_local instanceof \DateTimeInterface
            ? Carbon::parse($service->service_date_local->format('Y-m-d'), $operationTz)
            : Carbon::parse((string) $service->service_date_local, $operationTz);

        $vehicle = $service->vehicle;
        $driver = $service->driver;

        if ($vehicle === null || $driver === null) {
            return;
        }

        $reasons = array_merge(
            ServiceDocumentChecks::vehicleDocumentsValid($vehicle, $serviceDate),
            ServiceDocumentChecks::driverLicenseValid($driver, $vehicle, $serviceDate),
        );

        if ($reasons !== []) {
            abort(422, $reasons[0].' Contacta a Operaciones.');
        }
    }

    public function confirmEnd(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize(Permission::REGISTER_SERVICE_TIMES->value);

        $driver = $request->user()->driver;
        abort_unless($driver && $service->driver_id === $driver->id, 403);

        $this->assertActionAllowedToday($service);

        $service->update([
            'actual_end_at' => now(),
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
        $this->assertActionAllowedToday($service);
        abort_if($service->actual_start_at !== null, 422, 'El servicio ya tiene una hora de inicio registrada.');
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
