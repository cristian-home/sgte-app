<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Http\Requests\InvoiceStoreRequest;
use App\Http\Requests\InvoiceUpdateRequest;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ThirdParty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::VIEW_INVOICES->value);

        $invoices = QueryBuilder::for(Invoice::class)
            ->with([
                'thirdParty:id,document_type_id,identification_number,is_natural_person,first_name,first_lastname,company_name,is_customer,is_provider',
                'thirdParty.documentType:id,code,name',
            ])
            ->allowedFilters([
                'invoice_number',
                AllowedFilter::exact('payment_status'),
                AllowedFilter::exact('third_party_id'),
            ])
            ->allowedSorts(['invoice_number', 'issue_date', 'total_value', 'payment_status'])
            ->defaultSort('-issue_date')
            ->paginate($request->perPage())
            ->withQueryString();

        return Inertia::render('invoices/index', [
            'invoices' => $invoices,
            'thirdParties' => $this->customerOptions(),
        ]);
    }

    /**
     * Shared customer option list — used by the create modal on the
     * index, the above-the-table combobox filter, and the standalone
     * create/edit pages. Mirrors ContractController::customerOptions.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ThirdParty>
     */
    private function customerOptions(): \Illuminate\Database\Eloquent\Collection
    {
        return ThirdParty::query()
            ->where('is_customer', true)
            ->with('documentType:id,code,name')
            ->orderBy('company_name')
            ->orderBy('first_lastname')
            ->get([
                'id',
                'document_type_id',
                'identification_number',
                'is_natural_person',
                'first_name',
                'first_lastname',
                'company_name',
                'is_customer',
                'is_provider',
            ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Permission::CREATE_INVOICES->value);

        return Inertia::render('invoices/create', [
            'thirdParties' => $this->customerOptions(),
        ]);
    }

    public function store(InvoiceStoreRequest $request): RedirectResponse
    {
        Gate::authorize(Permission::CREATE_INVOICES->value);
        Invoice::create($request->validated());

        return redirect()->route('invoices.index');
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        Gate::authorize(Permission::VIEW_INVOICES->value);

        $invoice->load([
            'thirdParty:id,document_type_id,identification_number,is_natural_person,first_name,first_lastname,company_name,is_customer,is_provider',
            'thirdParty.documentType:id,code,name',
        ]);

        $recentServices = Service::query()
            ->where('invoice_id', $invoice->id)
            ->with([
                'vehicle:id,plate',
                'contract:id,contract_number',
            ])
            ->orderByDesc('service_date')
            ->orderByDesc('planned_start_time')
            ->limit(5)
            ->get([
                'id',
                'service_date',
                'service_status',
                'vehicle_id',
                'contract_id',
                'invoice_id',
                'unit_value',
                'quantity',
                'planned_start_time',
            ]);

        return Inertia::render('invoices/show', [
            'invoice' => $invoice,
            'recentServices' => $recentServices,
        ]);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        Gate::authorize(Permission::UPDATE_INVOICES->value);

        $invoice->load('thirdParty.documentType');

        return Inertia::render('invoices/edit', [
            'invoice' => $invoice,
            'thirdParties' => $this->customerOptions(),
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

    /**
     * Transition a pending invoice to paid. Rejected with 422 when the
     * invoice is already paid or overdue — the intent of this route is
     * to mark a billable invoice as collected, not to overwrite a
     * terminal state. Future transitions (mark-overdue, cancel, refund)
     * get their own dedicated methods.
     */
    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_INVOICES->value);

        if ($invoice->payment_status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'payment_status' => 'Solo facturas pendientes pueden marcarse como pagadas.',
            ]);
        }

        $invoice->update(['payment_status' => PaymentStatus::Paid]);

        return redirect()->route('invoices.show', $invoice);
    }
}
