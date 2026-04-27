<?php

namespace App\Jobs;

use App\Enums\DataImportStatus;
use App\Models\DataImport;
use App\Services\Imports\ImporterRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Throwable;

/**
 * Processes a queued bulk import. Runs on the dedicated `imports` queue
 * (supervisor-imports, maxProcesses=1) so two concurrent uploads can never
 * step on each other. tries=1 because partial failures should not auto-retry
 * — the user reviews the errors.csv and corrects the file manually.
 */
class ProcessDataImportJob implements ShouldQueue
{
    use FoundationQueueable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $queue = 'imports';

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public DataImport $import) {}

    public function handle(ImporterRegistry $registry): void
    {
        $this->import->update([
            'status' => DataImportStatus::Processing,
            'started_at' => now(),
        ]);

        $importer = $registry->for($this->import->type);
        $disk = Storage::disk($this->import->disk);
        $localPath = $this->ensureLocalCopy($disk);

        try {
            $headerCheck = $importer->validateHeader($localPath);
            if (! $headerCheck->ok) {
                $this->import->update([
                    'status' => DataImportStatus::Failed,
                    'error_message' => $headerCheck->error,
                    'completed_at' => now(),
                ]);

                return;
            }

            $rowsTotal = $importer->countRows($localPath);
            $this->import->update(['rows_total' => $rowsTotal]);

            $errorsLocalPath = tempnam(sys_get_temp_dir(), 'errors_').'.csv';
            $errorsWriter = SimpleExcelWriter::create($errorsLocalPath);
            $errorsWriter->addHeader(['row_number', 'error_message', 'original_data']);

            $reader = SimpleExcelReader::create($localPath);
            $counters = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errored' => 0];
            $processed = 0;
            $persistEvery = max(1, (int) (200 / 1)); // flush every 200 rows

            $importer->processFile(
                $reader,
                $this->import,
                $errorsWriter,
                function (array $delta) use (&$counters, &$processed, $persistEvery) {
                    foreach ($delta as $key => $value) {
                        $counters[$key] += $value;
                    }
                    $processed += array_sum($delta);

                    if ($processed % $persistEvery === 0) {
                        $this->import->update([
                            'rows_processed' => $processed,
                            'rows_created' => $counters['created'],
                            'rows_updated' => $counters['updated'],
                            'rows_skipped' => $counters['skipped'],
                            'rows_errored' => $counters['errored'],
                        ]);
                    }
                },
            );

            $reader->close();
            $errorsWriter->close();

            $errorsRemotePath = null;
            if ($counters['errored'] > 0) {
                $errorsRemotePath = $this->errorsRemotePathFor($this->import->path ?? '');
                $disk->put($errorsRemotePath, (string) file_get_contents($errorsLocalPath));
            }
            @unlink($errorsLocalPath);

            $this->import->update([
                'status' => DataImportStatus::Completed,
                'rows_processed' => $processed,
                'rows_created' => $counters['created'],
                'rows_updated' => $counters['updated'],
                'rows_skipped' => $counters['skipped'],
                'rows_errored' => $counters['errored'],
                'errors_path' => $errorsRemotePath,
                'completed_at' => now(),
            ]);
        } finally {
            // Local copy cleanup for non-local disks.
            if ($localPath !== ($disk->path($this->import->path ?? '') ?? null) && file_exists($localPath)) {
                @unlink($localPath);
            }
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('ProcessDataImportJob failed', [
            'import_id' => $this->import->id,
            'exception' => $e,
        ]);

        $this->import->update([
            'status' => DataImportStatus::Failed,
            'error_message' => substr($e->getMessage(), 0, 1000),
            'completed_at' => now(),
        ]);
    }

    /**
     * Returns a local filesystem path the importer can read from.
     * For non-local disks (S3/MinIO), downloads the file to a temp path —
     * simple-excel cannot stream directly from an S3 stream wrapper for XLSX.
     */
    private function ensureLocalCopy(\Illuminate\Contracts\Filesystem\Filesystem $disk): string
    {
        $remotePath = $this->import->path;
        if (! $remotePath) {
            throw new \RuntimeException('Import has no source path.');
        }

        $adapterClass = method_exists($disk, 'getAdapter') ? $disk->getAdapter()::class : '';
        $isLocal = str_contains($adapterClass, 'Local') || str_contains($adapterClass, 'InMemory');

        if ($isLocal && method_exists($disk, 'path')) {
            return $disk->path($remotePath);
        }

        $extension = pathinfo($remotePath, PATHINFO_EXTENSION) ?: 'csv';
        $tempPath = tempnam(sys_get_temp_dir(), 'import_').'.'.$extension;
        file_put_contents($tempPath, $disk->get($remotePath));

        return $tempPath;
    }

    private function errorsRemotePathFor(string $sourcePath): string
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $base = substr($sourcePath, 0, -1 * (strlen($extension) + 1));

        return $base.'_errors.csv';
    }
}
