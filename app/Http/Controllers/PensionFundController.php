<?php

namespace App\Http\Controllers;

use App\Http\Requests\PensionFundStoreRequest;
use App\Http\Requests\PensionFundUpdateRequest;
use App\Models\PensionFund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class PensionFundController extends Controller
{
    public function index(Request $request): Response
    {
        $pensionFunds = QueryBuilder::for(PensionFund::class)
            ->allowedFilters(['code', 'name'])
            ->allowedSorts(['code', 'name'])
            ->get();

        return Inertia::render('pension-funds/index', [
            'pensionFunds' => $pensionFunds,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('pension-funds/create');
    }

    public function store(PensionFundStoreRequest $request): RedirectResponse
    {
        $pensionFund = PensionFund::create($request->validated());

        return redirect()->route('pension-funds.index');
    }

    public function show(Request $request, PensionFund $pensionFund): Response
    {
        return Inertia::render('pension-funds/show', [
            'pensionFund' => $pensionFund,
        ]);
    }

    public function edit(Request $request, PensionFund $pensionFund): Response
    {
        return Inertia::render('pension-funds/edit', [
            'pensionFund' => $pensionFund,
        ]);
    }

    public function update(PensionFundUpdateRequest $request, PensionFund $pensionFund): RedirectResponse
    {
        $pensionFund->update($request->validated());

        return redirect()->route('pension-funds.index');
    }

    public function destroy(Request $request, PensionFund $pensionFund): RedirectResponse
    {
        $pensionFund->delete();

        return redirect()->route('pension-funds.index');
    }
}
