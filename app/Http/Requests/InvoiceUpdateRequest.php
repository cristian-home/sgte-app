<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use App\Enums\Permission;
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

        return [
            'third_party_id' => ['required', 'integer', 'exists:third_parties,id'],
            'invoice_number' => ['required', 'string', 'max:50', Rule::unique('invoices', 'invoice_number')->ignore($invoice)],
            'total_value' => [
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
