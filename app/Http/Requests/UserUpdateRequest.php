<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Gate::allows(Permission::UPDATE_USERS->value)) {
            return false;
        }

        $target = $this->route('user');
        $actor = $this->user();

        // Only a super_admin can edit a super_admin user.
        if ($target instanceof User
            && $target->hasRole(Role::SUPER_ADMIN->value)
            && ! $actor?->hasRole(Role::SUPER_ADMIN->value)
        ) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(UserStoreRequest::assignableRoleValues())],
            'is_active' => ['required', 'boolean'],
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

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->ensureLastAdminKeepsAdminRole($validator);
            },
            function (Validator $validator): void {
                $this->ensureDriverRoleCoherence($validator);
            },
        ];
    }

    /**
     * El rol Driver requiere un registro Driver vinculado y es exclusivo.
     * No se permite quitarle el rol Driver a un usuario que sigue vinculado
     * a un Driver — primero debe desvincularse desde el módulo Conductores.
     */
    private function ensureDriverRoleCoherence(Validator $validator): void
    {
        /** @var User|null $target */
        $target = $this->route('user');
        if (! $target) {
            return;
        }

        if ($target->hasRole(Role::DRIVER->value) && $target->driver !== null) {
            $newRoles = (array) $this->input('roles', []);
            if (! in_array(Role::DRIVER->value, $newRoles, true)) {
                $validator->errors()->add(
                    'roles',
                    'Este usuario está vinculado a un conductor. Para cambiarle el rol, primero desvincula o elimina el conductor desde el módulo Conductores.',
                );
            }
        }
    }

    private function ensureLastAdminKeepsAdminRole(Validator $validator): void
    {
        /** @var User|null $target */
        $target = $this->route('user');
        $actor = $this->user();

        if (! $target || ! $actor || $target->id !== $actor->id) {
            return;
        }

        if (! $target->hasRole(Role::ADMIN->value)) {
            return;
        }

        $newRoles = (array) $this->input('roles', []);
        if (in_array(Role::ADMIN->value, $newRoles, true)) {
            return;
        }

        $remainingAdmins = User::role(Role::ADMIN->value)->where('id', '!=', $target->id)->count();
        if ($remainingAdmins === 0) {
            $validator->errors()->add(
                'roles',
                'Eres el último administrador del sistema. No puedes quitarte el rol Admin.',
            );
        }
    }
}
