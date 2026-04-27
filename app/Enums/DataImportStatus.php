<?php

namespace App\Enums;

enum DataImportStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En cola',
            self::Processing => 'Procesando',
            self::Completed => 'Completado',
            self::Failed => 'Falló',
        };
    }
}
