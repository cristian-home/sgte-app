<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\InvoiceStoreRequest;
use App\Http\Requests\InvoiceUpdateRequest;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_INVOICES->value);
        $invoices = QueryBuilder::for(Invoice::class)
            ->allowedFilters([])
            ->allowedSorts([])
            ->get();

        return Inertia::render('invoices/index', [
            'invoices' => $invoices,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_INVOICES->value);

        return Inertia::render('invoices/create');
    }

    public function store(InvoiceStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_INVOICES->value);
        $invoice = Invoice::create($request->validated());

        return redirect()->route('invoices.index');
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        Gate::authorize(Permission::VIEW_INVOICES->value);

        return Inertia::render('invoices/show', [
            'invoice' => $invoice,
        ]);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        Gate::authorize(Permission::UPDATE_INVOICES->value);

        return Inertia::render('invoices/edit', [
            'invoice' => $invoice,
        ]);
    }

    public function update(InvoiceUpdateRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_INVOICES->value);
        $invoice->update($request->validated());

        return redirect()->route('invoices.index');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize(Permission::DELETE_INVOICES->value);
        $invoice->delete();

        return redirect()->route('invoices.index');
    }
}
