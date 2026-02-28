<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\VehicleStoreRequest;
use App\Http\Requests\VehicleUpdateRequest;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class VehicleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_VEHICLES->value);
        $vehicles = QueryBuilder::for(Vehicle::class)
            ->allowedFilters([])
            ->allowedSorts([])
            ->get();

        return Inertia::render('vehicles/index', [
            'vehicles' => $vehicles,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_VEHICLES->value);

        return Inertia::render('vehicles/create');
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
