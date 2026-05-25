<?php

namespace Tests\Helpers;

use Closure;

/**
 * Cross-timezone harness for Pest tests.
 *
 * `Tz::with('Asia/Tokyo', fn() => ...)` runs the closure with PHP's
 * default timezone temporarily set to Tokyo, then restores the prior
 * value in a finally block. Used to assert that operational gates
 * (REQ-009 retroactive entry, REQ-004/005 expiry checks, Gantt date
 * filter, document re-checks) all anchor "today/now" on the operation
 * timezone — never on the host PHP TZ.
 */
final class Tz
{
    /**
     * Run a closure with `date_default_timezone_set($tz)`, restoring
     * the previous value after the closure returns or throws.
     *
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    public static function with(string $tz, Closure $fn): mixed
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set($tz);
        try {
            return $fn();
        } finally {
            date_default_timezone_set($previous);
        }
    }

    /**
     * The four host TZs the cross-TZ suite runs with: a UTC baseline,
     * a +9 offset, a -7/-8 DST-affected zone, and Western Europe.
     *
     * @return list<string>
     */
    public static function crossTimezones(): array
    {
        return ['UTC', 'Asia/Tokyo', 'America/Los_Angeles', 'Europe/Madrid'];
    }
}
