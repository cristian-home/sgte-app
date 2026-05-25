<?php

namespace Tests\Feature\Services\Imports;

use App\Enums\BillingUnitType;
use App\Enums\ContractObject;
use App\Models\Contract;
use App\Models\DataImport;
use App\Models\DocumentType;
use App\Models\Municipality;
use App\Models\ThirdParty;
use App\Services\Imports\ThirdPartyImporter;
use App\Support\Tz;
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

test('importing a customer auto-creates a generic contract through 2026-12-31', function (): void {
    $csv = header3p().
        'NIT,900700001,0,,,,,Cliente Uno SA,CU,Cra 7,3001000001,c1@x.co,1,0,'."\n";

    runThirdPartyImporter($csv);

    $tp = ThirdParty::query()->where('identification_number', '900700001')->firstOrFail();
    $contract = Contract::query()->where('third_party_id', $tp->id)->firstOrFail();

    expect($contract->is_generic)->toBeTrue();
    expect($contract->active)->toBeTrue();
    expect($contract->contract_object)->toBe(ContractObject::Occasional);
    expect($contract->billing_unit_type)->toBe(BillingUnitType::Viaje);
    expect($contract->contract_number)->toMatch('/^GEN-\d{4}-\d{4}$/');
    expect($contract->end_date)->toBe('2026-12-31');
    expect($contract->timezone)->toBe(Tz::operation());
    expect($contract->route_description)->toContain('Cliente Uno SA');
});

test('provider-only third party does NOT receive an auto contract', function (): void {
    $csv = header3p().
        'NIT,900800001,0,,,,,Proveedor SA,PV,Cra 8,3002000001,p1@x.co,0,1,'."\n";

    runThirdPartyImporter($csv);

    $tp = ThirdParty::query()->where('identification_number', '900800001')->firstOrFail();
    expect(Contract::query()->where('third_party_id', $tp->id)->count())->toBe(0);
});

test('rerunning the import does not duplicate the auto contract', function (): void {
    $csv = header3p().
        'NIT,900900001,0,,,,,Cliente Bis,CB,Cra 9,3003000001,cb@x.co,1,0,'."\n";

    runThirdPartyImporter($csv);
    // Second run: by default update_existing=false, so the existing third
    // party is skipped — persistNew never fires again. Guards against
    // duplicate contracts via the natural skipped-path.
    runThirdPartyImporter($csv);

    $tp = ThirdParty::query()->where('identification_number', '900900001')->firstOrFail();
    expect(Contract::query()->where('third_party_id', $tp->id)->count())->toBe(1);
});

test('updating an existing third party does NOT create a contract', function (): void {
    // Seed an existing third party without any contract.
    $existing = ThirdParty::factory()->create([
        'identification_number' => '901100001',
        'is_customer' => true,
        'is_provider' => false,
    ]);
    expect(Contract::query()->where('third_party_id', $existing->id)->count())->toBe(0);

    // Same identifier + update_existing=true → applyUpdate path, NOT persistNew.
    $import = DataImport::factory()->create(['update_existing' => true]);
    $csv = header3p().
        'NIT,901100001,0,,,,,Cliente Actualizado,CA,Cra 1,3004000001,ca@x.co,1,0,'."\n";

    runThirdPartyImporter($csv, $import);

    expect(Contract::query()->where('third_party_id', $existing->id)->count())->toBe(0);
});

test('generic contract numbering increments across multiple customers in one import', function (): void {
    $csv = header3p().
        'NIT,901200001,0,,,,,Cliente A,A,Cra 1,3005000001,a@x.co,1,0,'."\n".
        'NIT,901200002,0,,,,,Cliente B,B,Cra 2,3005000002,b@x.co,1,0,'."\n".
        'NIT,901200003,0,,,,,Cliente C,C,Cra 3,3005000003,c@x.co,1,0,'."\n";

    runThirdPartyImporter($csv);

    $year = now()->year;
    $numbers = Contract::query()
        ->whereIn('third_party_id', ThirdParty::query()
            ->whereIn('identification_number', ['901200001', '901200002', '901200003'])
            ->pluck('id'))
        ->orderBy('contract_number')
        ->pluck('contract_number')
        ->all();

    expect($numbers)->toHaveCount(3);
    expect($numbers[0])->toBe(sprintf('GEN-%04d-%d', 1, $year));
    expect($numbers[1])->toBe(sprintf('GEN-%04d-%d', 2, $year));
    expect($numbers[2])->toBe(sprintf('GEN-%04d-%d', 3, $year));
});
