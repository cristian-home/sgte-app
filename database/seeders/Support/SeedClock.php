<?php

namespace Database\Seeders\Support;

use App\Support\Tz;
use Carbon\CarbonImmutable;

/**
 * The single source of "now" for the operational seeders.
 *
 * Every seeder that anchors data to the operational calendar — services,
 * day statuses, contracts, invoices, vehicle locations — goes through
 * this helper instead of calling `Carbon::now()` / `Carbon::today()`
 * directly. That way the curated dataset shifts coherently as the
 * system clock advances and the relationship between days (today's
 * services vs. yesterday's vs. tomorrow's) is preserved no matter when
 * `db:seed` is invoked.
 */
final class SeedClock
{
    /**
     * Operation timezone (typically America/Bogota).
     */
    public static function tz(): string
    {
        return Tz::operation();
    }

    /**
     * Start-of-day "today" in the operation timezone.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::tz())->startOfDay();
    }

    /**
     * Start-of-day (today + $days) in the operation timezone.
     */
    public static function dayOffset(int $days): CarbonImmutable
    {
        return self::today()->addDays($days);
    }

    /**
     * UTC instant for (today + $dayOffset) at the wall-clock HH:mm provided,
     * interpreted in the operation timezone.
     */
    public static function at(int $dayOffset, string $hhmm): CarbonImmutable
    {
        $date = self::dayOffset($dayOffset)->toDateString();

        return CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$hhmm}", self::tz())->utc();
    }

    /**
     * Y-m-d string for (today + $days) in the operation timezone — handy
     * when the column is `immutable_date:Y-m-d` and only the calendar day
     * matters.
     */
    public static function dateString(int $days): string
    {
        return self::dayOffset($days)->toDateString();
    }
}
