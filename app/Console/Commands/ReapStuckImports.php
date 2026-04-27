<?php

namespace App\Console\Commands;

use App\Enums\DataImportStatus;
use App\Models\DataImport;
use Illuminate\Console\Command;

class ReapStuckImports extends Command
{
    protected $signature = 'imports:reap-stuck';

    protected $description = 'Marca como failed los imports cuyo worker murió sin oportunidad de marcar el estado';

    public function handle(): int
    {
        $count = DataImport::query()
            ->where('status', DataImportStatus::Processing->value)
            ->where('started_at', '<', now()->subMinutes(35))
            ->update([
                'status' => DataImportStatus::Failed->value,
                'error_message' => 'Job interrumpido (timeout o caída del worker).',
                'completed_at' => now(),
            ]);

        $this->info("Reaped {$count} stuck imports.");

        return self::SUCCESS;
    }
}
