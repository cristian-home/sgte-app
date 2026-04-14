<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use App\Enums\Permission;
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
        return [
            'third_party_id' => ['required', 'integer', 'exists:third_parties,id'],
            'invoice_number' => ['required', 'string', 'max:50', Rule::unique('invoices', 'invoice_number')->ignore($this->route('invoice'))],
            'total_value' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'issue_date' => ['required', 'date'],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
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
