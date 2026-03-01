<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\ServiceStoreRequest;
use App\Http\Requests\ServiceUpdateRequest;
use App\Models\Service;
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
                AllowedFilter::callback('search', fn (Builder $query, $value) => $query->search($value)),
                AllowedFilter::exact('service_date'),
                'origin',
                'destination',
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

        return Inertia::render('services/create');
    }

    public function store(ServiceStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_SERVICES->value);
        $service = Service::create($request->validated());

        return redirect()->route('services.index');
    }

    public function show(Request $request, Service $service): Response
    {
        Gate::authorize(Permission::VIEW_SERVICES->value);

        return Inertia::render('services/show', [
            'service' => $service,
        ]);
    }

    public function edit(Request $request, Service $service): Response
    {
        return Inertia::render('services/edit', [
            'service' => $service,
        ]);
    }

    public function update(ServiceUpdateRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());

        return redirect()->route('services.index');
    }

    public function destroy(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_SERVICES->value);
        $service->delete();

        return redirect()->route('services.index');
    }
}
