<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\VehicleLocationStoreRequest;
use App\Http\Requests\VehicleLocationUpdateRequest;
use App\Models\VehicleLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class VehicleLocationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_VEHICLES->value);

        $vehicleLocations = QueryBuilder::for(VehicleLocation::class)
            ->allowedFilters([
                AllowedFilter::exact('vehicle_id'),
                AllowedFilter::exact('is_manual'),
            ])
            ->allowedSorts(['recorded_at'])
            ->get();

        return Inertia::render('vehicle-locations/index', [
            'vehicleLocations' => $vehicleLocations,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_VEHICLES->value);

        return Inertia::render('vehicle-locations/create');
    }

    public function store(VehicleLocationStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_VEHICLES->value);

        VehicleLocation::create($request->validated());

        return redirect()->route('vehicle-locations.index');
    }

    public function show(Request $request, VehicleLocation $vehicleLocation): Response
    {
        Gate::authorize(Permission::VIEW_VEHICLES->value);

        return Inertia::render('vehicle-locations/show', [
            'vehicleLocation' => $vehicleLocation,
        ]);
    }

    public function edit(Request $request, VehicleLocation $vehicleLocation): Response
    {
        Gate::authorize(Permission::UPDATE_VEHICLES->value);

        return Inertia::render('vehicle-locations/edit', [
            'vehicleLocation' => $vehicleLocation,
        ]);
    }

    public function update(VehicleLocationUpdateRequest $request, VehicleLocation $vehicleLocation): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_VEHICLES->value);

        $vehicleLocation->update($request->validated());

        return redirect()->route('vehicle-locations.index');
    }

    public function destroy(Request $request, VehicleLocation $vehicleLocation): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_VEHICLES->value);

        $vehicleLocation->delete();

        return redirect()->route('vehicle-locations.index');
    }
}
