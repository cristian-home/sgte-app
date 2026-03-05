<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ThirdPartyUpdateRequest extends FormRequest
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
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'identification_number' => ['required', 'string', 'max:50'],
            'is_natural_person' => ['required', 'boolean'],
            'first_name' => [Rule::when($this->boolean('is_natural_person'), ['required', 'string', 'max:100'], ['nullable', 'string', 'max:100'])],
            'second_name' => ['nullable', 'string', 'max:100'],
            'first_lastname' => [Rule::when($this->boolean('is_natural_person'), ['required', 'string', 'max:100'], ['nullable', 'string', 'max:100'])],
            'second_lastname' => ['nullable', 'string', 'max:100'],
            'company_name' => [Rule::when(! $this->boolean('is_natural_person'), ['required', 'string', 'max:200'], ['nullable', 'string', 'max:200'])],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'is_customer' => ['required', 'boolean'],
            'is_provider' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
        ];
    }
}
