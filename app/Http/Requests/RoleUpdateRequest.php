<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RoleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Gate::allows(Permission::UPDATE_USERS->value)) {
            return false;
        }

        $role = $this->route('role');
        if ($role instanceof Role && $role->name === RoleEnum::SUPER_ADMIN->value) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['present', 'array'],
            'permissions.*' => [
                'string',
                Rule::in(array_map(fn (Permission $p) => $p->value, Permission::cases())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.*.in' => 'Permiso no válido.',
            'description.max' => 'La descripción no puede exceder 500 caracteres.',
        ];
    }
}
