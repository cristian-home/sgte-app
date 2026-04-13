<?php

namespace App\Http\Requests;

use App\Enums\IncidentSeverity;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IncidentTypeUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows(Permission::UPDATE_INCIDENT_TYPES->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', Rule::unique('incident_types', 'code')->ignore($this->incident_type)],
            'name' => ['required', 'string', 'max:100'],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'affects_billing_default' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
