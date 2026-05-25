<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Centralized timezone resolution helpers.
 *
 * - `operation()` — the project-wide operation TZ (config('app.operation_tz'),
 *   typically America/Bogota). The right answer when "what calendar day is it
 *   in the business?" is the question.
 * - `viewer()` — the viewer's own browser TZ if the
 *   `App\Http\Middleware\CaptureViewerTimezone` middleware has run, else
 *   falls back to operation TZ. The right answer when rendering audit-style
 *   timestamps for a specific viewer.
 * - `for($modelOrTz)` — resolves a TZ for a specific business record. Looks
 *   for a `timezone` column / property; falls back to operation TZ.
 *
 * Used by anything that needs to know "which TZ should I use right now?"
 * without sprinkling `config('app.operation_tz')` calls all over the place.
 */
class Tz
{
    /**
     * The project-wide operation TZ. Use for "today in operation" decisions
     * (driver dashboard window, retroactive entry gate, contract / vehicle
     * doc validity at the moment of service creation, etc.).
     */
    public static function operation(): string
    {
        return (string) config('app.operation_tz', 'America/Bogota');
    }

    /**
     * The TZ the viewer's browser reported on the current request, set by
     * `CaptureViewerTimezone`. Falls back to operation TZ when the request
     * carries no header / cookie or when the value is unknown to PHP.
     */
    public static function viewer(?Request $request = null): string
    {
        $request ??= request();
        $tz = $request?->attributes->get('viewer_tz');
        if (is_string($tz) && $tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return self::operation();
    }

    /**
     * Resolve the TZ for a record. Accepts either a model with a `timezone`
     * attribute / property, or a TZ string directly. Falls back to operation
     * TZ when missing or unknown.
     */
    public static function for(mixed $modelOrTz): string
    {
        if (is_string($modelOrTz)) {
            $tz = trim($modelOrTz);
        } elseif (is_object($modelOrTz)) {
            $raw = $modelOrTz->timezone ?? null;
            $tz = is_string($raw) ? trim($raw) : '';
        } else {
            $tz = '';
        }

        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return self::operation();
    }

    /**
     * "Now" projected into the given TZ as a CarbonImmutable. Convenience
     * wrapper so call sites don't have to repeat `CarbonImmutable::now($tz)`.
     */
    public static function nowIn(string $tz): CarbonImmutable
    {
        return CarbonImmutable::now($tz);
    }

    /**
     * UTC instant of midnight (start-of-day) of `$date` interpreted in `$tz`.
     * Used by migrations / form requests when projecting a wall-clock day to
     * an instant for storage.
     *
     * Example: `startOfDayInTzAsUtc('2026-05-08', 'America/Bogota')` →
     * `2026-05-08T05:00:00+00:00`.
     */
    public static function startOfDayInTzAsUtc(string $date, string $tz): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} 00:00", $tz)
            ->startOfDay()
            ->utc();
    }

    /**
     * UTC instant of the *next* midnight after `$date` in `$tz` — i.e. the
     * exclusive end of a half-open day-range `[startOfDayInTzAsUtc, endOfDayInTzAsUtc)`.
     *
     * Used for the "expires at the close of `$date`" semantics: an SOAT due
     * 2026-05-08 in Bogotá is valid right up to 2026-05-09T05:00:00+00:00.
     */
    public static function endOfDayInTzAsUtc(string $date, string $tz): CarbonImmutable
    {
        return self::startOfDayInTzAsUtc($date, $tz)->addDay();
    }
}
