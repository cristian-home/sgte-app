<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceIncidentUpdateRequest extends FormRequest
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
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'incident_type' => ['required', 'in:delay,accident,breakdown,traffic,weather,customer_no_show,other'],
            'description' => ['required', 'string'],
            'registrar_id' => ['required', 'integer', 'exists:users,id'],
            'is_driver_report' => ['required'],
            'reported_at' => ['required'],
            'affects_billing' => ['required'],
            'additional_value' => ['nullable', 'numeric', 'between:-9999999999.99,9999999999.99'],
        ];
    }
}
