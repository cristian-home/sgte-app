<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\VehicleStatus;
use App\Http\Requests\ServiceStoreRequest;
use App\Http\Requests\ServiceUpdateRequest;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                AllowedFilter::exact('service_date'),
                AllowedFilter::exact('origin_municipality_id'),
                AllowedFilter::exact('destination_municipality_id'),
                AllowedFilter::exact('service_status'),
                AllowedFilter::exact('payment_method'),
            ])
            ->allowedSorts(['service_date', 'unit_value', 'service_status'])
            ->paginate($request->perPage())
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($services);
        }

        return Inertia::render('services/index', [
            'services' => $services,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_SERVICES->value);

        return Inertia::render('services/create', $this->formReferenceData());
    }

    public function store(ServiceStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_SERVICES->value);
        Service::create($request->validated());

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
        ]);
        $service->loadCount('serviceIncidents');

        return Inertia::render('services/show', [
            'service' => $service,
        ]);
    }

    public function edit(Request $request, Service $service): Response
    {
        Gate::authorize(Permission::UPDATE_PROJECTED_SERVICES->value);

        $service->load([
            'contract',
            'vehicle.thirdParty',
            'driver',
            'originMunicipality.department',
            'destinationMunicipality.department',
        ]);
        $service->loadCount('serviceIncidents');

        return Inertia::render('services/edit', [
            'service' => $service,
            ...$this->formReferenceData(),
        ]);
    }

    public function update(ServiceUpdateRequest $request, Service $service): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_PROJECTED_SERVICES->value);
        $service->update($request->validated());

        return redirect()->route('services.index');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_SERVICES->value);
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
                ->where('license_due_date', '>=', now()->toDateString())
                ->get(['id', 'first_name', 'first_lastname', 'identification_number', 'license_due_date', 'eps_id', 'pension_fund_id']),
            'contracts' => Contract::query()
                ->where('active', true)
                ->with('thirdParty:id,identification_number,first_name,first_lastname,company_name,is_natural_person')
                ->get(['id', 'contract_number', 'third_party_id', 'contract_object', 'start_date', 'end_date', 'is_generic']),
            'municipalities' => Municipality::query()
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'department_id']),
        ];
    }
}
