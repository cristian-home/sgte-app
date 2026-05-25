<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Maintenance => 'En Mantenimiento',
            self::Retired => 'Retirado',
        };
    }
}
