<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\DayStatusEnum;
use App\Enums\Permission as PermissionEnum;
use App\Enums\VehicleStatus;
use App\Models\Contract;
use App\Models\DayStatus;
use App\Models\Driver;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\get;

beforeEach(function (): void {
    SpatieRole::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('index renders gantt page with expected props', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $contract = Contract::factory()->create();
    $driver = Driver::factory()->create();
    Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'contract_id' => $contract->id,
        'driver_id' => $driver->id,
        'service_date' => now()->toDateString(),
    ]);

    $response = get(route('gantt.index'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('gantt/index')
        ->has('vehicles')
        ->has('services')
        ->has('dayStatus')
        ->has('municipalities')
        ->has('date')
        ->has('canCreateServices')
    );
});

test('index defaults to today when no date param', function (): void {
    $response = get(route('gantt.index'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('date', now()->toDateString())
    );
});

test('index accepts date parameter', function (): void {
    $response = get(route('gantt.index', ['date' => '2026-03-10']));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('date', '2026-03-10')
    );
});

test('index returns services filtered to the requested date', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $contract = Contract::factory()->create();
    Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'contract_id' => $contract->id,
        'service_date' => '2026-03-10',
    ]);
    Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'contract_id' => $contract->id,
        'service_date' => '2026-03-11',
    ]);

    $response = get(route('gantt.index', ['date' => '2026-03-10']));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('services', 1)
    );
});

test('index only shows active vehicles', function (): void {
    Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    Vehicle::factory()->create(['status' => VehicleStatus::Retired]);
    Vehicle::factory()->create(['status' => VehicleStatus::Maintenance]);

    $response = get(route('gantt.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('vehicles', 1)
    );
});

test('vehicles include document expiry dates', function (): void {
    Vehicle::factory()->create([
        'status' => VehicleStatus::Active,
        'soat_due_date' => '2026-05-01',
        'rtm_due_date' => '2026-06-01',
        'operation_card_due_date' => '2026-07-01',
    ]);

    $response = get(route('gantt.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('vehicles.0.soat_due_date', fn ($value) => str_starts_with($value, '2026-05-01'))
        ->where('vehicles.0.rtm_due_date', fn ($value) => str_starts_with($value, '2026-06-01'))
        ->where('vehicles.0.operation_card_due_date', fn ($value) => str_starts_with($value, '2026-07-01'))
    );
});

test('vehicles include third party relationship when is_third_party', function (): void {
    Vehicle::factory()->create([
        'status' => VehicleStatus::Active,
        'is_third_party' => true,
    ]);

    $response = get(route('gantt.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('vehicles.0.third_party')
    );
});

test('services include driver and contract relationships', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $contract = Contract::factory()->create();
    $driver = Driver::factory()->create();
    Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'contract_id' => $contract->id,
        'driver_id' => $driver->id,
        'service_date' => now()->toDateString(),
    ]);

    $response = get(route('gantt.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('services.0.driver')
        ->has('services.0.contract')
        ->has('services.0.contract.third_party')
    );
});

test('dayStatus is null when no day status exists for the date', function (): void {
    $response = get(route('gantt.index', ['date' => '2026-03-10']));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('dayStatus', null)
    );
});

test('dayStatus includes executor when day is executed', function (): void {
    $executor = User::factory()->create();
    DayStatus::factory()->create([
        'date' => '2026-03-10',
        'status' => DayStatusEnum::Executed,
        'executor_id' => $executor->id,
        'executed_at' => now(),
    ]);

    $response = get(route('gantt.index', ['date' => '2026-03-10']));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('dayStatus')
        ->where('dayStatus.executor.id', $executor->id)
    );
});

test('canCreateServices is true for super admin', function (): void {
    $response = get(route('gantt.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('canCreateServices', true)
    );
});

test('canCreateServices is false for user without create permission', function (): void {
    $role = SpatieRole::create(['name' => 'viewer', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => PermissionEnum::VIEW_SERVICES->value, 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $user = User::factory()->create();
    $user->assignRole('viewer');
    $this->actingAs($user);

    $response = get(route('gantt.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('canCreateServices', false)
    );
});

test('user without view services permission gets 403', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = get(route('gantt.index'));

    $response->assertForbidden();
});

test('index filters vehicles by municipality', function (): void {
    $municipality = Municipality::factory()->create();
    $otherMunicipality = Municipality::factory()->create();
    Vehicle::factory()->create([
        'status' => VehicleStatus::Active,
        'municipality_id' => $municipality->id,
    ]);
    Vehicle::factory()->create([
        'status' => VehicleStatus::Active,
        'municipality_id' => $otherMunicipality->id,
    ]);

    $response = get(route('gantt.index', ['municipality_id' => $municipality->id]));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('vehicles', 1)
        ->where('vehicles.0.municipality_id', $municipality->id)
    );
});
