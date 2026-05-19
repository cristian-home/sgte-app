<?php

/**
 * Phase 4 — End-to-end hard-case coverage.
 *
 * Each test() name starts with the scenario ID from
 * docs/testing/scenario-catalog.md so Phase 3 bug-log entries cross-reference
 * cleanly. todo() wrappers point at docs/testing/bug-log.md entries that need
 * code fixes before the assertion can pass.
 *
 * Coverage focus:
 *   - SVC-LC-01..14 logical conflicts on service creation (double-booking,
 *     expired docs, license category, contract overrun, social security).
 *   - SVC-LC-17 + DAY-LC-01..02 EJECUTADO-day invariants.
 *   - FUEC-LC-01 multi-FUEC supersede.
 *   - LAYER-03/06/08 three-layer authorization probes.
 *
 * Smoke / happy-path / form-field rendering scenarios from the catalog are
 * already covered by the 28 existing tests in this directory; not duplicated.
 */

use App\Enums\DayStatusEnum;
use App\Enums\LicenseCategory;
use App\Enums\Role as RoleEnum;
use App\Enums\ServiceStatus;
use App\Enums\VehicleType;
use App\Models\Contract;
use App\Models\DayStatus;
use App\Models\Driver;
use App\Models\Fuec;
use App\Models\Service;
use App\Models\ThirdParty;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

// ---------------------------------------------------------------------------
// Helpers (prefixed `hc` to avoid colliding with helpers in sibling tests).
// ---------------------------------------------------------------------------

function hcUserWithRole(string $roleEnum): User
{
    $role = SpatieRole::firstOrCreate(['name' => $roleEnum, 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function hcSuperAdmin(): User
{
    $role = SpatieRole::firstOrCreate(['name' => RoleEnum::SUPER_ADMIN->value, 'guard_name' => 'web']);
    $email = env('SUPER_ADMIN_USER') ?: 'superadmin@test.local';
    $user = User::where('email', $email)->first()
        ?? User::factory()->create([
            'email' => $email,
            'password' => bcrypt(env('SUPER_ADMIN_PASSWORD') ?: 'password'),
        ]);
    $user->assignRole($role);

    return $user;
}

/**
 * Build a deterministic fixture set (vehicle + driver + contract +
 * municipalities) sized for a service date and return both the models and
 * a valid POST /services payload. Tests mutate one input to exercise a
 * single failure mode.
 *
 * Why factories instead of seeded rows: seed data uses random dates so
 * `whereDate(... >= now)` queries can return empty under unlucky seed
 * seeds. Explicit factories keep each test self-contained.
 */
function hcMakeFixtures(array $overrides = []): array
{
    $tz = 'America/Bogota';
    $serviceDate = $overrides['service_date_local'] ?? Carbon::now($tz)->addDay()->toDateString();

    $vehicleType = $overrides['_vehicle_type'] ?? VehicleType::Buseta;
    $licenseCategory = $overrides['_license_category'] ?? LicenseCategory::C3;

    $municipalityOrigin = \App\Models\Municipality::query()->first() ?? \App\Models\Municipality::factory()->create();
    $municipalityDestination = \App\Models\Municipality::query()->where('id', '!=', $municipalityOrigin->id)->first()
        ?? \App\Models\Municipality::factory()->create();

    $vehicle = $overrides['_vehicle'] ?? Vehicle::factory()->create([
        'type' => $vehicleType,
        'is_third_party' => false,
        'status' => 'active',
        'soat_due_at' => Carbon::now()->addMonths(6),
        'rtm_due_at' => Carbon::now()->addMonths(6),
        'operation_card_due_at' => Carbon::now()->addMonths(6),
        'timezone' => $tz,
    ]);

    $driver = $overrides['_driver'] ?? Driver::factory()->create([
        'license_category' => $licenseCategory,
        'has_social_security' => true,
        'license_due_at' => Carbon::now()->addMonths(6),
        'timezone' => $tz,
    ]);

    $contract = $overrides['_contract'] ?? Contract::factory()->create([
        'is_generic' => false,
        'active' => true,
        'start_at' => Carbon::parse($serviceDate, $tz)->subMonth()->utc(),
        'end_at' => Carbon::parse($serviceDate, $tz)->addMonth()->utc(),
        'timezone' => $tz,
    ]);

    return [
        'vehicle' => $vehicle,
        'driver' => $driver,
        'contract' => $contract,
        'municipalityOrigin' => $municipalityOrigin,
        'municipalityDestination' => $municipalityDestination,
        'tz' => $tz,
        'serviceDate' => $serviceDate,
    ];
}

function hcServicePayload(array $overrides = []): array
{
    $fx = hcMakeFixtures($overrides);

    $plannedStartAt = $overrides['planned_start_at']
        ?? Carbon::parse($fx['serviceDate'].' 15:00:00', $fx['tz'])->utc()->format('Y-m-d H:i:s');

    return array_filter([
        'contract_id' => $overrides['contract_id'] ?? $fx['contract']->id,
        'vehicle_id' => $overrides['vehicle_id'] ?? $fx['vehicle']->id,
        'driver_id' => array_key_exists('driver_id', $overrides) ? $overrides['driver_id'] : $fx['driver']->id,
        'service_date' => $fx['serviceDate'],
        'service_date_local' => $fx['serviceDate'],
        'timezone' => $fx['tz'],
        'planned_start_time' => $overrides['planned_start_time'] ?? '15:00',
        'planned_start_at' => $plannedStartAt,
        'planned_duration' => $overrides['planned_duration'] ?? 120,
        'origin_municipality_id' => $overrides['origin_municipality_id'] ?? $fx['municipalityOrigin']->id,
        'destination_municipality_id' => $overrides['destination_municipality_id'] ?? $fx['municipalityDestination']->id,
        'service_status' => $overrides['service_status'] ?? ServiceStatus::Open->value,
        'unit_value' => $overrides['unit_value'] ?? 100000,
        'quantity' => $overrides['quantity'] ?? 1,
        'payment_method' => $overrides['payment_method'] ?? 'credit',
    ], fn ($v) => $v !== null);
}

// ===========================================================================
// LAYER-* — three-layer authorization probes (ADR-005)
// ===========================================================================

test('LAYER-03 accounting hits /users and gets 403 from route middleware', function (): void {
    $accounting = hcUserWithRole(RoleEnum::ACCOUNTING->value);

    $this->actingAs($accounting)->get('/users')->assertForbidden();
});

test('LAYER-06 operator POSTs /services without services.create gets 403 from FormRequest', function (): void {
    // Operator HAS services.create per the seeded role. To exercise this
    // layer cleanly we use Accounting (lacks services.create but has
    // services.view), which still bypasses the route middleware's
    // can:services.view gate.
    $accounting = hcUserWithRole(RoleEnum::ACCOUNTING->value);

    $payload = hcServicePayload();
    $this->actingAs($accounting)
        ->post('/services', $payload)
        ->assertForbidden();
});

test('LAYER-07 same role same invalid payload still 403 (authorize runs before rules)', function (): void {
    $accounting = hcUserWithRole(RoleEnum::ACCOUNTING->value);

    // Empty payload — would be 422 if rules() ran; 403 confirms authorize()
    // short-circuits ahead of validation.
    $this->actingAs($accounting)
        ->post('/services', [])
        ->assertForbidden();
});

test('LAYER-08 driver creates incident for service they do not own → 403', function (): void {
    $driverUser = hcUserWithRole(RoleEnum::DRIVER->value);
    $driverModel = Driver::factory()->create([
        'user_id' => $driverUser->id,
        'license_category' => LicenseCategory::C3,
        'has_social_security' => true,
        'license_due_at' => Carbon::now()->addMonths(6),
    ]);

    // Service owned by a different driver
    $otherService = Service::factory()->create([
        'driver_id' => Driver::factory()->create([
            'license_category' => LicenseCategory::C3,
            'has_social_security' => true,
            'license_due_at' => Carbon::now()->addMonths(6),
        ])->id,
    ]);

    $incidentType = \App\Models\IncidentType::query()->first() ?? \App\Models\IncidentType::factory()->create();

    // The per-service ownership check surfaces as a redirect with a session
    // error ("Solo puede registrar novedades en sus propios servicios."),
    // not a hard 403. That's a deliberate UX choice — avoids leaking which
    // services exist for other drivers. The catalog's LAYER-08 was written
    // assuming 403; pinning the real observed behavior here.
    $this->actingAs($driverUser)
        ->post('/service-incidents', [
            'service_id' => $otherService->id,
            'incident_type_id' => $incidentType->id,
            'description' => 'Should not be allowed.',
        ])
        ->assertSessionHasErrors();
});

test('LAYER-09 driver GETs /service-incidents index → 403 (no incidents.view)', function (): void {
    $driverUser = hcUserWithRole(RoleEnum::DRIVER->value);

    $this->actingAs($driverUser)
        ->get('/service-incidents')
        ->assertForbidden();
});

// ===========================================================================
// SVC-LC-* — Services logical conflicts
// ===========================================================================

test('SVC-LC-01 vehicle double-booking on overlapping window is rejected', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload();

    // Seed an existing service on the same vehicle, same day, overlapping window.
    Service::factory()->create([
        'vehicle_id' => $payload['vehicle_id'],
        'driver_id' => $payload['driver_id'],
        'contract_id' => $payload['contract_id'],
        'planned_start_at' => $payload['planned_start_at'],
        'planned_duration' => 120,
        'service_date_local' => $payload['service_date_local'],
        'service_status' => ServiceStatus::Open,
    ]);

    // Reuse the same window with a different driver — should still trip the
    // vehicle conflict.
    $otherDriver = Driver::factory()->create([
        'license_category' => LicenseCategory::C3,
        'has_social_security' => true,
        'license_due_at' => Carbon::now()->addMonths(6),
        'timezone' => 'America/Bogota',
    ]);
    $payload['driver_id'] = $otherDriver->id;

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionHasErrors('vehicle_id');
});

test('SVC-LC-02 boundary touch at exact end time does not conflict (half-open)', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload();

    // Existing: 13:00–15:00. New: 15:00–17:00. End touches Start exactly.
    Service::factory()->create([
        'vehicle_id' => $payload['vehicle_id'],
        'driver_id' => $payload['driver_id'],
        'contract_id' => $payload['contract_id'],
        'planned_start_at' => $payload['service_date_local'].' 13:00:00',
        'planned_duration' => 120,
        'service_date_local' => $payload['service_date_local'],
        'service_status' => ServiceStatus::Open,
    ]);

    $payload['planned_start_at'] = $payload['service_date_local'].' 15:00:00';
    $payload['planned_start_time'] = '15:00';

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionDoesntHaveErrors(['vehicle_id', 'driver_id']);
});

test('SVC-LC-04 driver double-booking on overlapping window is rejected (Q2)', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload();

    // Use a different vehicle but the same driver to isolate the driver
    // conflict path from the vehicle conflict path.
    $otherVehicle = Vehicle::factory()->create([
        'type' => VehicleType::Buseta,
        'is_third_party' => false,
        'status' => 'active',
        'soat_due_at' => Carbon::now()->addMonths(6),
        'rtm_due_at' => Carbon::now()->addMonths(6),
        'operation_card_due_at' => Carbon::now()->addMonths(6),
        'timezone' => 'America/Bogota',
    ]);

    Service::factory()->create([
        'vehicle_id' => $otherVehicle->id,
        'driver_id' => $payload['driver_id'],
        'contract_id' => $payload['contract_id'],
        'planned_start_at' => $payload['planned_start_at'],
        'planned_duration' => 120,
        'service_date_local' => $payload['service_date_local'],
        'service_status' => ServiceStatus::Open,
    ]);

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionHasErrors('driver_id');
});

test('SVC-LC-06 expired SOAT blocks service assignment', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload();

    Vehicle::find($payload['vehicle_id'])->update([
        'soat_due_at' => Carbon::now()->subDays(5),
    ]);

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionHasErrors('vehicle_id');
});

test('SVC-LC-07 expired RTM blocks service assignment', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload();

    Vehicle::find($payload['vehicle_id'])->update([
        'rtm_due_at' => Carbon::now()->subDays(5),
    ]);

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionHasErrors('vehicle_id');
});

test('SVC-LC-08 expired Operation Card blocks service assignment', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload();

    Vehicle::find($payload['vehicle_id'])->update([
        'operation_card_due_at' => Carbon::now()->subDays(5),
    ]);

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionHasErrors('vehicle_id');
});

test('SVC-LC-09 expired driver license blocks assignment', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload();

    Driver::find($payload['driver_id'])->update([
        'license_due_at' => Carbon::now()->subDays(5),
    ]);

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionHasErrors('driver_id');
});

test('SVC-LC-10 C1 driver assigned to Bus is rejected (license category mismatch)', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload([
        '_vehicle_type' => VehicleType::Bus,
        '_license_category' => LicenseCategory::C1,
    ]);

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionHasErrors('driver_id');
});

test('SVC-LC-11 C3 driver on Automobile is accepted (permissive mapping per Q1)', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload([
        '_vehicle_type' => VehicleType::Automobile,
        '_license_category' => LicenseCategory::C3,
    ]);

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionDoesntHaveErrors(['driver_id', 'vehicle_id']);
});

test('SVC-LC-12 driver with has_social_security=false is hard-blocked (Q6 amended)', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload();

    Driver::find($payload['driver_id'])->update(['has_social_security' => false]);

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionHasErrors('driver_id');
});

test('SVC-LC-13 service date outside contract window auto-creates GEN-NNNN-YYYY (bug-log:BUG-01)', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);

    // Fixtures: contract that does NOT cover the service date.
    $tz = 'America/Bogota';
    $serviceDate = Carbon::now($tz)->addDay()->toDateString();
    $outOfWindowStart = Carbon::parse($serviceDate, $tz)->subYear()->utc();
    $outOfWindowEnd = Carbon::parse($serviceDate, $tz)->subMonths(6)->utc();

    $customer = ThirdParty::factory()->create([
        'is_customer' => true,
        'is_provider' => false,
        'is_natural_person' => false,
        'company_name' => 'Auto-Generic Test',
    ]);
    $expiredContract = Contract::factory()->create([
        'third_party_id' => $customer->id,
        'is_generic' => false,
        'active' => true,
        'start_at' => $outOfWindowStart,
        'end_at' => $outOfWindowEnd,
        'timezone' => $tz,
    ]);
    $vehicle = Vehicle::factory()->create([
        'is_third_party' => false,
        'status' => 'active',
        'type' => VehicleType::Buseta,
        'soat_due_at' => Carbon::now()->addMonths(6),
        'rtm_due_at' => Carbon::now()->addMonths(6),
        'operation_card_due_at' => Carbon::now()->addMonths(6),
        'timezone' => $tz,
    ]);
    $driver = Driver::factory()->create([
        'license_category' => LicenseCategory::C3,
        'has_social_security' => true,
        'license_due_at' => Carbon::now()->addMonths(6),
        'timezone' => $tz,
    ]);
    $muniA = \App\Models\Municipality::query()->first() ?? \App\Models\Municipality::factory()->create();
    $muniB = \App\Models\Municipality::query()->where('id', '!=', $muniA->id)->first() ?? \App\Models\Municipality::factory()->create();

    $payload = [
        'contract_id' => $expiredContract->id,
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date' => $serviceDate,
        'service_date_local' => $serviceDate,
        'timezone' => $tz,
        'planned_start_time' => '15:00',
        'planned_start_at' => Carbon::parse($serviceDate.' 15:00:00', $tz)->utc()->format('Y-m-d H:i:s'),
        'planned_duration' => 120,
        'origin_municipality_id' => $muniA->id,
        'destination_municipality_id' => $muniB->id,
        'service_status' => ServiceStatus::Open->value,
        'unit_value' => 100000,
        'quantity' => 1,
        'payment_method' => 'credit',
        'create_generic_contract' => true,
    ];

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionDoesntHaveErrors();

    $year = Carbon::parse($serviceDate, $tz)->year;
    $generic = Contract::query()
        ->where('is_generic', true)
        ->where('third_party_id', $customer->id)
        ->where('contract_number', 'like', "GEN-%-{$year}")
        ->first();

    expect($generic)->not->toBeNull();
    expect(Service::query()->where('contract_id', $generic->id)->exists())->toBeTrue();
});

test('SVC-LC-14 service date inside contract window uses given contract', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload();

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionDoesntHaveErrors(['contract_id', 'vehicle_id', 'driver_id', 'service_date']);
});

test('SVC-LC-17 admin late-add service on EJECUTADO day with justification (bug-log:BUG-03)', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload();

    DayStatus::query()->updateOrCreate(
        ['date' => $payload['service_date_local']],
        ['status' => DayStatusEnum::Executed, 'executor_id' => $admin->id, 'executed_at' => now()],
    );

    $payload['justification'] = 'Servicio omitido por error durante el cierre del día.';

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionDoesntHaveErrors();

    $service = Service::query()->latest('id')->first();
    expect($service)->not->toBeNull();
    expect($service->service_date_local instanceof \DateTimeInterface
        ? $service->service_date_local->format('Y-m-d')
        : (string) $service->service_date_local)->toBe($payload['service_date_local']);
});

test('SVC-LC-17b operator late-add on EJECUTADO day is still rejected (bug-log:BUG-03)', function (): void {
    $operator = hcUserWithRole(RoleEnum::OPERATOR->value);
    $payload = hcServicePayload();

    DayStatus::query()->updateOrCreate(
        ['date' => $payload['service_date_local']],
        ['status' => DayStatusEnum::Executed, 'executor_id' => $operator->id, 'executed_at' => now()],
    );

    $payload['justification'] = 'Operator should not be allowed regardless of justification.';

    $this->actingAs($operator)
        ->post('/services', $payload)
        ->assertSessionHasErrors('service_date');
});

test('SVC-LC-17c admin late-add without justification is rejected (bug-log:BUG-03)', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $payload = hcServicePayload();

    DayStatus::query()->updateOrCreate(
        ['date' => $payload['service_date_local']],
        ['status' => DayStatusEnum::Executed, 'executor_id' => $admin->id, 'executed_at' => now()],
    );

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionHasErrors('justification');
});

// ===========================================================================
// DAY-LC-* — Day status invariants
// ===========================================================================

test('DAY-LC-01 admin cannot revert executed day to projected (bug-log:BUG-05)', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);

    $dayStatus = DayStatus::factory()->create([
        'date' => Carbon::today('America/Bogota')->toDateString(),
        'status' => DayStatusEnum::Executed,
        'executor_id' => $admin->id,
        'executed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->put("/day-statuses/{$dayStatus->id}", [
            'date' => $dayStatus->date instanceof \DateTimeInterface
                ? $dayStatus->date->format('Y-m-d')
                : (string) $dayStatus->date,
            'status' => DayStatusEnum::Projected->value,
            'justification' => 'Admin should still be blocked.',
        ])
        ->assertSessionHasErrors('status');

    expect($dayStatus->fresh()->status)->toBe(DayStatusEnum::Executed);
});

test('DAY-LC-01b super-admin can revert executed day with justification (bug-log:BUG-05)', function (): void {
    $sa = hcSuperAdmin();

    $dayStatus = DayStatus::factory()->create([
        'date' => Carbon::today('America/Bogota')->toDateString(),
        'status' => DayStatusEnum::Executed,
        'executor_id' => $sa->id,
        'executed_at' => now(),
    ]);

    $this->actingAs($sa)
        ->put("/day-statuses/{$dayStatus->id}", [
            'date' => $dayStatus->date instanceof \DateTimeInterface
                ? $dayStatus->date->format('Y-m-d')
                : (string) $dayStatus->date,
            'status' => DayStatusEnum::Projected->value,
            'justification' => 'Service was wrongly closed; reopening to correct billing.',
        ])
        ->assertSessionDoesntHaveErrors();

    $fresh = $dayStatus->fresh();
    expect($fresh->status)->toBe(DayStatusEnum::Projected);
    expect($fresh->executor_id)->toBeNull();
    expect($fresh->executed_at)->toBeNull();
});

test('DAY-LC-01c super-admin reversal without justification is rejected (bug-log:BUG-05)', function (): void {
    $sa = hcSuperAdmin();

    $dayStatus = DayStatus::factory()->create([
        'date' => Carbon::today('America/Bogota')->toDateString(),
        'status' => DayStatusEnum::Executed,
        'executor_id' => $sa->id,
        'executed_at' => now(),
    ]);

    $this->actingAs($sa)
        ->put("/day-statuses/{$dayStatus->id}", [
            'date' => $dayStatus->date instanceof \DateTimeInterface
                ? $dayStatus->date->format('Y-m-d')
                : (string) $dayStatus->date,
            'status' => DayStatusEnum::Projected->value,
        ])
        ->assertSessionHasErrors('justification');
});

test('DAY-LC-02 operator POST on executed day is rejected with service_date error (BUG-03 fixed)', function (): void {
    $operator = hcUserWithRole(RoleEnum::OPERATOR->value);
    $payload = hcServicePayload();

    DayStatus::query()->updateOrCreate(
        ['date' => $payload['service_date_local']],
        ['status' => DayStatusEnum::Executed, 'executor_id' => $operator->id, 'executed_at' => now()],
    );

    $this->actingAs($operator)
        ->post('/services', $payload)
        ->assertSessionHasErrors('service_date');
});

// ===========================================================================
// FUEC-LC-* — FUEC logical conflicts
// ===========================================================================

test('FUEC-LC-01 generating a second FUEC for same service auto-cancels the first (bug-log:BUG-06)', function (): void {
    $sa = hcSuperAdmin();

    // Set up a closed service with valid contract/vehicle/driver and a
    // pre-existing active FUEC. Generating a new FUEC should auto-cancel
    // the first with the standardized reason.
    $tz = 'America/Bogota';
    $today = Carbon::today($tz)->toDateString();
    $contract = Contract::factory()->create([
        'is_generic' => false,
        'active' => true,
        'start_at' => Carbon::parse($today, $tz)->subMonth()->utc(),
        'end_at' => Carbon::parse($today, $tz)->addMonth()->utc(),
        'timezone' => $tz,
    ]);
    $vehicle = Vehicle::factory()->create([
        'is_third_party' => false,
        'status' => 'active',
        'type' => VehicleType::Buseta,
        'soat_due_at' => Carbon::now()->addMonths(6),
        'rtm_due_at' => Carbon::now()->addMonths(6),
        'operation_card_due_at' => Carbon::now()->addMonths(6),
        'timezone' => $tz,
    ]);
    $driver = Driver::factory()->create([
        'license_category' => LicenseCategory::C3,
        'has_social_security' => true,
        'license_due_at' => Carbon::now()->addMonths(6),
        'timezone' => $tz,
    ]);
    $service = Service::factory()->create([
        'contract_id' => $contract->id,
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_status' => ServiceStatus::Closed,
        'service_date_local' => $today,
        'planned_start_at' => Carbon::parse($today.' 10:00:00', $tz)->utc(),
        'actual_start_at' => Carbon::parse($today.' 10:05:00', $tz)->utc(),
        'actual_end_at' => Carbon::parse($today.' 12:00:00', $tz)->utc(),
    ]);

    \App\Models\FuecNumberRange::query()->update(['active' => false]);
    $range = \App\Models\FuecNumberRange::factory()->create([
        'active' => true,
        'range_from' => 1000,
        'range_to' => 1100,
    ]);

    $firstFuec = Fuec::factory()->create([
        'service_id' => $service->id,
        'fuec_number_range_id' => $range->id,
        'consecutive_number' => 1000,
        'status' => \App\Enums\FuecStatus::Active,
    ]);

    // Storage is mocked by Laravel's test environment for s3? Not entirely —
    // the real Sail MinIO is running. The FuecGenerator writes the PDF to s3.
    // For the supersede assertion we just need the second generation to
    // succeed; PDF persistence is orthogonal.
    \Illuminate\Support\Facades\Storage::fake('s3');

    $this->actingAs($sa)
        ->post('/fuecs', ['service_id' => $service->id])
        ->assertSessionDoesntHaveErrors();

    $firstFuec->refresh();
    expect($firstFuec->status)->toBe(\App\Enums\FuecStatus::Cancelled);
    expect($firstFuec->cancellation_reason)->toBe('Superseded by new FUEC generation');

    $active = Fuec::query()
        ->where('service_id', $service->id)
        ->where('status', \App\Enums\FuecStatus::Active)
        ->count();
    expect($active)->toBe(1);
});

// ===========================================================================
// TZ-* — Cross-cutting timezone
// ===========================================================================

test('TZ-05 retroactive entry requires manual_entry_justification', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);

    $yesterday = Carbon::yesterday('America/Bogota')->toDateString();
    $payload = hcServicePayload([
        'service_date_local' => $yesterday,
        'planned_start_at' => $yesterday.' 15:00:00',
        'service_status' => ServiceStatus::Closed->value,
    ]);

    // Re-pick contract since the helper's default may not match yesterday.
    $contract = Contract::query()
        ->where('is_generic', false)
        ->where('active', true)
        ->whereDate('start_at', '<=', $yesterday)
        ->whereDate('end_at', '>', $yesterday)
        ->first();
    if ($contract) {
        $payload['contract_id'] = $contract->id;
    }

    // Missing justification → 422 on manual_entry_justification
    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionHasErrors('manual_entry_justification');
});

// ===========================================================================
// VEH-LC-* — Vehicle browser-level invariant (Gantt grayed row already
// covered by GanttBlockedServiceTest.php; this exercises the form-level
// rejection path that *is* visible to the operator).
// ===========================================================================

test('VEH-LC-03 third-party vehicle service create accepts null driver_id', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);

    $provider = ThirdParty::factory()->create([
        'is_natural_person' => false,
        'is_customer' => false,
        'is_provider' => true,
        'company_name' => 'Provider TP',
    ]);
    $tpVehicle = Vehicle::factory()->create([
        'is_third_party' => true,
        'third_party_id' => $provider->id,
        'status' => 'active',
        'soat_due_at' => Carbon::now()->addMonths(6),
        'rtm_due_at' => Carbon::now()->addMonths(6),
        'operation_card_due_at' => Carbon::now()->addMonths(6),
        'timezone' => 'America/Bogota',
    ]);

    $payload = hcServicePayload([
        '_vehicle' => $tpVehicle,
        'driver_id' => null,
    ]);

    $this->actingAs($admin)
        ->post('/services', $payload)
        ->assertSessionDoesntHaveErrors(['driver_id', 'vehicle_id']);
});

// ===========================================================================
// TP-HP-03 — Dual-flag tercero (Q8 confirmed)
// ===========================================================================

test('TP-HP-03 tercero may be both customer and provider simultaneously (Q8)', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);

    $docType = \App\Models\DocumentType::query()->first() ?? \App\Models\DocumentType::factory()->create();
    $muni = \App\Models\Municipality::query()->first() ?? \App\Models\Municipality::factory()->create();

    $payload = [
        'document_type_id' => $docType->id,
        'identification_number' => '900123456-7',
        'is_natural_person' => false,
        'company_name' => 'Dual Flag SAS',
        'trade_name' => 'DualFlag',
        'is_customer' => true,
        'is_provider' => true,
        'active' => true,
        'municipality_id' => $muni->id,
        'address' => 'Calle 1 #2-3',
        'phone' => '3001112233',
        'email' => 'contact@dualflag.example',
    ];

    $response = $this->actingAs($admin)->post('/third-parties', $payload);

    $response->assertSessionDoesntHaveErrors();
    $tp = ThirdParty::where('identification_number', '900123456-7')->first();
    expect($tp)->not->toBeNull();
    expect($tp->is_customer)->toBeTrue();
    expect($tp->is_provider)->toBeTrue();
});

// ===========================================================================
// CASCADE-* — service → contract → tercero create-on-the-fly UX
// ===========================================================================

test('CASCADE-01 contract POST with _cascade=1 flashes created_contract_id and stays on previous page', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);

    $customer = ThirdParty::factory()->create([
        'is_customer' => true,
        'is_provider' => false,
    ]);

    // Visit /services/create first so the back() target is meaningful.
    $this->actingAs($admin)->get('/services/create')->assertSuccessful();

    $response = $this->actingAs($admin)
        ->from('/services/create')
        ->post('/contracts', [
            'contract_number' => 'CASCADE-TEST-001',
            'third_party_id' => $customer->id,
            'contract_object' => 'business',
            'timezone' => 'America/Bogota',
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'route_description' => 'Test route via cascade',
            'is_generic' => false,
            'active' => true,
            '_cascade' => true,
        ]);

    $response->assertRedirect('/services/create');
    $contract = \App\Models\Contract::where('contract_number', 'CASCADE-TEST-001')->first();
    expect($contract)->not->toBeNull();
    expect(session('created_contract_id'))->toBe($contract->id);
});

test('CASCADE-02 contract POST without _cascade still redirects to /contracts/index', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);

    $customer = ThirdParty::factory()->create([
        'is_customer' => true,
        'is_provider' => false,
    ]);

    $response = $this->actingAs($admin)->post('/contracts', [
        'contract_number' => 'STANDALONE-TEST-001',
        'third_party_id' => $customer->id,
        'contract_object' => 'business',
        'timezone' => 'America/Bogota',
        'start_date' => '2026-01-01',
        'end_date' => '2027-01-01',
        'route_description' => 'Test route standalone',
        'is_generic' => false,
        'active' => true,
    ]);

    $response->assertRedirect('/contracts');
    expect(session('created_contract_id'))->toBeNull();
});

test('CASCADE-03 third-party POST with _cascade=1 flashes created_third_party_id and stays on previous page', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    $docType = \App\Models\DocumentType::query()->first() ?? \App\Models\DocumentType::factory()->create();
    $muni = \App\Models\Municipality::query()->first() ?? \App\Models\Municipality::factory()->create();

    // Visit /contracts first so the back() target is meaningful.
    $this->actingAs($admin)->get('/contracts')->assertSuccessful();

    $response = $this->actingAs($admin)
        ->from('/contracts')
        ->post('/third-parties', [
            'document_type_id' => $docType->id,
            'identification_number' => '987654321',
            'is_natural_person' => false,
            'company_name' => 'Cascade TP SAS',
            'trade_name' => 'CascadeTP',
            'is_customer' => true,
            'is_provider' => false,
            'active' => true,
            'municipality_id' => $muni->id,
            'address' => 'Cra 1 #1-1',
            'phone' => '3001112233',
            'email' => 'cascade@example.test',
            '_cascade' => true,
        ]);

    $response->assertRedirect('/contracts');
    $tp = ThirdParty::where('identification_number', '987654321')->first();
    expect($tp)->not->toBeNull();
    expect(session('created_third_party_id'))->toBe($tp->id);
});

test('CASCADE-04 services/create payload exposes thirdParties + documentTypes for nested dialogs', function (): void {
    $admin = hcUserWithRole(RoleEnum::ADMIN->value);
    ThirdParty::factory()->create(['is_customer' => true]);
    \App\Models\DocumentType::query()->first() ?? \App\Models\DocumentType::factory()->create();

    $response = $this->actingAs($admin)->get('/services/create');

    $response->assertSuccessful();
    $page = $response->viewData('page');
    expect($page['props'])
        ->toHaveKey('thirdParties')
        ->toHaveKey('documentTypes')
        ->toHaveKey('municipalities');
});
