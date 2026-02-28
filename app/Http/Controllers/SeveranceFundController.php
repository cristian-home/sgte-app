<?php

namespace App\Http\Controllers;

use App\Http\Requests\SeveranceFundStoreRequest;
use App\Http\Requests\SeveranceFundUpdateRequest;
use App\Models\SeveranceFund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class SeveranceFundController extends Controller
{
    public function index(Request $request): Response
    {
        $severanceFunds = QueryBuilder::for(SeveranceFund::class)
            ->allowedFilters(['code', 'name'])
            ->allowedSorts(['code', 'name'])
            ->get();

        return Inertia::render('severance-funds/index', [
            'severanceFunds' => $severanceFunds,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('severance-funds/create');
    }

    public function store(SeveranceFundStoreRequest $request): RedirectResponse
    {
        $severanceFund = SeveranceFund::create($request->validated());

        return redirect()->route('severance-funds.index');
    }

    public function show(Request $request, SeveranceFund $severanceFund): Response
    {
        return Inertia::render('severance-funds/show', [
            'severanceFund' => $severanceFund,
        ]);
    }

    public function edit(Request $request, SeveranceFund $severanceFund): Response
    {
        return Inertia::render('severance-funds/edit', [
            'severanceFund' => $severanceFund,
        ]);
    }

    public function update(SeveranceFundUpdateRequest $request, SeveranceFund $severanceFund): RedirectResponse
    {
        $severanceFund->update($request->validated());

        return redirect()->route('severance-funds.index');
    }

    public function destroy(Request $request, SeveranceFund $severanceFund): RedirectResponse
    {
        $severanceFund->delete();

        return redirect()->route('severance-funds.index');
    }
}
