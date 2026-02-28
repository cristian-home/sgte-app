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
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_CONTRACTS->value);
        $contracts = QueryBuilder::for(Contract::class)
            ->allowedFilters([
                'contract_number',
                'contract_object',
                AllowedFilter::exact('is_generic'),
                AllowedFilter::exact('active'),
            ])
            ->allowedSorts(['contract_number', 'start_date', 'end_date'])
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

        $data = $request->validated();

        if ($request->boolean('is_generic') && empty($data['contract_number'])) {
            $year = now()->year;
            $sequence = Contract::query()
                ->where('contract_number', 'like', "GEN-%-{$year}")
                ->count() + 1;
            $data['contract_number'] = sprintf('GEN-%04d-%d', $sequence, $year);
        }

        Contract::create($data);

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
