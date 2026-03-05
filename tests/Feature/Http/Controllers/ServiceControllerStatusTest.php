<?php

use App\Models\Contract;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\post;

beforeEach(function (): void {
    SpatieRole::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $this->contract = Contract::factory()->create([
        'active' => true,
        'start_date' => Carbon::now()->subMonth(),
        'end_date' => Carbon::now()->addMonth(),
    ]);
    $this->vehicle = Vehicle::factory()->create(['is_third_party' => false]);
    $this->driver = Driver::factory()->create(['license_due_date' => Carbon::now()->addYear()]);
});

test('store fails when service_status is closed and actual_start_time is missing', function (): void {
    $response = post(route('services.store'), [
        'contract_id' => $this->contract->id,
        'vehicle_id' => $this->vehicle->id,
        'driver_id' => $this->driver->id,
        'service_date' => Carbon::now()->toDateString(),
        'planned_start_time' => '08:00',
        'planned_duration' => 60,
        'unit_value' => 100000,
        'quantity' => 1,
        'payment_method' => 'credit',
        'service_status' => 'closed',
        'actual_end_time' => '10:00',
    ]);

    $response->assertSessionHasErrors(['actual_start_time']);
});

test('store fails when service_status is closed and actual_end_time is missing', function (): void {
    $response = post(route('services.store'), [
        'contract_id' => $this->contract->id,
        'vehicle_id' => $this->vehicle->id,
        'driver_id' => $this->driver->id,
        'service_date' => Carbon::now()->toDateString(),
        'planned_start_time' => '08:00',
        'planned_duration' => 60,
        'unit_value' => 100000,
        'quantity' => 1,
        'payment_method' => 'credit',
        'service_status' => 'closed',
        'actual_start_time' => '08:00',
    ]);

    $response->assertSessionHasErrors(['actual_end_time']);
});

test('store succeeds when service_status is closed and both actual times are provided', function (): void {
    $response = post(route('services.store'), [
        'contract_id' => $this->contract->id,
        'vehicle_id' => $this->vehicle->id,
        'driver_id' => $this->driver->id,
        'service_date' => Carbon::now()->toDateString(),
        'planned_start_time' => '08:00',
        'planned_duration' => 60,
        'unit_value' => 100000,
        'quantity' => 1,
        'payment_method' => 'credit',
        'service_status' => 'closed',
        'actual_start_time' => '08:00',
        'actual_end_time' => '09:30',
    ]);

    $response->assertRedirect(route('services.index'));
});

test('store succeeds when service_status is open and actual times are null', function (): void {
    $response = post(route('services.store'), [
        'contract_id' => $this->contract->id,
        'vehicle_id' => $this->vehicle->id,
        'driver_id' => $this->driver->id,
        'service_date' => Carbon::now()->toDateString(),
        'planned_start_time' => '08:00',
        'planned_duration' => 60,
        'unit_value' => 100000,
        'quantity' => 1,
        'payment_method' => 'credit',
        'service_status' => 'open',
    ]);

    $response->assertRedirect(route('services.index'));
});
