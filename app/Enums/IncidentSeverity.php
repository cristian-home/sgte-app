<?php

namespace App\Enums;

enum IncidentSeverity: string
{
    case Informational = 'informational';
    case Minor = 'minor';
    case Major = 'major';

    public function label(): string
    {
        return match ($this) {
            self::Informational => 'Informativo',
            self::Minor => 'Menor',
            self::Major => 'Mayor',
        };
    }
}
