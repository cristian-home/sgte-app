<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * FUEC cancellation input. Reason is mandatory (min:10, max:500) so
 * the audit log carries a human-readable justification per REQ-007.
 */
class FuecCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::GENERATE_FUEC->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'El motivo de anulación es obligatorio.',
            'reason.string' => 'El motivo debe ser texto.',
            'reason.min' => 'El motivo debe tener al menos 10 caracteres.',
            'reason.max' => 'El motivo no puede superar 500 caracteres.',
        ];
    }
}
