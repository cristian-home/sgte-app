<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force users with `must_change_password = true` to change their password
 * before accessing anything else. Triggered by:
 *
 * - Bulk imports created with an empty `password` column (UserImporter
 *   autogenerates a password and flips the flag, so the user is sent to
 *   the password page on their first login).
 * - Future: any flow that hands out a temporary credential.
 *
 * Whitelisted routes: the password edit/update pair (so the user can comply),
 * the email-verification routes (so a user who is also unverified isn't bounced
 * into an infinite loop against the `verified` middleware), logout, and Fortify
 * 2FA challenges (so 2FA still works at first login).
 */
class EnsurePasswordChanged
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_ROUTE_NAMES = [
        'user-password.edit',
        'user-password.update',
        'verification.notice',
        'verification.verify',
        'verification.send',
        'logout',
        'two-factor.login',
        'two-factor.show',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName !== null && in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        // Don't break partial Inertia requests; let them respond as usual so
        // the SPA layer can stay coherent. The very next full-page navigation
        // will redirect.
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return $next($request);
        }
        if ($request->header('X-Inertia-Partial-Data')) {
            return $next($request);
        }

        return redirect()->route('user-password.edit')
            ->with('warning', 'Debes cambiar tu contraseña antes de continuar.');
    }
}
