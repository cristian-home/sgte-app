<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\ServiceStatus;
use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class InvoiceServiceAttachRequest extends FormRequest
{
    /**
     * Minimum length for an override justification. Keeps the trail
     * auditable; "ok" and "si" don't count.
     */
    private const JUSTIFICATION_MIN = 10;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows(Permission::ASSIGN_SERVICES_TO_INVOICES->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'override_justification' => ['nullable', 'string', 'min:'.self::JUSTIFICATION_MIN, 'max:1000'],
        ];
    }

    /**
     * Domain-level checks that depend on the loaded Service rows +
     * the route-bound Invoice.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('service_ids') || $validator->errors()->hasAny(['service_ids.*'])) {
                    return;
                }

                $invoice = $this->route('invoice');
                if (! $invoice instanceof Invoice) {
                    return;
                }

                /** @var array<int> $ids */
                $ids = $this->validated('service_ids');

                $services = Service::query()
                    ->with([
                        'contract:id,third_party_id',
                        'serviceIncidents' => fn ($q) => $q
                            ->where('affects_billing', true)
                            ->with('incidentType:id,name'),
                    ])
                    ->whereIn('id', $ids)
                    ->get();

                $invoiceCustomer = $invoice->third_party_id;

                foreach ($services as $service) {
                    if ($service->contract?->third_party_id !== $invoiceCustomer) {
                        $validator->errors()->add(
                            'service_ids',
                            'Los servicios deben pertenecer al cliente de la factura.',
                        );
                        break;
                    }
                }

                foreach ($services as $service) {
                    if ($service->invoice_id !== null && $service->invoice_id !== $invoice->id) {
                        $validator->errors()->add(
                            'service_ids',
                            'Uno o más servicios ya están asociados a otra factura.',
                        );
                        break;
                    }
                }

                foreach ($services as $service) {
                    $status = $service->service_status;
                    $value = $status instanceof ServiceStatus ? $status->value : $status;
                    if ($value !== ServiceStatus::Closed->value) {
                        $validator->errors()->add(
                            'service_ids',
                            'Solo servicios cerrados pueden facturarse.',
                        );
                        break;
                    }
                }

                // REQ-011 billing-gate: services carrying a billing-
                // affecting incident must be explicitly acknowledged by
                // the operator with a justification — otherwise accounting
                // silently invoices a run that had a known issue.
                $blocked = $services->filter(
                    fn (Service $service) => $service->serviceIncidents->isNotEmpty(),
                );

                if ($blocked->isNotEmpty()) {
                    $justification = trim((string) $this->input('override_justification'));
                    if ($justification === '') {
                        $names = $blocked
                            ->map(function (Service $service): string {
                                $firstIncidentType = $service->serviceIncidents->first()?->incidentType?->name ?? 'novedad';

                                return "#{$service->id} ({$firstIncidentType})";
                            })
                            ->implode(', ');

                        $validator->errors()->add(
                            'service_ids',
                            "Los siguientes servicios tienen novedades que afectan la facturación y requieren justificación: {$names}.",
                        );
                    }
                }
            },
        ];
    }
}
