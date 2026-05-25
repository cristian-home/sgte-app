<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DriverInviteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::UPDATE_DRIVERS->value)
            && Gate::allows(Permission::CREATE_USERS->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'account_email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_email.required' => 'Indica el correo para la cuenta de acceso del conductor.',
            'account_email.unique' => 'Ya existe un usuario con ese correo.',
        ];
    }
}
