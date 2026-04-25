<?php

namespace App\Http\Controllers;

use App\Enums\DayStatusEnum;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\VehicleStatus;
use App\Http\Requests\ServiceStoreRequest;
use App\Http\Requests\ServiceUpdateRequest;
use App\Models\Contract;
use App\Models\DayStatus;
use App\Models\Driver;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\Vehicle;
use App\Notifications\ServiceAssignedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize(Permission::VIEW_SERVICES->value);

        $services = QueryBuilder::for(Service::class)
            ->with(['contract', 'vehicle', 'driver'])
            ->allowedIncludes(['invoice'])
            ->allowedFilters([
                AllowedFilter::callback('search', fn (Builder $query, $value) => $query->searchWithRelevance($value)),
                AllowedFilter::callback('service_date', fn (Builder $query, $value) => $query->whereDate('service_date_local', $value)),
                AllowedFilter::exact('origin_municipality_id'),
                AllowedFilter::exact('destination_municipality_id'),
                AllowedFilter::exact('service_status'),
                AllowedFilter::exact('payment_method'),
                AllowedFilter::exact('contract_id'),
                AllowedFilter::exact('driver_id'),
                AllowedFilter::exact('vehicle_id'),
                AllowedFilter::callback('date_from', fn (Builder $query, $value) => $query->whereDate('service_date_local', '>=', $value)),
                AllowedFilter::callback('date_to', fn (Builder $query, $value) => $query->whereDate('service_date_local', '<=', $value)),
            ])
            ->allowedSorts(['service_date_local', 'unit_value', 'service_status'])
            ->paginate($request->perPage())
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($services);
        }

        return Inertia::render('services/index', [
            'services' => $services,
            ...$this->indexFilterOptions(),
        ]);
    }

    /**
     * Option data used by the /services filter bar — contract, driver,
     * vehicle, and municipality comboboxes. Scoped to "active" or
     * in-use rows so the picker stays useful as the catalog grows.
     *
     * @return array<string, mixed>
     */
    private function indexFilterOptions(): array
    {
        return [
            'filterContracts' => Contract::query()
                ->where('active', true)
                ->with('thirdParty:id,identification_number,first_name,first_lastname,company_name,is_natural_person')
                ->orderBy('contract_number')
                ->get(['id', 'contract_number', 'third_party_id']),
            'filterDrivers' => Driver::query()
                ->where('active', true)
                ->orderBy('first_lastname')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'first_lastname', 'identification_number']),
            'filterVehicles' => Vehicle::query()
                ->where('status', VehicleStatus::Active)
                ->orderBy('plate')
                ->get(['id', 'plate', 'is_third_party']),
            'filterMunicipalities' => Municipality::query()
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'department_id']),
        ];
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_SERVICES->value);

        $prefill = array_filter($request->only(['vehicle_id', 'planned_start_time', 'service_date']));

        return Inertia::render('services/create', [
            ...$this->formReferenceData(),
            'prefill' => ! empty($prefill) ? $prefill : null,
        ]);
    }

    public function store(ServiceStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_SERVICES->value);
        $service = Service::create($request->validated());

        // REQ-009: tag retroactive closed entries so /audit-log can
        // filter them apart from services closed via the driver
        // workflow. Only fires when the gate in ServiceStoreRequest
        // passed — i.e. service_date < today && status = closed &&
        // a justification was supplied.
        $justification = trim((string) $request->input('manual_entry_justification'));
        if ($justification !== '') {
            activity()
                ->performedOn($service)
                ->causedBy($request->user())
                ->withProperties([
                    'source' => 'retroactive_entry',
                    'manual_entry_justification' => $justification,
                    'service_date_local' => $service->service_date_local instanceof \DateTimeInterface
                        ? $service->service_date_local->format('Y-m-d')
                        : (string) $service->service_date_local,
                    'timezone' => (string) $service->timezone,
                ])
                ->log('Registro retroactivo de servicio cerrado');
        }

        if ($service->driver_id) {
            $driverUser = Driver::find($service->driver_id)?->user;
            if ($driverUser) {
                $service->load('vehicle');
                $driverUser->notify(new ServiceAssignedNotification($service));
            }
        }

        return redirect()->route('services.index');
    }

    public function show(Request $request, Service $service): Response
    {
        Gate::authorize(Permission::VIEW_SERVICES->value);

        $service->load([
            'contract.thirdParty',
            'vehicle.thirdParty',
            'driver',
            'originMunicipality.department',
            'destinationMunicipality.department',
            'invoice',
            'serviceIncidents.incidentType',
            'serviceIncidents.registrar',
        ]);
        $service->loadCount('serviceIncidents');

        $dayStatus = DayStatus::with('executor')->whereDate('date', $service->service_date_local)->first();

        // Dedicated payload for the Novedades card — limits to the last
        // 5 incidents and pins the ordering. The full serviceIncidents
        // relation on $service still carries everything for any card
        // that needs it.
        $recentIncidents = \App\Models\ServiceIncident::query()
            ->where('service_id', $service->id)
            ->with([
                'incidentType:id,name,severity',
                'registrar:id,name',
            ])
            ->orderByDesc('reported_at')
            ->limit(5)
            ->get([
                'id',
                'service_id',
                'incident_type_id',
                'registrar_id',
                'reported_at',
                'is_driver_report',
                'affects_billing',
            ]);

        return Inertia::render('services/show', [
            'service' => $service,
            'dayStatus' => $dayStatus,
            'recentIncidents' => $recentIncidents,
        ]);
    }

    public function edit(Request $request, Service $service): Response
    {
        if (Gate::none([Permission::UPDATE_PROJECTED_SERVICES->value, Permission::UPDATE_EXECUTED_SERVICES->value])) {
            abort(403);
        }

        $service->load([
            'contract',
            'vehicle.thirdParty',
            'driver',
            'originMunicipality.department',
            'destinationMunicipality.department',
        ]);
        $service->loadCount('serviceIncidents');

        $dayStatus = DayStatus::whereDate('date', $service->service_date_local)->first();

        return Inertia::render('services/edit', [
            'service' => $service,
            'dayStatus' => $dayStatus,
            'canEditExecuted' => auth()->user()->can(Permission::UPDATE_EXECUTED_SERVICES->value),
            'isAdmin' => auth()->user()->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN]),
            ...$this->formReferenceData(),
        ]);
    }

    public function update(ServiceUpdateRequest $request, Service $service): RedirectResponse
    {
        if (Gate::none([Permission::UPDATE_PROJECTED_SERVICES->value, Permission::UPDATE_EXECUTED_SERVICES->value])) {
            abort(403);
        }

        $validated = $request->validated();
        $justification = $validated['justification'] ?? null;
        unset($validated['justification']);

        // REQ-009 reopen invariant: capture before-state so the
        // activity-log entry can name which actual_*_time fields were
        // cleared or set during the status transition.
        $statusBefore = $service->service_status instanceof \App\Enums\ServiceStatus
            ? $service->service_status->value
            : (string) $service->service_status;
        $actualStartBefore = $service->actual_start_at;
        $actualEndBefore = $service->actual_end_at;

        $service->update($validated);
        $service->refresh();

        $statusAfter = $service->service_status instanceof \App\Enums\ServiceStatus
            ? $service->service_status->value
            : (string) $service->service_status;

        if ($statusBefore !== $statusAfter) {
            $clearedFields = [];
            $setFields = [];

            if ($actualStartBefore !== null && $service->actual_start_at === null) {
                $clearedFields[] = 'actual_start_at';
            }
            if ($actualEndBefore !== null && $service->actual_end_at === null) {
                $clearedFields[] = 'actual_end_at';
            }
            if ($actualStartBefore === null && $service->actual_start_at !== null) {
                $setFields[] = 'actual_start_at';
            }
            if ($actualEndBefore === null && $service->actual_end_at !== null) {
                $setFields[] = 'actual_end_at';
            }

            activity()
                ->performedOn($service)
                ->causedBy(auth()->user())
                ->withProperties([
                    'status_from' => $statusBefore,
                    'status_to' => $statusAfter,
                    'cleared_fields' => $clearedFields,
                    'set_fields' => $setFields,
                ])
                ->log('Servicio cambió de estado');
        }

        $dayStatus = DayStatus::whereDate('date', $service->service_date_local)->first();

        if ($dayStatus?->status === DayStatusEnum::Executed && $justification) {
            activity()
                ->performedOn($service)
                ->causedBy(auth()->user())
                ->withProperties([
                    'justification' => $justification,
                    'edited_on_executed_day' => true,
                ])
                ->log('Servicio editado en día ejecutado');
        }

        return redirect()->route('services.index');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_SERVICES->value);

        $dayStatus = DayStatus::whereDate('date', $service->service_date_local)->first();

        if ($dayStatus?->status === DayStatusEnum::Executed) {
            if (! auth()->user()->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])) {
                abort(403);
            }
        }

        $service->delete();

        return redirect()->route('services.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formReferenceData(): array
    {
        return [
            'vehicles' => Vehicle::query()
                ->where('status', VehicleStatus::Active)
                ->with('thirdParty:id,identification_number,first_name,first_lastname,company_name,is_natural_person')
                ->get(['id', 'plate', 'is_third_party', 'third_party_id']),
            'drivers' => Driver::query()
                ->where('license_due_date', '>=', Carbon::now((string) config('app.operation_tz'))->toDateString())
                ->get(['id', 'first_name', 'first_lastname', 'identification_number', 'license_due_date', 'eps_id', 'pension_fund_id']),
            'contracts' => Contract::query()
                ->where('active', true)
                ->with('thirdParty:id,identification_number,first_name,first_lastname,company_name,is_natural_person')
                ->get(['id', 'contract_number', 'third_party_id', 'contract_object', 'start_date', 'end_date', 'is_generic', 'billing_unit_type']),
            'municipalities' => Municipality::query()
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'department_id']),
        ];
    }
}
