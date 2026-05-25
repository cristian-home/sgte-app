<?php

namespace App\Exceptions;

use App\Models\FuecNumberRange;
use RuntimeException;

/**
 * Raised by `FuecGenerator` when the active MinTransporte range has
 * no consecutive numbers remaining. The caller must translate this
 * into a user-facing Spanish validation error so the admin knows to
 * register a new range.
 */
class FuecRangeExhaustedException extends RuntimeException
{
    public function __construct(
        public readonly FuecNumberRange $range,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? sprintf(
                'El rango MinTransporte activo (%s de %d) se agotó en el consecutivo %d.',
                $range->resolution_number,
                $range->resolution_year,
                $range->range_to,
            ),
        );
    }

    public static function for(FuecNumberRange $range): self
    {
        return new self($range);
    }
}
