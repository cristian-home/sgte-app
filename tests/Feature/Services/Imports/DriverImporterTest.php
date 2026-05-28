<?php

namespace Tests\Feature\Services\Imports;

use App\Enums\Role;
use App\Models\DataImport;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\Eps;
use App\Models\PensionFund;
use App\Models\SeveranceFund;
use App\Models\User;
use App\Services\Imports\DriverImporter;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

function runDriverImporter(string $csv, ?DataImport $import = null): array
{
    $import ??= DataImport::factory()->create();
    $tmp = tempnam(sys_get_temp_dir(), 'd_').'.csv';
    file_put_contents($tmp, $csv);
    $errorsTmp = tempnam(sys_get_temp_dir(), 'er_').'.csv';
    $writer = SimpleExcelWriter::create($errorsTmp);
    $writer->addHeader(['row_number', 'error_message', 'original_data']);
    $reader = SimpleExcelReader::create($tmp);
    $counters = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errored' => 0];

    app(DriverImporter::class)->processFile($reader, $import, $writer, function (array $delta) use (&$counters): void {
        foreach ($delta as $key => $value) {
            $counters[$key] += $value;
        }
    });

    $reader->close();
    $writer->close();
    $errors = file_get_contents($errorsTmp);
    @unlink($tmp);
    @unlink($errorsTmp);

    return ['counters' => $counters, 'errors' => $errors];
}

function headerDriver(): string
{
    return 'document_type_code,identification_number,first_name,second_name,first_lastname,second_lastname,address,phone,email,license_category,license_due_date,eps_code,pension_fund_code,severance_fund_code,has_social_security,user_email,municipality_code,timezone'."\n";
}

beforeEach(function (): void {
    DocumentType::firstOrCreate(['code' => 'CC'], ['name' => 'Cedula', 'is_natural_person' => true, 'is_legal_person' => false]);
    Eps::firstOrCreate(['code' => 'EPS001'], ['name' => 'Nueva EPS']);
    PensionFund::firstOrCreate(['code' => 'FP001'], ['name' => 'Porvenir']);
    SeveranceFund::firstOrCreate(['code' => 'FC001'], ['name' => 'Porvenir']);
    foreach (Role::cases() as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
    }
});

test('valid row creates a driver and resolves all FKs', function (): void {
    $csv = headerDriver().
        'CC,1023456789,Carlos,,Ramirez,,Cra 1,3001234567,carlos@x.co,C3,2027-12-31,EPS001,FP001,FC001,1,,,'."\n";

    $result = runDriverImporter($csv);

    expect($result['counters']['created'])->toBe(1);
    $driver = Driver::query()->where('identification_number', '1023456789')->first();
    expect($driver)->not->toBeNull();
    expect($driver->eps_id)->not->toBeNull();
});

test('invalid eps_code goes to errored', function (): void {
    $csv = headerDriver().
        'CC,1023111111,Ana,,Lopez,,Cra 1,3001234567,a@x.co,C3,2027-12-31,XYZ999,FP001,FC001,1,,,'."\n";

    $result = runDriverImporter($csv);

    expect($result['counters']['errored'])->toBe(1);
});

test('user_email without driver role goes to errored', function (): void {
    $user = User::factory()->create(['email' => 'admin-not-driver@x.co']);
    $user->assignRole(Role::ADMIN->value);

    $csv = headerDriver().
        'CC,1023222222,Beto,,Diaz,,Cra 1,3001234567,b@x.co,C2,2027-12-31,EPS001,FP001,FC001,1,admin-not-driver@x.co,,'."\n";

    $result = runDriverImporter($csv);

    expect($result['counters']['errored'])->toBe(1);
    expect($result['errors'])->toContain('rol conductor');
});

test('user_email with driver role binds user_id', function (): void {
    $user = User::factory()->create(['email' => 'driver-bind@x.co']);
    $user->assignRole(Role::DRIVER->value);

    $csv = headerDriver().
        'CC,1023333333,Cesar,,Estrada,,Cra 1,3001234567,c@x.co,C1,2027-12-31,EPS001,FP001,FC001,1,driver-bind@x.co,,'."\n";

    $result = runDriverImporter($csv);

    expect($result['counters']['created'])->toBe(1);
    $driver = Driver::query()->where('identification_number', '1023333333')->first();
    expect($driver->user_id)->toBe($user->id);
});

test('license_category outside enum goes to errored', function (): void {
    $csv = headerDriver().
        'CC,1023444444,Diana,,Gomez,,Cra 1,3001234567,d@x.co,B1,2027-12-31,EPS001,FP001,FC001,1,,,'."\n";

    $result = runDriverImporter($csv);

    expect($result['counters']['errored'])->toBe(1);
    expect($result['errors'])->toContain('Categoría de licencia');
});

test('expected headers and natural key', function (): void {
    $importer = app(DriverImporter::class);
    expect($importer->expectedHeaders())->toContain('eps_code', 'pension_fund_code', 'severance_fund_code', 'timezone');
    expect($importer->naturalKey())->toBe('identification_number');
});

test('blank timezone column falls back to the column default', function (): void {
    $csv = headerDriver().
        'CC,1023555555,Eva,,Mora,,Cra 1,3001234567,e@x.co,C3,2027-12-31,EPS001,FP001,FC001,1,,,'."\n";

    $result = runDriverImporter($csv);

    expect($result['counters']['created'])->toBe(1);
    $driver = Driver::query()->where('identification_number', '1023555555')->first();
    expect($driver->timezone)->toBe('America/Bogota');
});

test('explicit timezone column is persisted verbatim', function (): void {
    $csv = headerDriver().
        'CC,1023666666,Felipe,,Nieto,,Cra 1,3001234567,f@x.co,C3,2027-12-31,EPS001,FP001,FC001,1,,,America/New_York'."\n";

    $result = runDriverImporter($csv);

    expect($result['counters']['created'])->toBe(1);
    $driver = Driver::query()->where('identification_number', '1023666666')->first();
    expect($driver->timezone)->toBe('America/New_York');
});

test('invalid timezone goes to errored', function (): void {
    $csv = headerDriver().
        'CC,1023777777,Gina,,Ortiz,,Cra 1,3001234567,g@x.co,C3,2027-12-31,EPS001,FP001,FC001,1,,,Atlantis/Standard'."\n";

    $result = runDriverImporter($csv);

    expect($result['counters']['errored'])->toBe(1);
    expect($result['errors'])->toContain('Zona horaria');
});

test('chunk continues after a unique-violation row (resilient processing)', function (): void {
    // Reproduces the bug observed during Playwright smoke: a row that
    // throws a QueryException at insert time (e.g. drivers.user_id
    // unique violation because the user already has a Driver attached)
    // would abort the entire chunk's transaction, losing the other
    // valid rows. The fix is per-row atomicity.
    $user = User::factory()->create(['email' => 'driver-already-bound@x.co']);
    $user->assignRole(Role::DRIVER->value);

    // Pre-seed: this user already has a Driver attached → row 1 will
    // fail with `drivers_user_id_unique` violation at INSERT.
    Driver::factory()->create([
        'user_id' => $user->id,
        'identification_number' => '9999999999',
    ]);

    $csv = headerDriver().
        // Row 1: collides on user_id unique (will throw QueryException).
        'CC,1010101010,Conflict,,Row,,Cra 1,3001234567,c@x.co,C3,2027-12-31,EPS001,FP001,FC001,1,driver-already-bound@x.co,,'."\n".
        // Row 2: independent identification_number, no user link → must succeed.
        'CC,2020202020,After,,Conflict,,Cra 1,3001234567,a@x.co,C3,2027-12-31,EPS001,FP001,FC001,1,,,'."\n";

    $result = runDriverImporter($csv);

    expect($result['counters']['errored'])->toBe(1);
    expect($result['counters']['created'])->toBe(1);
    // Both SQLite ("UNIQUE constraint failed: drivers.user_id") and
    // Postgres ("duplicate key value violates unique constraint
    // \"drivers_user_id_unique\"") name the offending column.
    expect($result['errors'])->toContain('user_id');

    expect(Driver::query()->where('identification_number', '2020202020')->exists())->toBeTrue();
    expect(Driver::query()->where('identification_number', '1010101010')->exists())->toBeFalse();
});
