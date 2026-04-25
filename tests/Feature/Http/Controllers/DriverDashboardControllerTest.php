<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\IncidentType;
use App\Models\Service;
use App\Models\User;

use function Pest\Laravel\actingAs;
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
    expect($service->actual_start_at)->not->toBeNull();
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
    expect($service->actual_end_at)->not->toBeNull();
});

test('confirm start persists a VehicleLocation when coordinates are provided', function (): void {
    config()->set('sgte.gps_enabled', true);
    $driver = Driver::factory()->create(['user_id' => $this->user->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
        'actual_start_time' => null,
    ]);

    $response = post(route('driver.confirm-start', $service), [
        'latitude' => 6.2518,
        'longitude' => -75.5636,
        'is_manual' => false,
        'accuracy' => 12.5,
    ]);

    $response->assertRedirect(route('driver.dashboard'));
    expect(\App\Models\VehicleLocation::where('service_id', $service->id)->count())->toBe(1);
});

test('confirm start without coordinates does not persist a VehicleLocation', function (): void {
    config()->set('sgte.gps_enabled', true);
    $driver = Driver::factory()->create(['user_id' => $this->user->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
    ]);

    post(route('driver.confirm-start', $service))
        ->assertRedirect(route('driver.dashboard'));

    expect(\App\Models\VehicleLocation::where('service_id', $service->id)->count())->toBe(0);
});

test('confirm start still succeeds when GPS module is disabled — location write skipped', function (): void {
    config()->set('sgte.gps_enabled', false);
    $driver = Driver::factory()->create(['user_id' => $this->user->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
    ]);

    // Even with coordinates on the payload, the module-disabled path skips the write.
    post(route('driver.confirm-start', $service), [
        'latitude' => 6.2518,
        'longitude' => -75.5636,
        'is_manual' => false,
    ])->assertRedirect(route('driver.dashboard'));

    expect(\App\Models\VehicleLocation::where('service_id', $service->id)->count())->toBe(0);
});

test('confirm end persists a VehicleLocation when coordinates are provided', function (): void {
    config()->set('sgte.gps_enabled', true);
    $driver = Driver::factory()->create(['user_id' => $this->user->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
        'actual_start_time' => '08:00:00',
    ]);

    post(route('driver.confirm-end', $service), [
        'latitude' => 6.2518,
        'longitude' => -75.5636,
        'is_manual' => true,
    ])->assertRedirect(route('driver.dashboard'));

    $location = \App\Models\VehicleLocation::where('service_id', $service->id)->first();
    expect($location)->not->toBeNull()
        ->and($location->is_manual)->toBeTrue();
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

test('driver can open the incident create form with service_id pre-filled', function (): void {
    // Swap the super-admin harness for a real driver scenario
    $driverUser = User::factory()->create();
    $driverUser->assignRole(Role::DRIVER->value);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
    ]);
    actingAs($driverUser);

    get(route('service-incidents.create', ['service_id' => $service->id]))
        ->assertOk()
        ->assertInertia(
            fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('service-incidents/create')
                ->where('service.id', $service->id)
        );
});

test('driver can decline a service before confirmStart and the side effects fire', function (): void {
    \Illuminate\Support\Facades\Notification::fake();
    $driverUser = User::factory()->create();
    $driverUser->assignRole(Role::DRIVER->value);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
        'service_status' => 'open',
        'actual_start_time' => null,
    ]);
    $incidentType = IncidentType::firstOrCreate(
        ['code' => 'PREDECL'],
        ['name' => 'Rechazo previo al servicio', 'severity' => 'major', 'affects_billing_default' => false],
    );
    $opsUser = User::factory()->create();
    $opsUser->assignRole(Role::ADMIN->value);

    actingAs($driverUser);

    post(route('driver.decline', $service), [
        'reason_text' => 'Llanta pinchada antes de salir del terminal.',
    ])->assertRedirect(route('driver.dashboard'));

    $service->refresh();
    expect($service->driver_declined_at)->not->toBeNull()
        ->and($service->driver_decline_reason)->toBe('Llanta pinchada antes de salir del terminal.')
        ->and($service->service_status->value)->toBe('open');

    $incident = $service->serviceIncidents()->first();
    expect($incident)->not->toBeNull()
        ->and($incident->incident_type_id)->toBe($incidentType->id)
        ->and($incident->is_driver_report)->toBeTrue()
        ->and((bool) $incident->affects_billing)->toBeFalse();

    \Illuminate\Support\Facades\Notification::assertSentTo(
        $opsUser,
        \App\Notifications\DriverDeclinedServiceNotification::class,
    );
});

test('driver cannot decline another driver service', function (): void {
    $driverUser = User::factory()->create();
    $driverUser->assignRole(Role::DRIVER->value);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);

    $otherDriver = Driver::factory()->create();
    $otherService = Service::factory()->create([
        'driver_id' => $otherDriver->id,
        'service_date' => today(),
    ]);

    actingAs($driverUser);

    $this->post(route('driver.decline', $otherService), [
        'reason_text' => 'Intento de declinar un servicio que no es mío.',
    ])->assertStatus(403);
});

test('decline is rejected when actual_start_time is already set', function (): void {
    $driverUser = User::factory()->create();
    $driverUser->assignRole(Role::DRIVER->value);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
        'actual_start_time' => '08:00:00',
    ]);

    actingAs($driverUser);

    $this->post(route('driver.decline', $service), [
        'reason_text' => 'Intento de declinar un servicio ya iniciado.',
    ])->assertStatus(422);

    $service->refresh();
    expect($service->driver_declined_at)->toBeNull();
});

test('decline validates reason_text is required and min length', function (): void {
    $driverUser = User::factory()->create();
    $driverUser->assignRole(Role::DRIVER->value);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
        'actual_start_time' => null,
    ]);
    actingAs($driverUser);

    post(route('driver.decline', $service), [
        'reason_text' => '',
    ])->assertSessionHasErrors(['reason_text']);

    post(route('driver.decline', $service), [
        'reason_text' => 'corto',
    ])->assertSessionHasErrors(['reason_text']);
});

test('decline cannot be called twice on the same service', function (): void {
    $driverUser = User::factory()->create();
    $driverUser->assignRole(Role::DRIVER->value);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
        'actual_start_time' => null,
        'driver_declined_at' => now(),
    ]);
    actingAs($driverUser);

    $this->post(route('driver.decline', $service), [
        'reason_text' => 'Intento de declinar dos veces el mismo servicio.',
    ])->assertStatus(422);
});

test('driver creating an incident is flagged as driver report and redirected to driver dashboard', function (): void {
    $driverUser = User::factory()->create();
    $driverUser->assignRole(Role::DRIVER->value);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $service = Service::factory()->create([
        'driver_id' => $driver->id,
        'service_date' => today(),
    ]);
    $type = IncidentType::factory()->create();
    actingAs($driverUser);

    $response = post(route('service-incidents.store'), [
        'service_id' => $service->id,
        'incident_type_id' => $type->id,
        'description' => 'Averia en la ruta',
    ]);

    $response->assertRedirect(route('driver.dashboard'));
    expect($service->serviceIncidents()->count())->toBe(1);
    expect($service->serviceIncidents()->first()->is_driver_report)->toBeTrue();
});
