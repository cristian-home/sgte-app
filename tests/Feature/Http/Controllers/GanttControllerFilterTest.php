<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\VehicleStatus;
use App\Models\Contract;
use App\Models\Municipality;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\get;

beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('municipality filter returns only vehicles with matching municipality_id', function (): void {
    $municipalityA = Municipality::factory()->create();
    $municipalityB = Municipality::factory()->create();

    Vehicle::factory()->create(['status' => VehicleStatus::Active, 'municipality_id' => $municipalityA->id]);
    Vehicle::factory()->create(['status' => VehicleStatus::Active, 'municipality_id' => $municipalityA->id]);
    Vehicle::factory()->create(['status' => VehicleStatus::Active, 'municipality_id' => $municipalityB->id]);

    $response = get(route('gantt.index', ['municipality_id' => $municipalityA->id]));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('vehicles', 2)
    );
});

test('services are returned for filtered vehicles only', function (): void {
    $municipalityA = Municipality::factory()->create();
    $municipalityB = Municipality::factory()->create();
    $contract = Contract::factory()->create();

    $vehicleA = Vehicle::factory()->create(['status' => VehicleStatus::Active, 'municipality_id' => $municipalityA->id]);
    $vehicleB = Vehicle::factory()->create(['status' => VehicleStatus::Active, 'municipality_id' => $municipalityB->id]);

    Service::factory()->create(['vehicle_id' => $vehicleA->id, 'contract_id' => $contract->id, 'service_date' => now()->toDateString()]);
    Service::factory()->create(['vehicle_id' => $vehicleB->id, 'contract_id' => $contract->id, 'service_date' => now()->toDateString()]);

    $response = get(route('gantt.index', ['municipality_id' => $municipalityA->id]));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('vehicles', 1)
        ->has('services', 1)
    );
});

test('invalid municipality_id returns validation error', function (): void {
    $response = get(route('gantt.index', ['municipality_id' => 99999]));

    $response->assertSessionHasErrors('municipality_id');
});

test('soft-deleted services are excluded', function (): void {
    $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Active]);
    $contract = Contract::factory()->create();

    $service = Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'contract_id' => $contract->id,
        'service_date' => now()->toDateString(),
    ]);
    $service->delete();

    Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'contract_id' => $contract->id,
        'service_date' => now()->toDateString(),
    ]);

    $response = get(route('gantt.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('services', 1)
    );
});

test('without municipality filter all active vehicles are returned', function (): void {
    Vehicle::factory()->count(3)->create(['status' => VehicleStatus::Active]);

    $response = get(route('gantt.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('vehicles', 3)
    );
});
