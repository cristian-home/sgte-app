<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every GPS-related route behind the `sgte.gps_enabled`
 * config flag. When disabled, the module's routes (driver POST
 * endpoint + admin map + vehicle-locations CRUD) return 404 — as
 * if the module didn't exist. The underlying code remains installed
 * so the flag can be flipped back on without redeploying.
 *
 * Parallels `EnsureFuecEnabled` (fuec-generation).
 */
class EnsureGpsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('sgte.gps_enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
