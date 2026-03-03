<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceStoreRequest extends FormRequest
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
            'contract_id' => ['required', 'integer', 'exists:contracts,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'service_date' => ['required', 'date'],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'planned_start_time' => ['required'],
            'planned_duration' => ['required', 'integer'],
            'actual_start_time' => ['nullable'],
            'actual_end_time' => ['nullable'],
            'unit_value' => ['required', 'numeric', 'between:-9999999999.99,9999999999.99'],
            'quantity' => ['required', 'integer'],
            'billing_group' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'service_status' => ['required', Rule::enum(ServiceStatus::class)],
        ];
    }
}
