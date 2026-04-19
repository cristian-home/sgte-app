<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\LicenseCategory;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Models\Driver;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * REQ-004 / REQ-005 execution-time regression for
 * document-expiry-service-date-recheck. Each branch confirms that
 * confirmStart 422s with a Spanish message naming the expired doc when
 * paperwork has expired as-of service.service_date — even if the service
 * was created while everything was valid.
 */
beforeEach(function (): void {
    $driverUser = User::factory()->create();
    $driverUser->assignRole(Role::DRIVER->value);
    $this->driverUser = $driverUser;
});

function buildValidFleet(array $vehicleOverrides = [], array $driverOverrides = []): array
{
    $vehicle = Vehicle::factory()->create([
        'type' => VehicleType::Van,
        'soat_due_date' => '2030-12-31',
        'rtm_due_date' => '2030-12-31',
        'operation_card_due_date' => '2030-12-31',
        ...$vehicleOverrides,
    ]);

    $driver = Driver::factory()->create([
        'license_category' => LicenseCategory::C2,
        'license_due_date' => '2030-12-31',
        'has_social_security' => true,
        ...$driverOverrides,
    ]);

    return [$vehicle, $driver];
}

test('confirmStart succeeds when all documents are valid as-of service_date', function (): void {
    [$vehicle, $driver] = buildValidFleet();
    $driver->user_id = $this->driverUser->id;
    $driver->save();

    $service = Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date' => '2026-03-10',
        'actual_start_time' => null,
    ]);

    actingAs($this->driverUser);

    post(route('driver.confirm-start', $service))
        ->assertRedirect(route('driver.dashboard'));
});

test('confirmStart 422s when SOAT expired before service_date (REQ-004 branch)', function (): void {
    [$vehicle, $driver] = buildValidFleet(['soat_due_date' => '2026-03-05']);
    $driver->user_id = $this->driverUser->id;
    $driver->save();
    $service = Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date' => '2026-03-10',
        'actual_start_time' => null,
    ]);

    actingAs($this->driverUser);

    $response = post(route('driver.confirm-start', $service));
    $response->assertStatus(422);
    expect($response->exception?->getMessage() ?? '')->toContain('SOAT');
});

test('confirmStart 422s when RTM expired before service_date (REQ-004 branch)', function (): void {
    [$vehicle, $driver] = buildValidFleet(['rtm_due_date' => '2026-03-05']);
    $driver->user_id = $this->driverUser->id;
    $driver->save();
    $service = Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date' => '2026-03-10',
        'actual_start_time' => null,
    ]);

    actingAs($this->driverUser);

    $response = post(route('driver.confirm-start', $service));
    $response->assertStatus(422);
    expect($response->exception?->getMessage() ?? '')->toContain('RTM');
});

test('confirmStart 422s when operation card expired before service_date (REQ-004 branch)', function (): void {
    [$vehicle, $driver] = buildValidFleet(['operation_card_due_date' => '2026-03-05']);
    $driver->user_id = $this->driverUser->id;
    $driver->save();
    $service = Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date' => '2026-03-10',
        'actual_start_time' => null,
    ]);

    actingAs($this->driverUser);

    $response = post(route('driver.confirm-start', $service));
    $response->assertStatus(422);
    expect($response->exception?->getMessage() ?? '')->toContain('Tarjeta de Operación');
});

test('confirmStart 422s when driver license expired before service_date (REQ-005 branch)', function (): void {
    [$vehicle, $driver] = buildValidFleet([], ['license_due_date' => '2026-03-05']);
    $driver->user_id = $this->driverUser->id;
    $driver->save();
    $service = Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date' => '2026-03-10',
        'actual_start_time' => null,
    ]);

    actingAs($this->driverUser);

    $response = post(route('driver.confirm-start', $service));
    $response->assertStatus(422);
    expect($response->exception?->getMessage() ?? '')->toContain('licencia');
});

test('confirmStart 422s when driver license category is incompatible with vehicle type (REQ-005 branch)', function (): void {
    // Bus requires C2 or C3; use C1 to trigger incompatibility.
    [$vehicle, $driver] = buildValidFleet(
        ['type' => VehicleType::Bus],
        ['license_category' => LicenseCategory::C1],
    );
    $driver->user_id = $this->driverUser->id;
    $driver->save();
    $service = Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date' => '2026-03-10',
        'actual_start_time' => null,
    ]);

    actingAs($this->driverUser);

    $response = post(route('driver.confirm-start', $service));
    $response->assertStatus(422);
    expect($response->exception?->getMessage() ?? '')->toContain('categoría');
});

test('confirmStart 422s when driver social security is inactive (REQ-005 branch)', function (): void {
    [$vehicle, $driver] = buildValidFleet([], ['has_social_security' => false]);
    $driver->user_id = $this->driverUser->id;
    $driver->save();
    $service = Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date' => '2026-03-10',
        'actual_start_time' => null,
    ]);

    actingAs($this->driverUser);

    $response = post(route('driver.confirm-start', $service));
    $response->assertStatus(422);
    expect($response->exception?->getMessage() ?? '')->toContain('seguridad social');
});

test('confirmStart re-check is evaluated against service_date not today', function (): void {
    // License is valid TODAY but expires before a future service_date.
    // Checking against "today" would pass; checking against service_date
    // must fail. This proves the re-check keys off service.service_date.
    $validToday = now()->addDays(10)->toDateString();
    $futureServiceDate = now()->addDays(30)->toDateString();

    [$vehicle, $driver] = buildValidFleet([], ['license_due_date' => $validToday]);
    $driver->user_id = $this->driverUser->id;
    $driver->save();

    $service = Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date' => $futureServiceDate,
        'actual_start_time' => null,
    ]);

    actingAs($this->driverUser);

    $response = post(route('driver.confirm-start', $service));
    $response->assertStatus(422);
    expect($response->exception?->getMessage() ?? '')->toContain('licencia');
});
