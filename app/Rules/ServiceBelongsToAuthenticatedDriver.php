<?php

namespace App\Rules;

use App\Enums\Role;
use App\Models\Service;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * Ensures that — when the authenticated user has the driver role
 * (and is not a super-admin) — the target service_id belongs to a
 * service assigned to that driver's own Driver record.
 *
 * Navigation controls visibility, not authorization: a driver could
 * otherwise submit an incident for another driver's service by
 * guessing the id. Enforcing this at the FormRequest layer means
 * the 422 response carries the Spanish error message directly on
 * the field (cleaner UX than a 403 interstitial).
 */
class ServiceBelongsToAuthenticatedDriver implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();

        // Super-admin bypasses via Gate::before; no additional
        // scoping required for admin / operator / accounting because
        // they manage the dispatch side of the operation.
        if ($user === null || ! $user->hasRole(Role::DRIVER->value) || $user->hasRole(Role::SUPER_ADMIN->value)) {
            return;
        }

        $driver = $user->driver;
        if ($driver === null) {
            $fail('Solo puede registrar novedades en sus propios servicios.');

            return;
        }

        $service = Service::query()
            ->where('id', $value)
            ->where('driver_id', $driver->id)
            ->exists();

        if (! $service) {
            $fail('Solo puede registrar novedades en sus propios servicios.');
        }
    }
}
