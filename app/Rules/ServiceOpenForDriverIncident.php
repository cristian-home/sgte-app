<?php

namespace App\Rules;

use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Models\Service;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * Rejects incident creation by a driver when the target service is
 * already closed. A closed service is finalized: the driver's work on
 * it is done and its data must stay immutable from the driver side.
 *
 * Mirrors ServiceBelongsToAuthenticatedDriver — only the driver role is
 * scoped. Admin / operator / accounting (and super-admin) manage the
 * dispatch side and may still log incidents against closed services.
 */
class ServiceOpenForDriverIncident implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();

        if ($user === null || ! $user->hasRole(Role::DRIVER->value) || $user->hasRole(Role::SUPER_ADMIN->value)) {
            return;
        }

        $service = Service::query()->find($value);

        if ($service !== null && $service->service_status === ServiceStatus::Closed) {
            $fail('No puede registrar novedades en un servicio cerrado.');
        }
    }
}
