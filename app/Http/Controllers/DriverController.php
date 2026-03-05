<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\DriverStoreRequest;
use App\Http\Requests\DriverUpdateRequest;
use App\Models\Driver;
use App\Models\Municipality;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DriverController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_DRIVERS->value);
        $drivers = QueryBuilder::for(Driver::class)
            ->allowedFilters([
                'identification_number',
                'first_name',
                'first_lastname',
                AllowedFilter::exact('municipality_id'),
                AllowedFilter::exact('license_category'),
                AllowedFilter::exact('active'),
            ])
            ->allowedSorts(['first_name', 'first_lastname', 'municipality_id', 'license_due_date', 'active'])
            ->get();

        return Inertia::render('drivers/index', [
            'drivers' => $drivers,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_DRIVERS->value);

        return Inertia::render('drivers/create', [
            'municipalities' => Municipality::query()
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'department_id']),
        ]);
    }

    public function store(DriverStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_DRIVERS->value);
        $driver = Driver::create($request->validated());

        return redirect()->route('drivers.index');
    }

    public function show(Request $request, Driver $driver): Response
    {
        Gate::authorize(Permission::VIEW_DRIVERS->value);

        return Inertia::render('drivers/show', [
            'driver' => $driver,
        ]);
    }

    public function edit(Request $request, Driver $driver): Response
    {
        Gate::authorize(Permission::UPDATE_DRIVERS->value);

        return Inertia::render('drivers/edit', [
            'driver' => $driver,
            'municipalities' => Municipality::query()
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'department_id']),
        ]);
    }

    public function update(DriverUpdateRequest $request, Driver $driver): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_DRIVERS->value);
        $driver->update($request->validated());

        return redirect()->route('drivers.index');
    }

    public function destroy(Request $request, Driver $driver): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_DRIVERS->value);
        $driver->delete();

        return redirect()->route('drivers.index');
    }
}
