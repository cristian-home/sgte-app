<?php

namespace App\Enums;

enum DayStatusEnum: string
{
    case Projected = 'projected';
    case Executed = 'executed';

    public function label(): string
    {
        return match ($this) {
            self::Projected => 'Proyectado',
            self::Executed => 'Ejecutado',
        };
    }
}
