<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\VehicleStoreRequest;
use App\Http\Requests\VehicleUpdateRequest;
use App\Models\Municipality;
use App\Models\ThirdParty;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class VehicleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_VEHICLES->value);
        $vehicles = QueryBuilder::for(Vehicle::class)
            ->allowedFilters([
                'internal_code',
                'plate',
                'brand',
                AllowedFilter::exact('type'),
                AllowedFilter::exact('municipality_id'),
                AllowedFilter::exact('is_third_party'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(['internal_code', 'plate', 'model_year', 'municipality_id', 'status'])
            ->get();

        return Inertia::render('vehicles/index', [
            'vehicles' => $vehicles,
            'thirdParties' => ThirdParty::query()
                ->where('active', true)
                ->where('is_provider', true)
                ->get(['id', 'identification_number', 'first_name', 'first_lastname', 'company_name', 'is_natural_person']),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_VEHICLES->value);

        return Inertia::render('vehicles/create', [
            'thirdParties' => ThirdParty::query()
                ->where('active', true)
                ->where('is_provider', true)
                ->get(['id', 'identification_number', 'first_name', 'first_lastname', 'company_name', 'is_natural_person']),
            'municipalities' => Municipality::query()
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'department_id']),
        ]);
    }

    public function store(VehicleStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_VEHICLES->value);
        $vehicle = Vehicle::create($request->validated());

        return redirect()->route('vehicles.index');
    }

    public function show(Request $request, Vehicle $vehicle): Response
    {
        Gate::authorize(Permission::VIEW_VEHICLES->value);

        return Inertia::render('vehicles/show', [
            'vehicle' => $vehicle,
        ]);
    }

    public function edit(Request $request, Vehicle $vehicle): Response
    {
        Gate::authorize(Permission::UPDATE_VEHICLES->value);

        return Inertia::render('vehicles/edit', [
            'vehicle' => $vehicle,
            'thirdParties' => ThirdParty::query()
                ->where('active', true)
                ->where('is_provider', true)
                ->get(['id', 'identification_number', 'first_name', 'first_lastname', 'company_name', 'is_natural_person']),
            'municipalities' => Municipality::query()
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'department_id']),
        ]);
    }

    public function update(VehicleUpdateRequest $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_VEHICLES->value);
        $vehicle->update($request->validated());

        return redirect()->route('vehicles.index');
    }

    public function destroy(Request $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_VEHICLES->value);
        $vehicle->delete();

        return redirect()->route('vehicles.index');
    }
}
