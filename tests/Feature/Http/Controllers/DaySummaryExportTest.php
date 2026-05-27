<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\ServiceStatus;
use App\Models\Driver;
use App\Models\Service;
use App\Models\ThirdParty;
use App\Models\User;
use App\Models\Vehicle;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\get;

beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('export returns CSV with correct content type', function (): void {
    Service::factory()->create(['service_date' => '2026-03-10', 'service_status' => ServiceStatus::Closed]);

    $response = get(route('day-summary.export', ['date' => '2026-03-10']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
});

test('export filename matches expected pattern', function (): void {
    $response = get(route('day-summary.export', ['date' => '2026-03-10']));

    $response->assertOk();
    $response->assertDownload('resumen-dia-2026-03-10.csv');
});

test('export CSV contains header row', function (): void {
    $response = get(route('day-summary.export', ['date' => '2026-03-10']));

    $response->assertOk();
    $content = $response->streamedContent();
    $bom = chr(0xEF).chr(0xBB).chr(0xBF);
    $content = str_replace($bom, '', $content);
    $lines = explode("\n", trim($content));
    expect($lines[0])->toContain('Placa');
    expect($lines[0])->toContain('Conductor/Proveedor');
    expect($lines[0])->toContain('Estado');
});

test('export contains one data row per service', function (): void {
    Service::factory()->count(3)->create(['service_date' => '2026-03-10', 'service_status' => ServiceStatus::Open]);

    $response = get(route('day-summary.export', ['date' => '2026-03-10']));

    $content = $response->streamedContent();
    $bom = chr(0xEF).chr(0xBB).chr(0xBF);
    $content = str_replace($bom, '', $content);
    $lines = array_filter(explode("\n", trim($content)));
    expect(count($lines))->toBe(4); // 1 header + 3 data rows
});

test('export displays provider name for third-party vehicles', function (): void {
    $provider = ThirdParty::factory()->create(['company_name' => 'Transportes XYZ', 'is_natural_person' => false]);
    $vehicle = Vehicle::factory()->create(['is_third_party' => true, 'third_party_id' => $provider->id]);
    Service::factory()->create(['service_date' => '2026-03-10', 'vehicle_id' => $vehicle->id, 'driver_id' => null, 'service_status' => ServiceStatus::Open]);

    $response = get(route('day-summary.export', ['date' => '2026-03-10']));

    $content = $response->streamedContent();
    expect($content)->toContain('Transportes XYZ');
});

test('export displays driver name for non-third-party vehicles', function (): void {
    $driver = Driver::factory()->create(['first_name' => 'Carlos', 'first_lastname' => 'Gomez']);
    $vehicle = Vehicle::factory()->create(['is_third_party' => false]);
    Service::factory()->create(['service_date' => '2026-03-10', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'service_status' => ServiceStatus::Open]);

    $response = get(route('day-summary.export', ['date' => '2026-03-10']));

    $content = $response->streamedContent();
    expect($content)->toContain('Carlos Gomez');
});

test('user without VIEW_DAY_SUMMARY permission gets 403', function (): void {
    SpatiePermission::create(['name' => 'other.perm', 'guard_name' => 'web']);
    $role = SpatieRole::create(['name' => 'no_access', 'guard_name' => 'web']);
    $role->givePermissionTo('other.perm');
    $user = User::factory()->create();
    $user->assignRole('no_access');
    $this->actingAs($user);

    $response = get(route('day-summary.export', ['date' => '2026-03-10']));

    $response->assertForbidden();
});

test('export CSV header includes Valor del servicio before Novedades and Total at the end', function (): void {
    $response = get(route('day-summary.export', ['date' => '2026-03-10']));

    $content = $response->streamedContent();
    $bom = chr(0xEF).chr(0xBB).chr(0xBF);
    $content = str_replace($bom, '', $content);
    $headerLine = explode("\n", trim($content))[0];
    $headers = str_getcsv($headerLine);

    $valorIdx = array_search('Valor del servicio', $headers, true);
    $novedadesIdx = array_search('Novedades', $headers, true);

    expect($valorIdx)->not->toBeFalse();
    expect($novedadesIdx)->not->toBeFalse();
    expect($valorIdx)->toBeLessThan($novedadesIdx);
    expect($headers[count($headers) - 1])->toBe('Total');
});

test('export emits Valor del servicio and Total per row with billing-affecting incidents', function (): void {
    $service = Service::factory()->create([
        'service_date' => '2026-03-10',
        'service_status' => ServiceStatus::Closed,
        'unit_value' => 50000,
        'quantity' => 2,
    ]);
    \App\Models\ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'affects_billing' => true,
        'additional_value' => 15000,
    ]);
    \App\Models\ServiceIncident::factory()->create([
        'service_id' => $service->id,
        'affects_billing' => false,
        'additional_value' => 999,
    ]);

    $response = get(route('day-summary.export', ['date' => '2026-03-10']));

    $content = $response->streamedContent();
    $bom = chr(0xEF).chr(0xBB).chr(0xBF);
    $content = str_replace($bom, '', $content);
    $lines = array_filter(explode("\n", trim($content)));
    $headers = str_getcsv($lines[0]);
    $row = str_getcsv($lines[1]);

    $valorIdx = array_search('Valor del servicio', $headers, true);
    $totalIdx = array_search('Total', $headers, true);

    expect($row[$valorIdx])->toBe('100000.00');
    expect($row[$totalIdx])->toBe('115000.00');
});

test('export without date param returns validation error', function (): void {
    $response = get(route('day-summary.export'));

    $response->assertSessionHasErrors(['date']);
});

test('export with invalid date returns validation error', function (): void {
    $response = get(route('day-summary.export', ['date' => 'not-a-date']));

    $response->assertSessionHasErrors(['date']);
});
