<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\BillingGroupStoreRequest;
use App\Http\Requests\BillingGroupUpdateRequest;
use App\Models\BillingGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class BillingGroupController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_BILLING_GROUPS->value);

        $billingGroups = QueryBuilder::for(BillingGroup::class)
            ->allowedFilters(['code', 'name', 'active'])
            ->allowedSorts(['code', 'name', 'active'])
            ->withCount('services')
            ->orderBy('name')
            ->get();

        return Inertia::render('billing-groups/index', [
            'billingGroups' => $billingGroups,
        ]);
    }

    public function store(BillingGroupStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_BILLING_GROUPS->value);

        BillingGroup::create($request->validated());

        return back()->with('success', 'Grupo de facturación creado.');
    }

    public function show(Request $request, BillingGroup $billingGroup): Response
    {
        Gate::authorize(Permission::VIEW_BILLING_GROUPS->value);

        $billingGroup->loadCount('services');

        return Inertia::render('billing-groups/show', [
            'billingGroup' => $billingGroup,
        ]);
    }

    public function update(BillingGroupUpdateRequest $request, BillingGroup $billingGroup): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_BILLING_GROUPS->value);

        $billingGroup->update($request->validated());

        return back()->with('success', 'Grupo de facturación actualizado.');
    }

    public function destroy(Request $request, BillingGroup $billingGroup): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_BILLING_GROUPS->value);

        $inUseCount = $billingGroup->services()->count();
        if ($inUseCount > 0) {
            throw ValidationException::withMessages([
                'billing_group' => "No se puede eliminar: {$inUseCount} servicio(s) lo usan. Desactívalo en su lugar.",
            ]);
        }

        $billingGroup->delete();

        return redirect()->route('billing-groups.index');
    }
}
