<?php

namespace App\Enums;

enum LicenseCategory: string
{
    case C1 = 'C1';
    case C2 = 'C2';
    case C3 = 'C3';

    public function label(): string
    {
        return match ($this) {
            self::C1 => 'C1',
            self::C2 => 'C2',
            self::C3 => 'C3',
        };
    }
}
