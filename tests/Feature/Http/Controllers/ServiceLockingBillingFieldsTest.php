<?php

use App\Enums\DayStatusEnum;
use App\Enums\ServiceStatus;
use App\Models\Contract;
use App\Models\DayStatus;
use App\Models\Driver;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

use function Pest\Laravel\put;

beforeEach(function (): void {
    $this->serviceDate = Carbon::today()->toDateString();

    $this->contract = Contract::factory()->create([
        'active' => true,
        'start_date' => Carbon::now()->subMonth(),
        'end_date' => Carbon::now()->addMonth(),
    ]);
    $this->vehicle = Vehicle::factory()->create(['is_third_party' => false]);
    $this->driver = Driver::factory()->create(['license_due_date' => Carbon::now()->addYear()]);

    $this->service = Service::factory()->create([
        'contract_id' => $this->contract->id,
        'vehicle_id' => $this->vehicle->id,
        'driver_id' => $this->driver->id,
        'service_date' => $this->serviceDate,
        'planned_start_time' => '08:00',
        'planned_duration' => 120,
        'service_status' => ServiceStatus::Closed,
        'unit_value' => 100000,
        'quantity' => 1,
        'billing_group' => 'Original',
        'payment_method' => 'credit',
    ]);

    // Mark day as executed
    DayStatus::whereDate('date', $this->serviceDate)->update([
        'status' => DayStatusEnum::Executed,
        'executor_id' => User::factory()->create()->id,
        'executed_at' => now(),
    ]);

    $user = User::factory()->create();
    $user->assignRole('accounting');
    $this->actingAs($user);
});

test('accounting user can update billing_group on executed day', function (): void {
    $response = put(route('services.update', $this->service), [
        'billing_group' => 'Grupo Nuevo',
        'unit_value' => $this->service->unit_value,
        'quantity' => $this->service->quantity,
        'payment_method' => $this->service->payment_method->value,
    ]);

    $response->assertRedirect(route('services.index'));

    $this->service->refresh();
    expect($this->service->billing_group)->toBe('Grupo Nuevo');
});

test('accounting user can update unit_value on executed day', function (): void {
    $response = put(route('services.update', $this->service), [
        'billing_group' => $this->service->billing_group,
        'unit_value' => 250000,
        'quantity' => $this->service->quantity,
        'payment_method' => $this->service->payment_method->value,
    ]);

    $response->assertRedirect(route('services.index'));

    $this->service->refresh();
    expect($this->service->unit_value)->toBe('250000.00');
});

test('accounting user can update quantity on executed day', function (): void {
    $response = put(route('services.update', $this->service), [
        'billing_group' => $this->service->billing_group,
        'unit_value' => $this->service->unit_value,
        'quantity' => 5,
        'payment_method' => $this->service->payment_method->value,
    ]);

    $response->assertRedirect(route('services.index'));

    $this->service->refresh();
    expect($this->service->quantity)->toBe(5);
});

test('accounting user can update payment_method on executed day', function (): void {
    $response = put(route('services.update', $this->service), [
        'billing_group' => $this->service->billing_group,
        'unit_value' => $this->service->unit_value,
        'quantity' => $this->service->quantity,
        'payment_method' => 'transfer',
    ]);

    $response->assertRedirect(route('services.index'));

    $this->service->refresh();
    expect($this->service->payment_method->value)->toBe('transfer');
});

test('accounting user cannot update vehicle_id on executed day', function (): void {
    $newVehicle = Vehicle::factory()->create();

    $response = put(route('services.update', $this->service), [
        'billing_group' => $this->service->billing_group,
        'unit_value' => $this->service->unit_value,
        'quantity' => $this->service->quantity,
        'payment_method' => $this->service->payment_method->value,
        'vehicle_id' => $newVehicle->id,
    ]);

    $response->assertRedirect(route('services.index'));

    $this->service->refresh();
    expect($this->service->vehicle_id)->toBe($this->vehicle->id);
});

test('accounting user cannot update driver_id on executed day', function (): void {
    $newDriver = Driver::factory()->create();

    $response = put(route('services.update', $this->service), [
        'billing_group' => $this->service->billing_group,
        'unit_value' => $this->service->unit_value,
        'quantity' => $this->service->quantity,
        'payment_method' => $this->service->payment_method->value,
        'driver_id' => $newDriver->id,
    ]);

    $response->assertRedirect(route('services.index'));

    $this->service->refresh();
    expect($this->service->driver_id)->toBe($this->driver->id);
});

test('accounting user cannot update service_date on executed day', function (): void {
    $newDate = Carbon::tomorrow()->toDateString();

    $response = put(route('services.update', $this->service), [
        'billing_group' => $this->service->billing_group,
        'unit_value' => $this->service->unit_value,
        'quantity' => $this->service->quantity,
        'payment_method' => $this->service->payment_method->value,
        'service_date' => $newDate,
    ]);

    $response->assertRedirect(route('services.index'));

    $this->service->refresh();
    expect($this->service->service_date)->toBe($this->serviceDate);
});
