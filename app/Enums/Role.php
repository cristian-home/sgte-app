<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case OPERATOR = 'operator';
    case DRIVER = 'driver';
    case ACCOUNTING = 'accounting';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrador',
            self::OPERATOR => 'Operación',
            self::DRIVER => 'Conductor',
            self::ACCOUNTING => 'Contabilidad',
        };
    }
}
