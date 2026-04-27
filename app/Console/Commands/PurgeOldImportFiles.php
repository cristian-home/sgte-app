<?php

namespace App\Console\Commands;

use App\Models\DataImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeOldImportFiles extends Command
{
    protected $signature = 'imports:purge-old-files';

    protected $description = 'Borra archivos de imports completados hace más de 90 días (la fila data_imports se conserva como audit trail)';

    public function handle(): int
    {
        $cutoff = now()->subDays(90);
        $count = 0;

        DataImport::query()
            ->whereNotNull('path')
            ->whereNull('files_purged_at')
            ->where('completed_at', '<', $cutoff)
            ->lazyById(100)
            ->each(function (DataImport $import) use (&$count): void {
                $disk = Storage::disk($import->disk);

                if ($import->path !== null) {
                    $disk->delete($import->path);
                }
                if ($import->errors_path !== null) {
                    $disk->delete($import->errors_path);
                }

                $import->update([
                    'path' => null,
                    'errors_path' => null,
                    'files_purged_at' => now(),
                ]);

                $count++;
            });

        $this->info("Purgados {$count} imports.");

        return self::SUCCESS;
    }
}
