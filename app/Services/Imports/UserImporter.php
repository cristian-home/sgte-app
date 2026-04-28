<?php

namespace App\Services\Imports;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserImporter extends AbstractImporter
{
    /**
     * Roles assignable via CSV upload. Super admin is intentionally excluded
     * — bootstrapping the bypass-everything role through a CSV import would
     * make a stray uploaded file devastating.
     *
     * @var array<int, string>
     */
    private const ALLOWED_ROLES = ['admin', 'operator', 'driver', 'accounting'];

    public function expectedHeaders(): array
    {
        return ['email', 'name', 'role', 'password'];
    }

    public function naturalKey(): string
    {
        return 'email';
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'string', 'in:'.implode(',', self::ALLOWED_ROLES)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email no es válido.',
            'name.required' => 'El nombre es obligatorio.',
            'role.required' => 'El rol es obligatorio.',
            'role.in' => 'Rol inválido. Valores permitidos: '.implode(', ', self::ALLOWED_ROLES).'.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }

    public function transformRow(array $row): array
    {
        $hasExplicitPassword = isset($row['password']) && $row['password'] !== '' && $row['password'] !== null;
        $plainPassword = $hasExplicitPassword ? $row['password'] : Str::password(16);

        return [
            'email' => $row['email'],
            'name' => $row['name'],
            'password' => Hash::make($plainPassword),
            'must_change_password' => ! $hasExplicitPassword,
            '_role' => $row['role'],
        ];
    }

    public function findExisting(string $naturalKeyValue): ?Model
    {
        return User::query()->where('email', $naturalKeyValue)->first();
    }

    public function persistNew(array $data): Model
    {
        $role = $data['_role'];
        unset($data['_role']);

        $user = User::query()->create($data);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(Role::from($role));

        return $user;
    }

    public function applyUpdate(Model $existing, array $data): Model
    {
        $role = $data['_role'];
        unset($data['_role']);

        $existing->update($data);
        $existing->syncRoles([Role::from($role)->value]);

        return $existing;
    }
}
