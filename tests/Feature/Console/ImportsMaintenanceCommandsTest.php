<?php

namespace Tests\Feature\Console;

use App\Enums\DataImportStatus;
use App\Models\DataImport;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    Storage::fake('s3');
});

test('purge-old-files deletes blobs and stamps files_purged_at for imports older than 90 days', function (): void {
    $old = DataImport::factory()->completed()->create([
        'completed_at' => now()->subDays(91),
        'path' => 'imports/users/old.csv',
    ]);
    $recent = DataImport::factory()->completed()->create([
        'completed_at' => now()->subDays(10),
        'path' => 'imports/users/recent.csv',
    ]);
    Storage::disk('s3')->put($old->path, 'old');
    Storage::disk('s3')->put($recent->path, 'recent');

    artisan('imports:purge-old-files')->assertExitCode(0);

    Storage::disk('s3')->assertMissing('imports/users/old.csv');
    Storage::disk('s3')->assertExists('imports/users/recent.csv');

    expect($old->fresh()->path)->toBeNull();
    expect($old->fresh()->files_purged_at)->not->toBeNull();
    expect($recent->fresh()->path)->toBe('imports/users/recent.csv');
    expect($recent->fresh()->files_purged_at)->toBeNull();
});

test('purge-old-files ignores already purged rows', function (): void {
    $purged = DataImport::factory()->purged()->create([
        'completed_at' => now()->subDays(120),
    ]);

    // No exception, no DB churn — just verify the row is left alone
    artisan('imports:purge-old-files')->assertExitCode(0);

    expect($purged->fresh()->path)->toBeNull();
});

test('reap-stuck marks processing imports started >35 min ago as failed', function (): void {
    $stuck = DataImport::factory()->processing()->create([
        'started_at' => now()->subMinutes(40),
    ]);
    $alive = DataImport::factory()->processing()->create([
        'started_at' => now()->subMinutes(10),
    ]);
    $completed = DataImport::factory()->completed()->create();

    artisan('imports:reap-stuck')->assertExitCode(0);

    expect($stuck->fresh()->status)->toBe(DataImportStatus::Failed);
    expect($stuck->fresh()->error_message)->toContain('interrumpido');
    expect($alive->fresh()->status)->toBe(DataImportStatus::Processing);
    expect($completed->fresh()->status)->toBe(DataImportStatus::Completed);
});

test('reap-stuck is a no-op when no imports are stuck', function (): void {
    DataImport::factory()->completed()->count(3)->create();

    artisan('imports:reap-stuck')->assertExitCode(0);

    expect(DataImport::query()->where('status', DataImportStatus::Failed)->count())->toBe(0);
});
