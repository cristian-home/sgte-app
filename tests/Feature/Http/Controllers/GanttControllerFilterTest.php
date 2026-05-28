<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\VehicleStatus;
use App\Models\Contract;
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

test('all active vehicles are returned', function (): void {
    Vehicle::factory()->count(3)->create(['status' => VehicleStatus::Active]);

    $response = get(route('gantt.index'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('vehicles', 3)
    );
});
