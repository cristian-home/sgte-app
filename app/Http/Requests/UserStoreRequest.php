<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::CREATE_USERS->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(self::assignableRoleValues())],
            'is_active' => ['required', 'boolean'],
            'send_welcome_email' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'Debes asignar al menos un rol.',
            'roles.min' => 'Debes asignar al menos un rol.',
            'roles.*.in' => 'Rol no válido.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function assignableRoleValues(): array
    {
        return array_map(fn (Role $r) => $r->value, [
            Role::ADMIN,
            Role::OPERATOR,
            Role::DRIVER,
            Role::ACCOUNTING,
        ]);
    }
}
