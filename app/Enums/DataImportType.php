<?php

namespace App\Enums;

enum DataImportType: string
{
    case Users = 'users';
    case ThirdParties = 'third_parties';
    case Drivers = 'drivers';
    case Vehicles = 'vehicles';

    public function label(): string
    {
        return match ($this) {
            self::Users => 'Usuarios',
            self::ThirdParties => 'Terceros',
            self::Drivers => 'Conductores',
            self::Vehicles => 'Vehículos',
        };
    }
}
