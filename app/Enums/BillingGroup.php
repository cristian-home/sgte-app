<?php

namespace App\Enums;

enum BillingGroup: string
{
    case Salud = 'salud';
    case Escolar = 'escolar';
    case Turismo = 'turismo';
    case Empresarial = 'empresarial';
    case Ocasional = 'ocasional';

    public function label(): string
    {
        return match ($this) {
            self::Salud => 'Salud',
            self::Escolar => 'Escolar',
            self::Turismo => 'Turismo',
            self::Empresarial => 'Empresarial',
            self::Ocasional => 'Ocasional',
        };
    }
}
