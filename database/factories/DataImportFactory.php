<?php

namespace Database\Factories;

use App\Enums\DataImportStatus;
use App\Enums\DataImportType;
use App\Models\DataImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DataImport>
 */
class DataImportFactory extends Factory
{
    protected $model = DataImport::class;

    public function definition(): array
    {
        $type = fake()->randomElement(DataImportType::cases());
        $ulid = (string) Str::ulid();

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'original_filename' => $type->value.'_'.fake()->word().'.csv',
            'disk' => 's3',
            'path' => "imports/{$type->value}/{$ulid}.csv",
            'errors_path' => null,
            'status' => DataImportStatus::Queued,
            'dry_run' => false,
            'update_existing' => false,
            'rows_total' => null,
            'rows_processed' => 0,
            'rows_created' => 0,
            'rows_updated' => 0,
            'rows_skipped' => 0,
            'rows_errored' => 0,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'files_purged_at' => null,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn () => ['status' => DataImportStatus::Queued]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => DataImportStatus::Processing,
            'started_at' => now(),
            'rows_total' => 100,
            'rows_processed' => 30,
            'rows_created' => 25,
            'rows_skipped' => 5,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => DataImportStatus::Completed,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
            'rows_total' => 100,
            'rows_processed' => 100,
            'rows_created' => 90,
            'rows_updated' => 5,
            'rows_skipped' => 3,
            'rows_errored' => 2,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => DataImportStatus::Failed,
            'started_at' => now()->subMinutes(1),
            'completed_at' => now(),
            'error_message' => 'Faltan columnas en el archivo: [email]. Descargue la plantilla actualizada.',
        ]);
    }

    public function dryRun(): static
    {
        return $this->state(fn () => ['dry_run' => true]);
    }

    public function withErrors(): static
    {
        return $this->state(fn () => [
            'errors_path' => function (array $attrs) {
                $path = $attrs['path'] ?? 'imports/users/x.csv';

                return str_replace('.csv', '_errors.csv', $path);
            },
            'rows_errored' => 5,
        ]);
    }

    public function purged(): static
    {
        return $this->completed()->state(fn () => [
            'path' => null,
            'errors_path' => null,
            'files_purged_at' => now(),
        ]);
    }
}
