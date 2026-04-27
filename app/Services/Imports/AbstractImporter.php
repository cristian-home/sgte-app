<?php

namespace App\Services\Imports;

use App\Models\DataImport;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

abstract class AbstractImporter
{
    /**
     * Number of rows per transactional chunk. Tests can override via reflection
     * if needed; production value balances throughput and memory headroom.
     */
    protected int $chunkSize = 200;

    /**
     * @return array<int, string>
     */
    abstract public function expectedHeaders(): array;

    abstract public function naturalKey(): string;

    /**
     * @return array<string, array<int, mixed>>
     */
    abstract public function rules(): array;

    /**
     * @return array<string, string>
     */
    abstract public function messages(): array;

    /**
     * Resolve human-readable inputs to FK ids and other domain conversions.
     * May throw {@see RowTransformException} when an input cannot be resolved.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    abstract public function transformRow(array $row): array;

    /**
     * Look up an existing record by the natural key. Returns null when missing.
     */
    abstract public function findExisting(string $naturalKeyValue): ?Model;

    /**
     * @param  array<string, mixed>  $data
     */
    abstract public function persistNew(array $data): Model;

    /**
     * @param  array<string, mixed>  $data
     */
    abstract public function applyUpdate(Model $existing, array $data): Model;

    public function validateHeader(string $path): HeaderCheck
    {
        $reader = SimpleExcelReader::create($path);
        $first = $reader->getRows()->first() ?? [];
        $reader->close();
        $actual = array_keys($first);

        $expected = $this->expectedHeaders();
        $missing = array_diff($expected, $actual);

        if (count($missing) > 0) {
            return new HeaderCheck(false,
                'Faltan columnas en el archivo: '.implode(', ', $missing).
                '. Descargue la plantilla actualizada.'
            );
        }

        return new HeaderCheck(true);
    }

    public function countRows(string $path): int
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return 0;
            }
            $count = -1; // discount header
            while (! feof($handle)) {
                $chunk = fread($handle, 65536);
                if ($chunk === false) {
                    break;
                }
                $count += substr_count($chunk, "\n");
            }
            fclose($handle);

            return max($count, 0);
        }

        // XLSX: streaming iteration via simple-excel
        $count = 0;
        $reader = SimpleExcelReader::create($path);
        foreach ($reader->getRows() as $row) {
            $count++;
        }
        $reader->close();

        return $count;
    }

    public function processFile(
        SimpleExcelReader $reader,
        DataImport $import,
        SimpleExcelWriter $errorsWriter,
        Closure $onProgress,
    ): void {
        /** @var array<string, int> $seenKeys */
        $seenKeys = [];
        $chunk = [];
        $rowNumber = 1; // header is row 1; data rows start at 2

        foreach ($reader->getRows() as $row) {
            $rowNumber++;
            $chunk[] = ['row_number' => $rowNumber, 'data' => $row];

            if (count($chunk) >= $this->chunkSize) {
                $this->processChunk($chunk, $import, $errorsWriter, $seenKeys, $onProgress);
                $chunk = [];
            }
        }

        if (count($chunk) > 0) {
            $this->processChunk($chunk, $import, $errorsWriter, $seenKeys, $onProgress);
        }
    }

    /**
     * @param  array<int, array{row_number: int, data: array<string, mixed>}>  $chunk
     * @param  array<string, int>  $seenKeys
     */
    protected function processChunk(
        array $chunk,
        DataImport $import,
        SimpleExcelWriter $errorsWriter,
        array &$seenKeys,
        Closure $onProgress,
    ): void {
        $delta = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errored' => 0];

        DB::transaction(function () use ($chunk, $import, $errorsWriter, &$seenKeys, &$delta) {
            foreach ($chunk as $entry) {
                $row = $entry['data'];
                $rowNumber = $entry['row_number'];

                $validator = Validator::make($row, $this->rules(), $this->messages());
                if ($validator->fails()) {
                    $this->writeError($errorsWriter, $rowNumber, implode('; ', $validator->errors()->all()), $row);
                    $delta['errored']++;

                    continue;
                }

                $validated = $validator->validated();

                $key = (string) ($validated[$this->naturalKey()] ?? '');
                if ($key !== '' && isset($seenKeys[$key])) {
                    $this->writeError(
                        $errorsWriter,
                        $rowNumber,
                        "Clave duplicada en el archivo: {$this->naturalKey()}={$key} (vista en fila {$seenKeys[$key]})",
                        $row,
                    );
                    $delta['errored']++;

                    continue;
                }
                if ($key !== '') {
                    $seenKeys[$key] = $rowNumber;
                }

                try {
                    $transformed = $this->transformRow($validated);
                } catch (RowTransformException $e) {
                    $this->writeError($errorsWriter, $rowNumber, $e->getMessage(), $row);
                    $delta['errored']++;

                    continue;
                }

                $existing = $key !== '' ? $this->findExisting($key) : null;

                if ($existing !== null && ! $import->update_existing) {
                    $delta['skipped']++;

                    continue;
                }

                if ($import->dry_run) {
                    $existing !== null ? $delta['updated']++ : $delta['created']++;

                    continue;
                }

                if ($existing !== null) {
                    $this->applyUpdate($existing, $transformed);
                    $delta['updated']++;
                } else {
                    $this->persistNew($transformed);
                    $delta['created']++;
                }
            }
        });

        $onProgress($delta);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function writeError(SimpleExcelWriter $errorsWriter, int $rowNumber, string $message, array $row): void
    {
        $errorsWriter->addRow([
            'row_number' => $rowNumber,
            'error_message' => $message,
            'original_data' => json_encode($row, JSON_UNESCAPED_UNICODE) ?: '',
        ]);
    }
}
