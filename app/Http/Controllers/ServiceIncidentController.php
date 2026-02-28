<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\ServiceIncidentStoreRequest;
use App\Http\Requests\ServiceIncidentUpdateRequest;
use App\Models\ServiceIncident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            ->allowedFilters([
                AllowedFilter::exact('incident_type'),
                AllowedFilter::exact('is_driver_report'),
                AllowedFilter::exact('affects_billing'),
            ])
            ->allowedSorts(['incident_type', 'reported_at'])
            ->get();

        return Inertia::render('service-incidents/index', [
            'serviceIncidents' => $serviceIncidents,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_INCIDENTS->value);

        return Inertia::render('service-incidents/create');
    }

    public function store(ServiceIncidentStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_INCIDENTS->value);
        $serviceIncident = ServiceIncident::create($request->validated());

        return redirect()->route('service-incidents.index');
    }

    public function show(Request $request, ServiceIncident $serviceIncident): Response
    {
        Gate::authorize(Permission::VIEW_INCIDENTS->value);

        return Inertia::render('service-incidents/show', [
            'serviceIncident' => $serviceIncident,
        ]);
    }

    public function edit(Request $request, ServiceIncident $serviceIncident): Response
    {
        Gate::authorize(Permission::UPDATE_INCIDENTS->value);

        return Inertia::render('service-incidents/edit', [
            'serviceIncident' => $serviceIncident,
        ]);
    }

    public function update(ServiceIncidentUpdateRequest $request, ServiceIncident $serviceIncident): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_INCIDENTS->value);
        $serviceIncident->update($request->validated());

        return redirect()->route('service-incidents.index');
    }

    public function destroy(Request $request, ServiceIncident $serviceIncident): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_INCIDENTS->value);
        $serviceIncident->delete();

        return redirect()->route('service-incidents.index');
    }
}
