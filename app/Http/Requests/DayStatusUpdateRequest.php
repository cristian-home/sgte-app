<?php

namespace App\Http\Requests;

use App\Enums\DayStatusEnum;
use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DayStatusUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows(Permission::EXECUTE_DAY->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'date' => ['required', 'date', Rule::unique('day_statuses', 'date')->ignore($this->route('day_status'))],
            'status' => ['required', Rule::enum(DayStatusEnum::class)],
            'executor_id' => ['nullable', 'integer', 'exists:users,id'],
            'executed_at' => ['nullable'],
            // Q3 / bug-log:BUG-05 — Super Admin override on
            // executed → projected reversal must supply a justification.
            // Non-SA reversals are rejected outright in after().
            'justification' => ['nullable', 'string', 'min:10', 'max:500'],
        ];

        return $rules;
    }

    /**
     * EJECUTADO is permanent for Admin and Operator. Only Super Admin may
     * revert a day to projected, and only with a `justification`. See
     * Q3 / bug-log:BUG-05.
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $dayStatus = $this->route('day_status');

                if (! $dayStatus instanceof \App\Models\DayStatus) {
                    return;
                }

                $currentStatus = $dayStatus->status instanceof DayStatusEnum
                    ? $dayStatus->status
                    : DayStatusEnum::tryFrom((string) $dayStatus->status);

                if ($currentStatus !== DayStatusEnum::Executed) {
                    return;
                }

                $incomingStatus = $this->input('status');
                if ($incomingStatus !== DayStatusEnum::Projected->value) {
                    return;
                }

                $user = $this->user();

                if (! $user || ! $user->hasRole(Role::SUPER_ADMIN->value)) {
                    $validator->errors()->add(
                        'status',
                        'No se puede revertir un día ejecutado a proyectado. Esta acción está reservada al Super Administrador.',
                    );

                    return;
                }

                $justification = trim((string) $this->input('justification'));
                if ($justification === '') {
                    $validator->errors()->add(
                        'justification',
                        'La justificación es obligatoria para revertir un día ejecutado.',
                    );
                }
            },
        ];
    }
}
