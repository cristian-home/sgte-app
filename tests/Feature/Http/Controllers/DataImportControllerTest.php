<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\DataImportStatus;
use App\Enums\DataImportType;
use App\Enums\Role;
use App\Jobs\ProcessDataImportJob;
use App\Models\DataImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function (): void {
    Storage::fake('s3');
    Bus::fake();
});

function superAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::SUPER_ADMIN->value);

    return $user;
}

function userWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('super admin can view the imports index', function (): void {
    actingAs(superAdmin());

    DataImport::factory()->count(3)->create();

    $response = get(route('admin.imports.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('admin/imports/index')
            ->has('imports.data', 3)
            ->has('types', 4),
    );
});

test('super admin can render the create page', function (): void {
    actingAs(superAdmin());

    get(route('admin.imports.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/imports/create'));
});

test('store uploads to s3, creates row, and dispatches the job', function (): void {
    actingAs(superAdmin());

    $file = UploadedFile::fake()->createWithContent(
        'users.csv',
        "email,name,role,password,timezone\n",
    );

    $response = post(route('admin.imports.store'), [
        'type' => DataImportType::Users->value,
        'csv' => $file,
        'dry_run' => '1',
        'update_existing' => '0',
    ]);

    $response->assertRedirect();

    $import = DataImport::query()->latest('id')->first();
    expect($import)->not->toBeNull();
    expect($import->type)->toBe(DataImportType::Users);
    expect($import->status)->toBe(DataImportStatus::Queued);
    expect($import->dry_run)->toBeTrue();
    expect($import->update_existing)->toBeFalse();
    expect($import->path)->toStartWith('imports/users/');

    Storage::disk('s3')->assertExists($import->path);
    Bus::assertDispatched(ProcessDataImportJob::class);
});

test('store rejects files larger than 20 MB', function (): void {
    actingAs(superAdmin());

    // 25 MB file → fails the max:20480 KB rule
    $file = UploadedFile::fake()->create('huge.csv', 25 * 1024);

    post(route('admin.imports.store'), [
        'type' => DataImportType::Users->value,
        'csv' => $file,
    ])->assertSessionHasErrors(['csv']);

    Bus::assertNotDispatched(ProcessDataImportJob::class);
});

test('store rejects unsupported mime types', function (): void {
    actingAs(superAdmin());

    $file = UploadedFile::fake()->create('weird.bin', 100);

    post(route('admin.imports.store'), [
        'type' => DataImportType::Users->value,
        'csv' => $file,
    ])->assertSessionHasErrors(['csv']);
});

test('store with from_import_id clones metadata and dispatches a new job', function (): void {
    actingAs(superAdmin());

    $source = DataImport::factory()->dryRun()->completed()->create([
        'type' => DataImportType::Drivers,
        'original_filename' => 'drivers_lote.csv',
        'path' => 'imports/drivers/01HXKEEPME.csv',
        'update_existing' => true,
    ]);
    Storage::disk('s3')->put($source->path, 'placeholder');

    post(route('admin.imports.store'), [
        'from_import_id' => $source->id,
    ])->assertRedirect();

    $clone = DataImport::query()->whereKeyNot($source->id)->latest('id')->first();
    expect($clone)->not->toBeNull();
    expect($clone->id)->not->toBe($source->id);
    expect($clone->type)->toBe($source->type);
    expect($clone->path)->toBe($source->path);
    expect($clone->original_filename)->toBe($source->original_filename);
    expect($clone->dry_run)->toBeFalse();
    expect($clone->status)->toBe(DataImportStatus::Queued);

    Bus::assertDispatched(ProcessDataImportJob::class);
});

test('store without type or from_import_id fails validation', function (): void {
    actingAs(superAdmin());

    post(route('admin.imports.store'), [])
        ->assertSessionHasErrors(['type', 'csv']);
});

test('show renders the inertia page with the import', function (): void {
    actingAs(superAdmin());

    $import = DataImport::factory()->completed()->create();

    get(route('admin.imports.show', $import))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('admin/imports/show')
                ->has('import')
                ->where('import.id', $import->id),
        );
});

test('purge clears files and stamps files_purged_at', function (): void {
    actingAs(superAdmin());

    $import = DataImport::factory()->completed()->withErrors()->create();
    Storage::disk('s3')->put($import->path, 'src');
    Storage::disk('s3')->put($import->errors_path, 'errs');

    delete(route('admin.imports.purge', $import));

    Storage::disk('s3')->assertMissing($import->path);
    Storage::disk('s3')->assertMissing($import->errors_path);

    $fresh = $import->fresh();
    expect($fresh->path)->toBeNull();
    expect($fresh->errors_path)->toBeNull();
    expect($fresh->files_purged_at)->not->toBeNull();
});

test('downloadSource returns 410 when files were purged', function (): void {
    actingAs(superAdmin());

    $import = DataImport::factory()->purged()->create();

    get(route('admin.imports.download.source', $import))->assertStatus(410);
});

test('downloadErrors returns 404 when no errors_path', function (): void {
    actingAs(superAdmin());

    $import = DataImport::factory()->completed()->create([
        'errors_path' => null,
    ]);

    get(route('admin.imports.download.errors', $import))->assertNotFound();
});

dataset('non_super_admins', [
    'admin' => [Role::ADMIN->value],
    'operator' => [Role::OPERATOR->value],
    'driver' => [Role::DRIVER->value],
    'accounting' => [Role::ACCOUNTING->value],
]);

test('non super admin cannot access index', function (string $role): void {
    actingAs(userWithRole($role));
    get(route('admin.imports.index'))->assertForbidden();
})->with('non_super_admins');

test('non super admin cannot access create', function (string $role): void {
    actingAs(userWithRole($role));
    get(route('admin.imports.create'))->assertForbidden();
})->with('non_super_admins');

test('non super admin cannot store', function (string $role): void {
    actingAs(userWithRole($role));

    post(route('admin.imports.store'), [
        'type' => DataImportType::Users->value,
        'csv' => UploadedFile::fake()->createWithContent(
            'users.csv',
            "email,name,role,password,timezone\n",
        ),
    ])->assertForbidden();

    Bus::assertNotDispatched(ProcessDataImportJob::class);
})->with('non_super_admins');

test('guests are redirected to login on index', function (): void {
    get(route('admin.imports.index'))->assertRedirect('/login');
});
