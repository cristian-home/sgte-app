<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force-logout an authenticated user whose account was deactivated mid-session.
 *
 * `Fortify::authenticateUsing()` already blocks login for inactive accounts,
 * but admin-driven deactivations need to take effect on existing sessions too.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Esta cuenta está desactivada.']);
        }

        return $next($request);
    }
}
