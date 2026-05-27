<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Invoice;
use App\Rules\TotalValueLockedWhenServicesAttached;
use App\Support\Tz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows(Permission::UPDATE_INVOICES->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $invoiceModel = $invoice instanceof Invoice ? $invoice : null;
        $hasServiceIds = $this->has('service_ids');

        return [
            'third_party_id' => ['required', 'integer', 'exists:third_parties,id'],
            'invoice_number' => ['required', 'string', 'max:50', Rule::unique('invoices', 'invoice_number')->ignore($invoice)],
            // Cuando llegan service_ids el server recomputa el total, así
            // que afloja la regla a `min:0` (los servicios pueden sumar
            // cero si todos se detachan). Sin service_ids se mantiene
            // el mínimo de 0.01 del flujo "factura manual".
            'total_value' => $hasServiceIds
                ? [
                    'required',
                    'numeric',
                    'min:0',
                    'max:9999999999.99',
                ]
                : [
                    'required',
                    'numeric',
                    'min:0.01',
                    'max:9999999999.99',
                    new TotalValueLockedWhenServicesAttached($invoiceModel),
                ],
            'issue_date' => ['required', 'date'],
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
            'issued_at' => ['required', 'date'],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'notes' => ['nullable', 'string'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'override_justification' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $timezone = $this->input('timezone');
        if (! is_string($timezone) || $timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = Tz::operation();
        }
        $this->merge(['timezone' => $timezone]);

        $issueDate = $this->input('issue_date');
        $ymd = $issueDate instanceof \DateTimeInterface
            ? $issueDate->format('Y-m-d')
            : (is_string($issueDate) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $issueDate, $m) ? $m[1] : null);
        if ($ymd !== null) {
            $this->merge(['issued_at' => Tz::startOfDayInTzAsUtc($ymd, $timezone)->utc()->toIso8601String()]);
        }

        // Solo super admin puede modificar el número de factura una vez
        // creada; para el resto sobreescribimos cualquier valor entrante
        // con el actual del modelo antes de que entren las reglas (así
        // un cliente malicioso o un dialog mal cableado no rompe la
        // identidad de la factura).
        $invoice = $this->route('invoice');
        if ($invoice instanceof Invoice && ! ($this->user()?->hasRole(Role::SUPER_ADMIN->value) ?? false)) {
            $this->merge(['invoice_number' => $invoice->invoice_number]);
        }

        // Cuando llega `service_ids` (set final deseado) el server hace
        // diff y recomputa el total automáticamente — el `total_value`
        // que mande el cliente se descarta. Lo normalizamos al valor
        // actual del modelo para que la regla
        // TotalValueLockedWhenServicesAttached no rechace requests que
        // simultáneamente cambian servicios y proponen un nuevo total.
        if ($invoice instanceof Invoice && $this->has('service_ids')) {
            $this->merge(['total_value' => $invoice->total_value]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'third_party_id.required' => 'El cliente es obligatorio.',
            'third_party_id.exists' => 'El cliente seleccionado no existe.',
            'total_value.min' => 'El valor total debe ser mayor que cero.',
        ];
    }
}
