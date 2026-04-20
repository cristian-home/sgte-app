<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\ServiceIncidentStoreRequest;
use App\Http\Requests\ServiceIncidentUpdateRequest;
use App\Models\IncidentType;
use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\User;
use App\Notifications\BillingIncidentNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceIncidentController extends Controller
{
    /**
     * Days-back window used when building the service option list for
     * the new <ServiceCombobox /> on the create page. Keeps the
     * payload small on projects with thousands of services while still
     * covering the typical incident-registration lookback.
     */
    private const RECENT_SERVICES_WINDOW_DAYS = 60;

    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize(Permission::VIEW_INCIDENTS->value);

        $serviceIncidents = QueryBuilder::for(ServiceIncident::class)
            ->with([
                'service:id,service_date,vehicle_id,contract_id,driver_id',
                'service.vehicle:id,plate',
                'service.contract:id,contract_number',
                'incidentType:id,code,name,severity',
                'registrar:id,name',
            ])
            ->allowedFilters([
                AllowedFilter::exact('service_id'),
                AllowedFilter::exact('incident_type_id'),
                AllowedFilter::exact('is_driver_report'),
                AllowedFilter::exact('affects_billing'),
                AllowedFilter::callback('severity', function (Builder $query, $value) {
                    $first = is_array($value) ? ($value[0] ?? '') : explode(',', (string) $value)[0];
                    if ($first === '') {
                        return;
                    }
                    $query->whereHas('incidentType', fn (Builder $q) => $q->where('severity', $first));
                }),
            ])
            ->allowedSorts(['reported_at', 'service_id', 'incident_type_id'])
            ->defaultSort('-reported_at')
            ->paginate($request->perPage())
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($serviceIncidents);
        }

        return Inertia::render('service-incidents/index', [
            'serviceIncidents' => $serviceIncidents,
            'incidentTypes' => IncidentType::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'severity', 'affects_billing_default']),
        ]);
    }

    /**
     * Build the "last 60 days of services" option list for the
     * <ServiceCombobox /> primitive. Only returned when the user
     * hits /service-incidents/create without a ?service_id= query
     * param — otherwise the form skips the picker entirely.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Service>
     */
    private function recentServiceOptions(): \Illuminate\Database\Eloquent\Collection
    {
        $cutoff = Carbon::today()->subDays(self::RECENT_SERVICES_WINDOW_DAYS);

        return Service::query()
            ->whereDate('service_date', '>=', $cutoff)
            ->with([
                'vehicle:id,plate',
                'contract:id,contract_number',
                'driver:id,first_name,first_lastname',
            ])
            ->orderByDesc('service_date')
            ->orderByDesc('planned_start_time')
            ->get(['id', 'service_date', 'vehicle_id', 'contract_id', 'driver_id']);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_INCIDENTS->value);

        $service = null;
        if ($request->has('service_id')) {
            $service = Service::with(['vehicle', 'contract.thirdParty', 'driver:id,first_name,first_lastname'])
                ->find($request->integer('service_id'));
        }

        return Inertia::render('service-incidents/create', [
            'incidentTypes' => IncidentType::orderBy('name')->get(),
            'service' => $service,
            // Only ship the full options list when the form actually
            // needs the picker — driver-portal + services/show paths
            // preselect the service and don't need the combobox.
            'services' => $service ? null : $this->recentServiceOptions(),
        ]);
    }

    public function store(ServiceIncidentStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_INCIDENTS->value);

        $serviceIncident = ServiceIncident::create([
            ...$request->validated(),
            'registrar_id' => $request->user()->id,
            'reported_at' => now(),
            'is_driver_report' => $request->user()->hasRole(Role::DRIVER->value),
        ]);

        if ($serviceIncident->affects_billing) {
            $recipients = User::role([Role::SUPER_ADMIN->value, Role::ADMIN->value, Role::ACCOUNTING->value])->get();
            if ($recipients->isNotEmpty()) {
                $serviceIncident->load(['service', 'incidentType']);
                Notification::send($recipients, new BillingIncidentNotification($serviceIncident));
            }
        }

        return $this->redirectAfterMutation($request, $serviceIncident->service_id);
    }

    public function show(Request $request, ServiceIncident $serviceIncident): Response
    {
        Gate::authorize(Permission::VIEW_INCIDENTS->value);

        $serviceIncident->load([
            'service:id,service_date,vehicle_id,contract_id,driver_id',
            'service.vehicle:id,plate',
            'service.contract:id,contract_number,third_party_id',
            'service.contract.thirdParty:id,is_natural_person,first_name,first_lastname,company_name',
            'incidentType:id,code,name,severity,affects_billing_default',
            'registrar:id,name',
        ]);

        return Inertia::render('service-incidents/show', [
            'serviceIncident' => $serviceIncident,
        ]);
    }

    public function edit(Request $request, ServiceIncident $serviceIncident): Response
    {
        Gate::authorize(Permission::UPDATE_INCIDENTS->value);

        $serviceIncident->load([
            'service:id,service_date,vehicle_id,contract_id,driver_id',
            'service.vehicle:id,plate',
            'service.contract:id,contract_number,third_party_id',
            'service.contract.thirdParty:id,is_natural_person,first_name,first_lastname,company_name',
            'incidentType:id,code,name,severity,affects_billing_default',
        ]);

        return Inertia::render('service-incidents/edit', [
            'serviceIncident' => $serviceIncident,
            'incidentTypes' => IncidentType::orderBy('name')->get(),
        ]);
    }

    public function update(ServiceIncidentUpdateRequest $request, ServiceIncident $serviceIncident): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_INCIDENTS->value);
        $serviceIncident->update($request->validated());

        return $this->redirectAfterMutation($request, $serviceIncident->service_id);
    }

    public function destroy(Request $request, ServiceIncident $serviceIncident): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_INCIDENTS->value);

        $serviceId = $serviceIncident->service_id;
        $serviceIncident->delete();

        return $this->redirectAfterMutation($request, $serviceId);
    }

    /**
     * Pick a safe redirect target depending on the user's role.
     *
     * Drivers don't have VIEW_SERVICES, so redirecting them to
     * services.show would yield a 403. Send them back to /driver
     * where they see their own assigned services.
     */
    private function redirectAfterMutation(Request $request, int $serviceId): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasRole(Role::DRIVER->value) && ! $user->can(Permission::VIEW_SERVICES->value)) {
            return redirect()->route('driver.dashboard');
        }

        return redirect()->route('services.show', $serviceId);
    }
}
