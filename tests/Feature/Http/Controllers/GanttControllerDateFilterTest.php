<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\VehicleStatus;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Tests\Helpers\Tz;

use function Pest\Laravel\get;

/**
 * GET /gantt?date=Y-m-d returns the services whose service_date_local
 * matches the requested day in the operation timezone — regardless of
 * the host PHP TZ. Verifies the dropped wall-clock service_date column
 * isn't accidentally re-introduced via host-TZ midnight drift.
 */
beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

dataset('host_timezones', Tz::crossTimezones());

test('Gantt date filter returns services for the requested operation-TZ day', function (string $hostTz): void {
    Tz::with($hostTz, function () {
        $contract = Contract::factory()->create([
            'active' => true,
            'start_date' => '2026-04-01',
            'end_date' => '2026-12-31',
        ]);
        $vehicle = Vehicle::factory()->create([
            'is_third_party' => false,
            'status' => VehicleStatus::Active,
            'soat_due_date' => '2026-12-31',
            'rtm_due_date' => '2026-12-31',
            'operation_card_due_date' => '2026-12-31',
        ]);
        $driver = Driver::factory()->create([
            'license_due_date' => '2026-12-31',
            'has_social_security' => true,
        ]);

        // Same wall-clock 14:30 Bogotá fixture across all host TZs.
        Service::factory()->create([
            'contract_id' => $contract->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'service_date' => '2026-04-24',
            'planned_start_time' => '14:30',
            'timezone' => 'America/Bogota',
            'planned_duration' => 60,
        ]);

        get(route('gantt.index', ['date' => '2026-04-24']))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('gantt/index')
                    ->has('services', 1)
                    ->where('date', '2026-04-24'),
            );
    });
})->with('host_timezones');
