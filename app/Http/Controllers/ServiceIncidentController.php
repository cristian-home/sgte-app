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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceIncidentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_INCIDENTS->value);
        $serviceIncidents = QueryBuilder::for(ServiceIncident::class)
            ->with(['service', 'incidentType', 'registrar'])
            ->allowedFilters([
                AllowedFilter::exact('service_id'),
                AllowedFilter::exact('incident_type_id'),
                AllowedFilter::exact('is_driver_report'),
                AllowedFilter::exact('affects_billing'),
            ])
            ->allowedSorts(['reported_at', 'incident_type_id'])
            ->latest('reported_at')
            ->get();

        return Inertia::render('service-incidents/index', [
            'serviceIncidents' => $serviceIncidents,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_INCIDENTS->value);

        $service = null;
        if ($request->has('service_id')) {
            $service = Service::with(['vehicle', 'contract.thirdParty'])->find($request->integer('service_id'));
        }

        return Inertia::render('service-incidents/create', [
            'incidentTypes' => IncidentType::orderBy('name')->get(),
            'service' => $service,
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

        $serviceIncident->load(['service', 'incidentType', 'registrar']);

        return Inertia::render('service-incidents/show', [
            'serviceIncident' => $serviceIncident,
        ]);
    }

    public function edit(Request $request, ServiceIncident $serviceIncident): Response
    {
        Gate::authorize(Permission::UPDATE_INCIDENTS->value);

        $serviceIncident->load(['service.vehicle', 'service.contract.thirdParty']);

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
