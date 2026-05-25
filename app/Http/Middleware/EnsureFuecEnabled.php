<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every FUEC-related route behind the `sgte.fuec_enabled`
 * config flag. When disabled, the module's routes (including the
 * public QR verification endpoint) return 404 — as if the module
 * didn't exist. The underlying code remains installed so the flag
 * can be flipped back on without redeploying.
 */
class EnsureFuecEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('sgte.fuec_enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
