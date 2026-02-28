<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContractStoreRequest extends FormRequest
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
            'contract_number' => ['required', 'string', 'max:50'],
            'third_party_id' => ['required', 'integer', 'exists:third_parties,id'],
            'contract_object' => ['required', 'in:business,tourism,health,occasional'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'route_description' => ['required', 'string'],
            'is_generic' => ['required'],
            'active' => ['required'],
        ];
    }
}
