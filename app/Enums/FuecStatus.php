<?php

namespace App\Enums;

enum FuecStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Cancelled => 'Cancelado',
        };
    }
}
