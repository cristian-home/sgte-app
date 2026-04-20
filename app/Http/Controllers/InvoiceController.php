<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\ServiceStatus;
use App\Http\Requests\InvoiceServiceAttachRequest;
use App\Http\Requests\InvoiceStoreRequest;
use App\Http\Requests\InvoiceUpdateRequest;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ThirdParty;
use App\Services\InvoiceTotalCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InvoiceController extends Controller
{
    public function index(Request $request): Response|JsonResponse
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

        if ($request->wantsJson()) {
            return response()->json($invoices);
        }

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

    public function show(Request $request, Invoice $invoice, InvoiceTotalCalculator $calculator): Response
    {
        Gate::authorize(Permission::VIEW_INVOICES->value);

        $invoice->load([
            'thirdParty:id,document_type_id,identification_number,is_natural_person,first_name,first_lastname,company_name,is_customer,is_provider',
            'thirdParty.documentType:id,code,name',
        ]);
        $invoice->loadCount('services');

        $recentServices = Service::query()
            ->where('invoice_id', $invoice->id)
            ->with([
                'vehicle:id,plate',
                'driver:id,first_name,first_lastname',
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
                'driver_id',
                'contract_id',
                'invoice_id',
                'unit_value',
                'quantity',
                'planned_start_time',
            ]);

        $candidates = $this->candidateServices($invoice);
        [$cleanCandidates, $blockedCandidates] = $candidates->partition(
            fn (Service $service) => ! $service->serviceIncidents
                ->where('affects_billing', true)
                ->isNotEmpty(),
        );

        return Inertia::render('invoices/show', [
            'invoice' => $invoice,
            'recentServices' => $recentServices,
            'computedTotal' => $calculator->computeFor($invoice),
            'candidateServices' => $cleanCandidates->values(),
            'blockedCandidateServices' => $blockedCandidates->values(),
        ]);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        Gate::authorize(Permission::UPDATE_INVOICES->value);

        $invoice->load('thirdParty.documentType');
        $invoice->loadCount('services');

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

    /**
     * Attach one or more closed services to the invoice and recompute
     * the total. The InvoiceServiceAttachRequest after() hook validates
     * that all target services belong to the invoice's customer, are
     * closed, and are not already attached to a different invoice.
     */
    public function attachServices(
        InvoiceServiceAttachRequest $request,
        Invoice $invoice,
        InvoiceTotalCalculator $calculator,
    ): RedirectResponse {
        Gate::authorize(Permission::ASSIGN_SERVICES_TO_INVOICES->value);

        $ids = $request->validated('service_ids');
        $justification = trim((string) $request->input('override_justification'));

        // Identify the subset of services that carry a billing-
        // affecting incident — these are the ones the justification is
        // actually overriding. We log them explicitly so the audit
        // trail answers "which services were force-attached" without
        // requiring reviewers to replay the incident state at attach
        // time.
        $overriddenServiceIds = [];
        if ($justification !== '') {
            $overriddenServiceIds = Service::query()
                ->whereIn('id', $ids)
                ->whereHas(
                    'serviceIncidents',
                    fn ($q) => $q->where('affects_billing', true),
                )
                ->pluck('id')
                ->all();
        }

        DB::transaction(function () use ($ids, $invoice) {
            Service::query()
                ->whereIn('id', $ids)
                ->update(['invoice_id' => $invoice->id]);
        });

        if ($justification !== '' && $overriddenServiceIds !== []) {
            activity()
                ->performedOn($invoice)
                ->causedBy($request->user())
                ->withProperties([
                    'override_justification' => $justification,
                    'overridden_service_ids' => $overriddenServiceIds,
                    'attached_service_ids' => $ids,
                ])
                ->log('Servicios con novedades facturables asociados con justificación');
        }

        $calculator->recomputeFor($invoice->fresh());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', count($ids).' servicio(s) asociado(s).');
    }

    /**
     * Detach a service from the invoice by nulling its invoice_id and
     * recomputing the total. Returns 404 if the service is not attached
     * to this invoice (defensive; the route already binds both params).
     */
    public function detachService(
        Request $request,
        Invoice $invoice,
        Service $service,
        InvoiceTotalCalculator $calculator,
    ): RedirectResponse {
        Gate::authorize(Permission::ASSIGN_SERVICES_TO_INVOICES->value);

        if ($service->invoice_id !== $invoice->id) {
            abort(404);
        }

        $service->update(['invoice_id' => null]);
        $calculator->recomputeFor($invoice->fresh());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Servicio desvinculado.');
    }

    /**
     * Idempotent recompute. Covers the drift case where a linked
     * service's unit_value/quantity or a linked incident's
     * affects_billing/additional_value changes after attach-time.
     */
    public function recomputeTotal(
        Request $request,
        Invoice $invoice,
        InvoiceTotalCalculator $calculator,
    ): RedirectResponse {
        Gate::authorize(Permission::ASSIGN_SERVICES_TO_INVOICES->value);

        $calculator->recomputeFor($invoice);

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Total recalculado.');
    }

    /**
     * Services the <ServicePickerDialog /> on the invoice show page
     * can offer for attachment: closed, unbilled, within the last
     * 90 days, and belonging to the invoice's customer.
     *
     * Shipped alongside `invoice` on the show payload so the picker's
     * first open is hydrated (see Notes in the requirement doc).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Service>
     */
    /**
     * Stream an informational PDF of the invoice inline.
     *
     * Explicitly NOT a fiscal document (DIAN compliance requires a
     * certified e-invoice provider integration — separate project).
     * The PDF carries a prominent INFORMATIVO badge and a footer
     * disclaimer on every page.
     *
     * Side-effect: recomputes `invoice.total_value` via the calculator
     * before rendering so the printed total matches the current
     * attached services + billing-affecting incidents. Justified
     * because a stale total leaking into a customer-facing PDF is a
     * bigger problem than the minor activity-log noise when nothing
     * changed.
     */
    public function pdf(Request $request, Invoice $invoice, InvoiceTotalCalculator $calculator): HttpResponse
    {
        Gate::authorize(Permission::VIEW_INVOICES->value);

        $calculator->recomputeFor($invoice->fresh());

        $invoice = $invoice->fresh()->load([
            'thirdParty.documentType',
            'thirdParty.municipality.department',
            'services' => fn ($q) => $q->orderBy('service_date'),
            'services.vehicle:id,plate',
            'services.contract:id,contract_number',
            'services.serviceIncidents' => fn ($q) => $q->where('affects_billing', true),
            'services.serviceIncidents.incidentType:id,name',
        ]);

        $services = $invoice->services;
        $billingIncidents = $services->flatMap(fn ($s) => $s->serviceIncidents)->values();

        $subtotalServices = (float) $services->sum(fn ($s) => (float) $s->unit_value * (int) $s->quantity);
        $subtotalIncidents = (float) $billingIncidents->sum(fn ($i) => (float) ($i->additional_value ?? 0));
        $grandTotal = $subtotalServices + $subtotalIncidents;

        $thirdParty = $invoice->thirdParty;
        $customerName = $this->customerNameFor($thirdParty);
        $customerDocument = trim(
            ($thirdParty?->documentType?->code ?? '').' '.($thirdParty?->identification_number ?? ''),
        );

        $customerAddressLine = collect([
            $thirdParty?->municipality?->name,
            $thirdParty?->municipality?->department?->name,
            $thirdParty?->address,
        ])->filter()->implode(' — ');

        $nowFormatted = Carbon::now()->locale('es_CO')->isoFormat('LLLL');

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'services' => $services,
            'billing_incidents' => $billingIncidents,
            'subtotal_services' => $subtotalServices,
            'subtotal_incidents' => $subtotalIncidents,
            'grand_total' => $grandTotal,
            'customer_name' => $customerName,
            'customer_document' => $customerDocument,
            'customer_address_line' => $customerAddressLine,
            'now_formatted' => $nowFormatted,
        ])->setPaper('letter');

        return $pdf->stream(
            "factura-{$invoice->invoice_number}.pdf",
            ['Attachment' => false],
        );
    }

    /**
     * Resolve the customer display name — legal persons print their
     * company name, natural persons get their first + last name.
     */
    private function customerNameFor(?ThirdParty $tp): string
    {
        if ($tp === null) {
            return '—';
        }

        if ($tp->is_natural_person) {
            $name = trim(($tp->first_name ?? '').' '.($tp->first_lastname ?? ''));

            return $name !== '' ? $name : '—';
        }

        return $tp->company_name ?? '—';
    }

    private function candidateServices(Invoice $invoice): \Illuminate\Database\Eloquent\Collection
    {
        $cutoff = Carbon::today()->subDays(90);

        return Service::query()
            ->whereNull('invoice_id')
            ->where('service_status', ServiceStatus::Closed->value)
            ->whereDate('service_date', '>=', $cutoff)
            ->whereHas('contract', fn ($q) => $q->where('third_party_id', $invoice->third_party_id))
            ->with([
                'vehicle:id,plate',
                'driver:id,first_name,first_lastname',
                'contract:id,contract_number',
                'serviceIncidents:id,service_id,affects_billing,additional_value',
            ])
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->get([
                'id',
                'service_date',
                'vehicle_id',
                'driver_id',
                'contract_id',
                'unit_value',
                'quantity',
                'service_status',
            ]);
    }
}
