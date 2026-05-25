<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Capture the viewer's IANA timezone from the browser and make it
 * available throughout the request lifecycle:
 *
 * - Source of truth (priority order): `X-Viewer-Timezone` request header,
 *   `viewer_tz` cookie. The header wins because the frontend can update
 *   it on every Inertia visit; the cookie is the bootstrap value before
 *   the SPA boots.
 * - Validation: must be a known IANA identifier; unknown values are
 *   silently ignored (the frontend will retry on the next visit).
 * - Storage: when a user is authenticated and the captured TZ differs
 *   from the row, persist on `users.timezone` (best-effort, never blocks
 *   the request).
 * - Exposure: writes to the request attribute bag so `App\Support\Tz`
 *   and `HandleInertiaRequests` can read it without re-parsing headers.
 *
 * Order: must run before `HandleInertiaRequests` so the shared `config.viewer_tz`
 * prop reflects the value captured this turn.
 */
class CaptureViewerTimezone
{
    public const REQUEST_ATTRIBUTE = 'viewer_tz';

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tz = $this->resolveTimezone($request);

        if ($tz !== null) {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $tz);
            $this->maybePersistOnUser($request, $tz);
        }

        return $next($request);
    }

    /**
     * Pick the most authoritative TZ value the request carries. Returns
     * null when nothing valid is present so the caller can fall through
     * to operation TZ via `App\Support\Tz::viewer()`.
     */
    protected function resolveTimezone(Request $request): ?string
    {
        $candidates = [
            $request->header('X-Viewer-Timezone'),
            $request->cookie('viewer_tz'),
        ];

        $valid = timezone_identifiers_list();

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }
            $trimmed = trim($candidate);
            if ($trimmed === '') {
                continue;
            }
            if (in_array($trimmed, $valid, true)) {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * Best-effort sync of the captured TZ onto the authenticated user's
     * row. Wrapped in a try/catch so a flaky DB connection or read-only
     * replica never aborts the request.
     */
    protected function maybePersistOnUser(Request $request, string $tz): void
    {
        $user = $request->user();
        if ($user === null) {
            return;
        }
        if ($user->timezone === $tz) {
            return;
        }

        try {
            $user->forceFill(['timezone' => $tz])->saveQuietly();
        } catch (\Throwable) {
            // Never let a bookkeeping write break the user's request.
        }
    }
}
