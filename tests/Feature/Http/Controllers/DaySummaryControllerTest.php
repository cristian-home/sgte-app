<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\DayStatusEnum;
use App\Enums\ServiceStatus;
use App\Models\DayStatus;
use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\User;
use App\Models\Vehicle;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\get;

beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
    $this->user = $user;
});

test('index returns expected inertia props', function (): void {
    $date = '2026-03-10';
    $service = Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Open]);

    $response = get(route('day-summary.index', ['date' => $date]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('day-summary/index')
        ->has('services', 1)
        ->has('summary')
        ->has('date')
        ->has('canExecuteDay')
        ->where('date', $date)
    );
});

test('index filters services by date', function (): void {
    Service::factory()->create(['service_date' => '2026-03-10', 'service_status' => ServiceStatus::Open]);
    Service::factory()->create(['service_date' => '2026-03-11', 'service_status' => ServiceStatus::Open]);

    $response = get(route('day-summary.index', ['date' => '2026-03-10']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('services', 1)
    );
});

test('index defaults to today when no date param', function (): void {
    $response = get(route('day-summary.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('date', now()->format('Y-m-d'))
    );
});

test('index includes vehicle, driver, and contract relationships', function (): void {
    $date = '2026-03-10';
    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Open]);

    $response = get(route('day-summary.index', ['date' => $date]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('services.0.vehicle')
        ->has('services.0.driver')
        ->has('services.0.contract.third_party')
    );
});

test('index includes service_incidents_count', function (): void {
    $date = '2026-03-10';
    $service = Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Open]);
    ServiceIncident::factory()->count(3)->create(['service_id' => $service->id]);

    $response = get(route('day-summary.index', ['date' => $date]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('services.0.service_incidents_count', 3)
    );
});

test('index computes summary correctly', function (): void {
    $date = '2026-03-10';
    $ownVehicle = Vehicle::factory()->create(['is_third_party' => false]);
    $thirdPartyVehicle = Vehicle::factory()->create(['is_third_party' => true]);

    Service::factory()->count(2)->create(['service_date' => $date, 'service_status' => ServiceStatus::Closed, 'vehicle_id' => $ownVehicle->id]);
    Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Open, 'vehicle_id' => $ownVehicle->id]);
    $serviceWithIncident = Service::factory()->create(['service_date' => $date, 'service_status' => ServiceStatus::Open, 'vehicle_id' => $thirdPartyVehicle->id, 'driver_id' => null]);
    ServiceIncident::factory()->create(['service_id' => $serviceWithIncident->id]);

    $response = get(route('day-summary.index', ['date' => $date]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('summary.total', 4)
        ->where('summary.closed', 2)
        ->where('summary.open', 2)
        ->where('summary.with_incidents', 1)
        ->where('summary.third_party', 1)
    );
});

test('dayStatus is null when no DayStatus exists', function (): void {
    $response = get(route('day-summary.index', ['date' => '2026-03-10']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('dayStatus', null)
    );
});

test('dayStatus includes executor when executed', function (): void {
    $executor = User::factory()->create();
    DayStatus::factory()->create([
        'date' => '2026-03-10',
        'status' => DayStatusEnum::Executed,
        'executor_id' => $executor->id,
        'executed_at' => now(),
    ]);

    $response = get(route('day-summary.index', ['date' => '2026-03-10']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('dayStatus.executor.id', $executor->id)
        ->where('dayStatus.executor.name', $executor->name)
    );
});

test('canExecuteDay is true for user with EXECUTE_DAY permission', function (): void {
    $response = get(route('day-summary.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('canExecuteDay', true)
    );
});

test('canExecuteDay is false for user without EXECUTE_DAY permission', function (): void {
    SpatiePermission::firstOrCreate(['name' => 'day-summary.view', 'guard_name' => 'web']);
    $role = SpatieRole::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
    $role->givePermissionTo('day-summary.view');
    $user = User::factory()->create();
    $user->assignRole('viewer');
    $this->actingAs($user);

    $response = get(route('day-summary.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('canExecuteDay', false)
    );
});

test('user without VIEW_DAY_SUMMARY permission gets 403', function (): void {
    SpatiePermission::create(['name' => 'other.perm', 'guard_name' => 'web']);
    $role = SpatieRole::create(['name' => 'no_access', 'guard_name' => 'web']);
    $role->givePermissionTo('other.perm');
    $user = User::factory()->create();
    $user->assignRole('no_access');
    $this->actingAs($user);

    $response = get(route('day-summary.index'));

    $response->assertForbidden();
});
