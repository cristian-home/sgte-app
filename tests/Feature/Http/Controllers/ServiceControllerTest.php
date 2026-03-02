<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Contract;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
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

test('index returns paginated inertia response', function (): void {
    Service::factory()->count(20)->create();

    $response = get(route('services.index'));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('services/index')
            ->has('services.data', 10)
            ->has('services.links')
            ->where('services.per_page', 10)
    );
});

test('index respects per_page parameter', function (): void {
    Service::factory()->count(10)->create();

    $response = get(route('services.index', ['per_page' => 5]));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('services.data', 5)
            ->where('services.per_page', 5)
    );
});

test('index caps per_page at 100', function (): void {
    Service::factory()->count(5)->create();

    $response = get(route('services.index', ['per_page' => 500]));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('services.per_page', 100)
    );
});

test('index filters by search term', function (): void {
    Service::factory()->create(['origin' => 'Bogota', 'destination' => 'Cali', 'billing_group' => 'Grupo A']);
    Service::factory()->create(['origin' => 'Medellin', 'destination' => 'Pereira', 'billing_group' => 'Grupo B']);

    $response = get(route('services.index', ['filter[search]' => 'Bogota']));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.origin', 'Bogota')
    );
});

test('index search is case insensitive', function (): void {
    Service::factory()->create(['origin' => 'Bogota', 'destination' => 'Cali', 'billing_group' => null]);

    $response = get(route('services.index', ['filter[search]' => 'bogota']));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('services.data', 1)
    );
});

test('index search matches across multiple columns', function (): void {
    Service::factory()->create(['origin' => 'Cali', 'destination' => 'Bogota']);
    Service::factory()->create(['origin' => 'Medellin', 'destination' => 'Cali']);

    $response = get(route('services.index', ['filter[search]' => 'Cali']));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('services.data', 2)
    );
});

test('index combines search with other filters', function (): void {
    Service::factory()->create(['origin' => 'Bogota', 'destination' => 'Cali', 'billing_group' => null, 'service_status' => 'open']);
    Service::factory()->create(['origin' => 'Bogota', 'destination' => 'Cali', 'billing_group' => null, 'service_status' => 'closed']);

    $response = get(route('services.index', [
        'filter[search]' => 'Bogota',
        'filter[service_status]' => 'open',
    ]));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('services.data', 1)
    );
});

test('index returns json when Accept header is application/json', function (): void {
    Service::factory()->count(3)->create();

    $response = getJson(route('services.index'));

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data',
            'current_page',
            'per_page',
            'total',
            'last_page',
            'links',
        ]);
});

test('index json respects filters and sorting', function (): void {
    Service::factory()->create(['origin' => 'Bogota', 'destination' => 'Cali', 'billing_group' => null, 'service_status' => 'open']);
    Service::factory()->create(['origin' => 'Medellin', 'destination' => 'Pereira', 'billing_group' => null, 'service_status' => 'closed']);

    $response = getJson(route('services.index', [
        'filter[search]' => 'Bogota',
        'sort' => '-service_date',
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.origin', 'Bogota');
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

test('index can filter services by service_status', function (): void {
    Service::factory()->create(['service_status' => 'open']);
    Service::factory()->create(['service_status' => 'closed']);

    $response = get(route('services.index', ['filter[service_status]' => 'open']));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.service_status', 'open')
    );
});

test('index can filter services by payment_method', function (): void {
    Service::factory()->create(['payment_method' => 'cash']);
    Service::factory()->create(['payment_method' => 'credit']);
    Service::factory()->create(['payment_method' => 'transfer']);

    $response = get(route('services.index', ['filter[payment_method]' => 'cash']));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.payment_method', 'cash')
    );
});

test('index search returns results for partial terms', function (): void {
    Service::factory()->create(['origin' => 'Barranquilla', 'destination' => 'Cali', 'billing_group' => null]);
    Service::factory()->create(['origin' => 'Bucaramanga', 'destination' => 'Medellin', 'billing_group' => null]);

    $response = get(route('services.index', ['filter[search]' => 'Barran']));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.origin', 'Barranquilla')
    );
});

test('index search matches related model fields via dot notation', function (): void {
    $driverCarlos = Driver::factory()->create(['first_name' => 'Carlos', 'first_lastname' => 'Gomez']);
    $driverMaria = Driver::factory()->create(['first_name' => 'Maria', 'first_lastname' => 'Lopez']);
    Service::factory()->create(['driver_id' => $driverCarlos->id, 'origin' => 'Bogota', 'destination' => 'Cali', 'billing_group' => null]);
    Service::factory()->create(['driver_id' => $driverMaria->id, 'origin' => 'Cali', 'destination' => 'Pereira', 'billing_group' => null]);

    $response = get(route('services.index', ['filter[search]' => 'Carlos']));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.origin', 'Bogota')
    );
});

test('index search matches composite related fields with full name', function (): void {
    $driverCarlos = Driver::factory()->create(['first_name' => 'Carlos', 'first_lastname' => 'Gomez']);
    $driverCarlosL = Driver::factory()->create(['first_name' => 'Carlos', 'first_lastname' => 'Lopez']);
    Service::factory()->create(['driver_id' => $driverCarlos->id, 'origin' => 'Bogota', 'destination' => 'Cali', 'billing_group' => null]);
    Service::factory()->create(['driver_id' => $driverCarlosL->id, 'origin' => 'Medellin', 'destination' => 'Pereira', 'billing_group' => null]);

    $response = get(route('services.index', ['filter[search]' => 'Carlos Gomez']));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('services.data', 1)
            ->where('services.data.0.origin', 'Bogota')
    );
});

test('index can filter services by multiple service_status values', function (): void {
    Service::factory()->create(['service_status' => 'open']);
    Service::factory()->create(['service_status' => 'closed']);

    $response = get(route('services.index', ['filter[service_status]' => 'open,closed']));

    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->has('services.data', 2)
    );
});
