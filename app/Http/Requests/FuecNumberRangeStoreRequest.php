<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FuecNumberRangeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::MANAGE_FUEC_NUMBER_RANGES->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution_number' => ['required', 'string', 'max:50'],
            'resolution_year' => ['required', 'integer', 'between:2000,2100'],
            'range_from' => ['required', 'integer', 'min:1'],
            'range_to' => ['required', 'integer', 'gt:range_from'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'range_to.gt' => 'El número inicial debe ser menor que el final.',
            'resolution_year.between' => 'El año de la resolución no es válido.',
        ];
    }
}
