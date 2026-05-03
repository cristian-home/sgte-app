<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UserResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Gate::allows(Permission::UPDATE_USERS->value)) {
            return false;
        }

        $target = $this->route('user');
        $actor = $this->user();

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
        return [];
    }
}
