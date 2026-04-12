<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Driver;
use App\Models\Service;
use App\Models\User;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
    $this->user = $user;
});

test('index renders driver dashboard', function (): void {
    $driver = Driver::factory()->create(['user_id' => $this->user->id]);

    $response = get(route('driver.dashboard'));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('driver/index')
            ->has('driver')
            ->has('services')
    );
});

test('index shows only today services for the driver', function (): void {
    $driver = Driver::factory()->create(['user_id' => $this->user->id]);
    $otherDriver = Driver::factory()->create();

    // Today's service
    Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
    ]);

    // Yesterday's service (should not appear)
    Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today()->subDay(),
    ]);

    // Another driver's service today (should not appear)
    Service::factory()->create([
        'driver_id' => $otherDriver->id,
        'service_date' => today(),
    ]);

    $response = get(route('driver.dashboard'));

    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('services', 1)
    );
});

test('index shows empty when user has no linked driver', function (): void {
    $response = get(route('driver.dashboard'));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('driver', null)
            ->has('services', 0)
    );
});

test('confirm start sets actual_start_time', function (): void {
    $driver = Driver::factory()->create(['user_id' => $this->user->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
        'actual_start_time' => null,
    ]);

    $response = post(route('driver.confirm-start', $service));

    $response->assertRedirect(route('driver.dashboard'));
    $service->refresh();
    expect($service->actual_start_time)->not->toBeNull();
});

test('confirm end sets actual_end_time', function (): void {
    $driver = Driver::factory()->create(['user_id' => $this->user->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
        'actual_start_time' => '08:00:00',
        'actual_end_time' => null,
    ]);

    $response = post(route('driver.confirm-end', $service));

    $response->assertRedirect(route('driver.dashboard'));
    $service->refresh();
    expect($service->actual_end_time)->not->toBeNull();
});

test('driver cannot confirm start for another driver service', function (): void {
    $driver = Driver::factory()->create(['user_id' => $this->user->id]);
    $otherDriver = Driver::factory()->create();
    $otherService = Service::factory()->create([
        'driver_id' => $otherDriver->id,
        'service_date' => today(),
    ]);

    $response = $this->post(route('driver.confirm-start', $otherService), [], [
        'X-Inertia' => 'true',
    ]);

    $response->assertStatus(403);
});

test('unauthorized user cannot access driver dashboard', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = get(route('driver.dashboard'));

    $response->assertForbidden();
});
