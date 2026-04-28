<?php

namespace Tests\Feature\Services\Imports;

use App\Models\DataImport;
use App\Models\DocumentType;
use App\Models\Municipality;
use App\Models\ThirdParty;
use App\Services\Imports\ThirdPartyImporter;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

function runThirdPartyImporter(string $csv, ?DataImport $import = null): array
{
    $import ??= DataImport::factory()->create();
    $tmp = tempnam(sys_get_temp_dir(), 'tp_').'.csv';
    file_put_contents($tmp, $csv);
    $errorsTmp = tempnam(sys_get_temp_dir(), 'er_').'.csv';
    $writer = SimpleExcelWriter::create($errorsTmp);
    $writer->addHeader(['row_number', 'error_message', 'original_data']);
    $reader = SimpleExcelReader::create($tmp);
    $counters = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errored' => 0];

    app(ThirdPartyImporter::class)->processFile($reader, $import, $writer, function (array $delta) use (&$counters): void {
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

function header3p(): string
{
    return 'document_type_code,identification_number,is_natural_person,first_name,second_name,first_lastname,second_lastname,company_name,trade_name,address,phone,email,is_customer,is_provider,municipality_code'."\n";
}

beforeEach(function (): void {
    DocumentType::firstOrCreate(['code' => 'NIT'], ['name' => 'NIT', 'is_natural_person' => false, 'is_legal_person' => true]);
    DocumentType::firstOrCreate(['code' => 'CC'], ['name' => 'Cedula', 'is_natural_person' => true, 'is_legal_person' => false]);
});

test('valid legal person row creates a third party', function (): void {
    $csv = header3p().
        'NIT,900111111,0,,,,,Empresa SA,SA,Cra 1,3001234567,a@b.co,1,0,'."\n";

    $result = runThirdPartyImporter($csv);

    expect($result['counters']['created'])->toBe(1);
    $tp = ThirdParty::query()->where('identification_number', '900111111')->first();
    expect($tp)->not->toBeNull();
    expect($tp->is_natural_person)->toBeFalse();
    expect($tp->is_customer)->toBeTrue();
    expect($tp->is_provider)->toBeFalse();
    expect($tp->company_name)->toBe('Empresa SA');
});

test('invalid municipality_code goes to errored', function (): void {
    $csv = header3p().
        'NIT,900222222,0,,,,,X,X,Cra 1,3001112222,a@b.co,1,0,99999'."\n";

    $result = runThirdPartyImporter($csv);

    expect($result['counters']['errored'])->toBe(1);
    expect($result['counters']['created'])->toBe(0);
});

test('invalid document_type_code goes to errored', function (): void {
    $csv = header3p().
        'XYZ,900333333,0,,,,,X,X,Cra 1,3001112222,a@b.co,1,0,'."\n";

    $result = runThirdPartyImporter($csv);

    expect($result['counters']['errored'])->toBe(1);
});

test('duplicate identification_number within file is reported once', function (): void {
    $csv = header3p().
        'NIT,900444444,0,,,,,X,X,Cra 1,3001112222,a@b.co,1,0,'."\n".
        'NIT,900444444,0,,,,,Y,Y,Cra 2,3009998888,a2@b.co,1,0,'."\n";

    $result = runThirdPartyImporter($csv);

    expect($result['counters']['created'])->toBe(1);
    expect($result['counters']['errored'])->toBe(1);
});

test('municipality_code resolves to municipality_id when valid', function (): void {
    $department = \App\Models\Department::firstOrCreate(['code' => '11'], ['name' => 'Bogotá DC']);
    Municipality::firstOrCreate(['code' => '11001'], ['name' => 'Bogotá', 'department_id' => $department->id, 'type' => 'CD']);

    $csv = header3p().
        'NIT,900555555,0,,,,,X,X,Cra 1,3001112222,a@b.co,1,0,11001'."\n";

    $result = runThirdPartyImporter($csv);

    expect($result['counters']['created'])->toBe(1);
    expect(ThirdParty::query()->where('identification_number', '900555555')->first()->municipality_id)
        ->not->toBeNull();
});
