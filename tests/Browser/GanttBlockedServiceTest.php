<?php

use App\Enums\VehicleStatus;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->artisan('migrate:fresh', ['--seed' => true, '--no-interaction' => true]);
});

function ganttBlockedAuthenticateAsSuperAdmin(): User
{
    $role = SpatieRole::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::where('email', env('SUPER_ADMIN_USER'))->first();
    if (! $user) {
        $user = User::factory()->create([
            'email' => env('SUPER_ADMIN_USER'),
            'password' => bcrypt(env('SUPER_ADMIN_PASSWORD')),
        ]);
    }
    $user->assignRole($role);

    return $user;
}

test('gantt renders blocked state for service whose vehicle SOAT expired before service_date (REQ-004 regression)', function (): void {
    $user = ganttBlockedAuthenticateAsSuperAdmin();

    $serviceDate = '2026-03-10';

    $vehicle = Vehicle::factory()->create([
        'status' => VehicleStatus::Active,
        'soat_due_date' => '2026-03-05',
        'rtm_due_date' => '2030-12-31',
        'operation_card_due_date' => '2030-12-31',
    ]);
    $contract = Contract::factory()->create();
    $driver = Driver::factory()->create([
        'license_due_date' => '2030-12-31',
        'has_social_security' => true,
    ]);
    Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'contract_id' => $contract->id,
        'driver_id' => $driver->id,
        'service_date' => $serviceDate,
        'planned_start_time' => '08:00:00',
        'planned_duration' => 60,
    ]);

    $this->browse(function (Browser $browser) use ($user, $serviceDate): void {
        $browser->loginAs($user)
            ->visit("/gantt?date={$serviceDate}")
            ->waitForText('Planificador Gantt')
            ->waitFor('[data-service-blocked="true"]')
            ->assertPresent('[data-service-blocked="true"]')
            ->screenshot('gantt-blocked-service-bar');
    });
});

test('gantt does not mark a service as blocked when all documents are valid', function (): void {
    $user = ganttBlockedAuthenticateAsSuperAdmin();

    $serviceDate = '2026-03-10';

    $vehicle = Vehicle::factory()->create([
        'status' => VehicleStatus::Active,
        'soat_due_date' => '2030-12-31',
        'rtm_due_date' => '2030-12-31',
        'operation_card_due_date' => '2030-12-31',
    ]);
    $contract = Contract::factory()->create();
    $driver = Driver::factory()->create([
        'license_due_date' => '2030-12-31',
        'has_social_security' => true,
    ]);
    Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'contract_id' => $contract->id,
        'driver_id' => $driver->id,
        'service_date' => $serviceDate,
        'planned_start_time' => '08:00:00',
        'planned_duration' => 60,
    ]);

    $this->browse(function (Browser $browser) use ($user, $serviceDate): void {
        $browser->loginAs($user)
            ->visit("/gantt?date={$serviceDate}")
            ->waitForText('Planificador Gantt')
            ->waitFor('[data-service-blocked]')
            ->assertPresent('[data-service-blocked="false"]')
            ->assertMissing('[data-service-blocked="true"]')
            ->screenshot('gantt-healthy-service-bar');
    });
});
