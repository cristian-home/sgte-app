<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ServiceIncidentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows(Permission::UPDATE_INCIDENTS->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'incident_type_id' => ['required', 'integer', 'exists:incident_types,id'],
            'description' => ['required', 'string'],
            'affects_billing' => ['boolean'],
            'additional_value' => ['nullable', 'numeric', 'between:-9999999999.99,9999999999.99'],
        ];
    }
}
