<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Support\Tz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows(Permission::CREATE_INVOICES->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $hasServiceIds = is_array($this->input('service_ids')) && count($this->input('service_ids')) > 0;

        return [
            'third_party_id' => ['required', 'integer', 'exists:third_parties,id'],
            'invoice_number' => ['required', 'string', 'max:50', 'unique:invoices,invoice_number'],
            // When the request carries service_ids[], the server
            // recomputes the total from those services after attach.
            // The form may still send a placeholder value (e.g. 0) so
            // the rule relaxes to "nullable" and the user-supplied
            // value is overwritten downstream.
            'total_value' => $hasServiceIds
                ? ['nullable', 'numeric', 'min:0', 'max:9999999999.99']
                : ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
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
