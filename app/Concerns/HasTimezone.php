<?php

namespace App\Concerns;

use App\Support\Tz;

/**
 * Mixin for models that carry their own IANA `timezone` column. Mirrors
 * the pattern already in `App\Models\Service` so date / datetime accessors
 * that project an instant to a wall-clock can rely on a single
 * `resolveTimezone()` method instead of hand-rolling the fallback.
 *
 * Models applying this trait MUST have:
 * - a string `timezone` column (VARCHAR(64) NOT NULL, default operation TZ).
 * - cast `'timezone' => 'string'` (or omit; default cast is fine).
 */
trait HasTimezone
{
    /**
     * Resolve the model's IANA timezone, falling back to operation TZ when
     * the column is missing or empty. Always returns a valid IANA string.
     */
    public function resolveTimezone(): string
    {
        $raw = $this->getAttribute('timezone');
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed !== '' && in_array($trimmed, timezone_identifiers_list(), true)) {
                return $trimmed;
            }
        }

        return Tz::operation();
    }
}
