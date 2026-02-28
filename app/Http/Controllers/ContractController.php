<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\ContractStoreRequest;
use App\Http\Requests\ContractUpdateRequest;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_CONTRACTS->value);
        $contracts = QueryBuilder::for(Contract::class)
            ->allowedFilters([])
            ->allowedSorts([])
            ->get();

        return Inertia::render('contracts/index', [
            'contracts' => $contracts,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_CONTRACTS->value);

        return Inertia::render('contracts/create');
    }

    public function store(ContractStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_CONTRACTS->value);
        $contract = Contract::create($request->validated());

        return redirect()->route('contracts.index');
    }

    public function show(Request $request, Contract $contract): Response
    {
        Gate::authorize(Permission::VIEW_CONTRACTS->value);

        return Inertia::render('contracts/show', [
            'contract' => $contract,
        ]);
    }

    public function edit(Request $request, Contract $contract): Response
    {
        Gate::authorize(Permission::UPDATE_CONTRACTS->value);

        return Inertia::render('contracts/edit', [
            'contract' => $contract,
        ]);
    }

    public function update(ContractUpdateRequest $request, Contract $contract): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_CONTRACTS->value);
        $contract->update($request->validated());

        return redirect()->route('contracts.index');
    }

    public function destroy(Request $request, Contract $contract): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_CONTRACTS->value);
        $contract->delete();

        return redirect()->route('contracts.index');
    }
}
