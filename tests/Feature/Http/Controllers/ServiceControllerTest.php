<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Contract;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    SpatieRole::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('index behaves as expected', function (): void {
    $services = Service::factory()->count(3)->create();

    $response = get(route('services.index'));

    $response->assertOk();
});

test('create behaves as expected', function (): void {
    $response = get(route('services.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\ServiceController::class,
        'store',
        \App\Http\Requests\ServiceStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $contract = Contract::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create();
    $invoice = Invoice::factory()->create();
    $service_date = Carbon::parse(fake()->date());
    $origin = fake()->word();
    $destination = fake()->word();
    $planned_start_time = fake()->time();
    $planned_duration = fake()->numberBetween(30, 480);
    $actual_start_time = fake()->time();
    $actual_end_time = fake()->time();
    $unit_value = fake()->randomFloat(2, 50000, 500000);
    $quantity = fake()->numberBetween(1, 5);
    $billing_group = fake()->word();
    $payment_method = fake()->randomElement(['cash', 'credit', 'transfer']);
    $service_status = fake()->randomElement(['open', 'closed']);

    $response = post(route('services.store'), [
        'contract_id' => $contract->id,
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'invoice_id' => $invoice->id,
        'service_date' => $service_date,
        'origin' => $origin,
        'destination' => $destination,
        'planned_start_time' => $planned_start_time,
        'planned_duration' => $planned_duration,
        'actual_start_time' => $actual_start_time,
        'actual_end_time' => $actual_end_time,
        'unit_value' => $unit_value,
        'quantity' => $quantity,
        'billing_group' => $billing_group,
        'payment_method' => $payment_method,
        'service_status' => $service_status,
    ]);

    $services = Service::query()
        ->where('contract_id', $contract->id)
        ->where('vehicle_id', $vehicle->id)
        ->where('driver_id', $driver->id)
        ->where('invoice_id', $invoice->id)
        ->where('service_date', $service_date)
        ->where('origin', $origin)
        ->where('destination', $destination)
        ->where('planned_start_time', $planned_start_time)
        ->where('planned_duration', $planned_duration)
        ->where('actual_start_time', $actual_start_time)
        ->where('actual_end_time', $actual_end_time)
        ->where('unit_value', $unit_value)
        ->where('quantity', $quantity)
        ->where('billing_group', $billing_group)
        ->where('payment_method', $payment_method)
        ->where('service_status', $service_status)
        ->get();
    expect($services)->toHaveCount(1);
    $service = $services->first();

    $response->assertRedirect(route('services.index'));
});

test('show behaves as expected', function (): void {
    $service = Service::factory()->create();

    $response = get(route('services.show', $service));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $service = Service::factory()->create();

    $response = get(route('services.edit', $service));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\ServiceController::class,
        'update',
        \App\Http\Requests\ServiceUpdateRequest::class
    );

test('update redirects', function (): void {
    $service = Service::factory()->create();
    $contract = Contract::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $driver = Driver::factory()->create();
    $invoice = Invoice::factory()->create();
    $service_date = Carbon::parse(fake()->date());
    $origin = fake()->word();
    $destination = fake()->word();
    $planned_start_time = fake()->time();
    $planned_duration = fake()->numberBetween(30, 480);
    $actual_start_time = fake()->time();
    $actual_end_time = fake()->time();
    $unit_value = fake()->randomFloat(2, 50000, 500000);
    $quantity = fake()->numberBetween(1, 5);
    $billing_group = fake()->word();
    $payment_method = fake()->randomElement(['cash', 'credit', 'transfer']);
    $service_status = fake()->randomElement(['open', 'closed']);

    $response = put(route('services.update', $service), [
        'contract_id' => $contract->id,
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'invoice_id' => $invoice->id,
        'service_date' => $service_date,
        'origin' => $origin,
        'destination' => $destination,
        'planned_start_time' => $planned_start_time,
        'planned_duration' => $planned_duration,
        'actual_start_time' => $actual_start_time,
        'actual_end_time' => $actual_end_time,
        'unit_value' => $unit_value,
        'quantity' => $quantity,
        'billing_group' => $billing_group,
        'payment_method' => $payment_method,
        'service_status' => $service_status,
    ]);

    $service->refresh();

    $response->assertRedirect(route('services.index'));

    expect($contract->id)->toEqual($service->contract_id);
    expect($vehicle->id)->toEqual($service->vehicle_id);
    expect($driver->id)->toEqual($service->driver_id);
    expect($invoice->id)->toEqual($service->invoice_id);
    expect($service_date)->toEqual($service->service_date);
    expect($origin)->toEqual($service->origin);
    expect($destination)->toEqual($service->destination);
    expect($planned_start_time)->toEqual($service->planned_start_time);
    expect($planned_duration)->toEqual($service->planned_duration);
    expect($actual_start_time)->toEqual($service->actual_start_time);
    expect($actual_end_time)->toEqual($service->actual_end_time);
    expect($unit_value)->toEqual($service->unit_value);
    expect($quantity)->toEqual($service->quantity);
    expect($billing_group)->toEqual($service->billing_group);
    expect($payment_method)->toEqual($service->payment_method);
    expect($service_status)->toEqual($service->service_status);
});

test('destroy deletes and redirects', function (): void {
    $service = Service::factory()->create();

    $response = delete(route('services.destroy', $service));

    $response->assertRedirect(route('services.index'));

    assertSoftDeleted($service);
});
