<?php

namespace App\Enums;

enum ContractObject: string
{
    case Business = 'business';
    case Tourism = 'tourism';
    case Health = 'health';
    case Occasional = 'occasional';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Empresarial',
            self::Tourism => 'Turismo',
            self::Health => 'Salud',
            self::Occasional => 'Ocasional',
        };
    }
}
