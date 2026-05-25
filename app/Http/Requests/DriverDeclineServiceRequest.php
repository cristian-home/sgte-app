<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DriverDeclineServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::REGISTER_SERVICE_TIMES->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason_text' => ['required', 'string', 'min:10', 'max:1000'],
            'incident_type_id' => ['nullable', 'integer', 'exists:incident_types,id'],
        ];
    }
}
