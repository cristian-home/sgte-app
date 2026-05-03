<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UserToggleActiveRequest extends FormRequest
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

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->preventDeactivatingSelf($validator);
            },
        ];
    }

    private function preventDeactivatingSelf(Validator $validator): void
    {
        /** @var User|null $target */
        $target = $this->route('user');
        $actor = $this->user();

        if (! $target || ! $actor || $target->id !== $actor->id) {
            return;
        }

        // Only block when the operation would result in deactivation.
        if ($target->is_active) {
            $validator->errors()->add(
                'is_active',
                'No puedes desactivar tu propia cuenta.',
            );
        }
    }
}
