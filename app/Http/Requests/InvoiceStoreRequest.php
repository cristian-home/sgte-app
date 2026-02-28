<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'invoice_number' => ['required', 'string', 'max:50', 'unique:invoices,invoice_number'],
            'total_value' => ['required', 'numeric', 'between:-9999999999.99,9999999999.99'],
            'issue_date' => ['required', 'date'],
            'payment_status' => ['required', 'in:pending,paid,overdue'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
