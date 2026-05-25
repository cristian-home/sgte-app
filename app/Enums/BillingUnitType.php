<?php

namespace App\Enums;

enum BillingUnitType: string
{
    case Viaje = 'viaje';
    case Pasajero = 'pasajero';
    case Dia = 'dia';
    case Hora = 'hora';

    public function label(): string
    {
        return match ($this) {
            self::Viaje => 'Viaje',
            self::Pasajero => 'Pasajero',
            self::Dia => 'Día',
            self::Hora => 'Hora',
        };
    }

    /**
     * Pluralized Spanish label for the "Cantidad (…)" slot on the
     * service form: "Cantidad (pasajeros)", "Cantidad (días)", etc.
     */
    public function quantityLabel(): string
    {
        return match ($this) {
            self::Viaje => 'viajes',
            self::Pasajero => 'pasajeros',
            self::Dia => 'días',
            self::Hora => 'horas',
        };
    }
}
