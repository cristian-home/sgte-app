<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BillingGroupUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::UPDATE_BILLING_GROUPS->value);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('billing_groups', 'code')->ignore($this->billing_group),
            ],
            'name' => ['required', 'string', 'max:100'],
            'active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'El código solo admite minúsculas, números, guiones y guiones bajos.',
            'code.unique' => 'Ya existe un grupo con ese código.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->code)) {
            $this->merge(['code' => strtolower(trim($this->code))]);
        }
    }
}
