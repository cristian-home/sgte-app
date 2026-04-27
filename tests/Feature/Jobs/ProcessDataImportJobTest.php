<?php

namespace Tests\Feature\Jobs;

use App\Enums\DataImportStatus;
use App\Enums\DataImportType;
use App\Enums\Role;
use App\Jobs\ProcessDataImportJob;
use App\Models\DataImport;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('s3');
    foreach (Role::cases() as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
    }
});

function makeUserImport(string $csv, array $overrides = []): DataImport
{
    $disk = Storage::disk('s3');
    $path = 'imports/users/test-'.uniqid().'.csv';
    $disk->put($path, $csv);

    return DataImport::factory()->create(array_merge([
        'type' => DataImportType::Users,
        'path' => $path,
        'disk' => 's3',
        'status' => DataImportStatus::Queued,
    ], $overrides));
}

test('job processes a valid csv and marks completed with correct counters', function (): void {
    $csv = "email,name,role,password\n".
        "a@x.co,A,admin,\n".
        "b@x.co,B,operator,Temporal2026!\n";

    $import = makeUserImport($csv);

    (new ProcessDataImportJob($import))->handle(app(\App\Services\Imports\ImporterRegistry::class));

    $fresh = $import->fresh();
    expect($fresh->status)->toBe(DataImportStatus::Completed);
    expect($fresh->rows_total)->toBe(2);
    expect($fresh->rows_created)->toBe(2);
    expect($fresh->rows_errored)->toBe(0);
    expect($fresh->errors_path)->toBeNull();
});

test('invalid header marks failed without processing rows', function (): void {
    $csv = "wrong,header\nx,y\n";

    $import = makeUserImport($csv);

    (new ProcessDataImportJob($import))->handle(app(\App\Services\Imports\ImporterRegistry::class));

    $fresh = $import->fresh();
    expect($fresh->status)->toBe(DataImportStatus::Failed);
    expect($fresh->error_message)->toContain('Faltan columnas');
    expect($fresh->rows_total)->toBeNull();
});

test('errors.csv is uploaded to s3 when there are errored rows', function (): void {
    $csv = "email,name,role,password\n".
        "valid@x.co,Valid,admin,\n".
        "notanemail,Bad,admin,\n";

    $import = makeUserImport($csv);

    (new ProcessDataImportJob($import))->handle(app(\App\Services\Imports\ImporterRegistry::class));

    $fresh = $import->fresh();
    expect($fresh->status)->toBe(DataImportStatus::Completed);
    expect($fresh->rows_created)->toBe(1);
    expect($fresh->rows_errored)->toBe(1);
    expect($fresh->errors_path)->not->toBeNull();
    Storage::disk('s3')->assertExists($fresh->errors_path);

    $errorsContent = Storage::disk('s3')->get($fresh->errors_path);
    expect($errorsContent)->toContain('row_number');
    expect($errorsContent)->toContain('email');
});

test('failed() marks the import as failed with truncated message', function (): void {
    $import = makeUserImport("email,name,role,password\nx@y.co,X,admin,\n");

    (new ProcessDataImportJob($import))->failed(new \RuntimeException(str_repeat('A', 2000)));

    $fresh = $import->fresh();
    expect($fresh->status)->toBe(DataImportStatus::Failed);
    expect(strlen($fresh->error_message))->toBe(1000);
    expect($fresh->completed_at)->not->toBeNull();
});

test('dry_run does not persist users and reports counters', function (): void {
    $csv = "email,name,role,password\nfoo@bar.co,Foo,admin,\n";

    $import = makeUserImport($csv, ['dry_run' => true]);

    (new ProcessDataImportJob($import))->handle(app(\App\Services\Imports\ImporterRegistry::class));

    $fresh = $import->fresh();
    expect($fresh->status)->toBe(DataImportStatus::Completed);
    expect($fresh->rows_created)->toBe(1);
    expect(\App\Models\User::query()->where('email', 'foo@bar.co')->exists())->toBeFalse();
});

test('rows_total is populated before processing starts', function (): void {
    $csv = "email,name,role,password\n".str_repeat("x@y.co,X,admin,\n", 3);

    $import = makeUserImport($csv);

    (new ProcessDataImportJob($import))->handle(app(\App\Services\Imports\ImporterRegistry::class));

    $fresh = $import->fresh();
    expect($fresh->rows_total)->toBe(3);
});
