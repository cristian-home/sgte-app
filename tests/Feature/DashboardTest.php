<?php

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Enums\VehicleStatus;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('admin sees dashboard with KPIs and document alerts', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    // Seed a small operational picture to assert the KPIs reflect reality.
    Vehicle::factory()->create(['status' => VehicleStatus::Active->value]);
    Vehicle::factory()->create(['status' => VehicleStatus::Maintenance->value]);
    Driver::factory()->create(['active' => true]);

    Service::factory()->create([
        'service_date' => Carbon::today(),
        'service_status' => ServiceStatus::Open->value,
    ]);
    Service::factory()->create([
        'service_date' => Carbon::today(),
        'service_status' => ServiceStatus::Closed->value,
    ]);

    Invoice::factory()->create(['payment_status' => PaymentStatus::Pending->value]);

    actingAs($user);

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('dashboard')
                ->has('kpis.vehicles', fn ($bucket) => $bucket->where('total', 2)->where('active', 1)->where('maintenance', 1)->etc())
                ->has('kpis.services_today', fn ($bucket) => $bucket->where('total', 2)->where('open', 1)->where('closed', 1)->etc())
                ->has('kpis.invoices_pending', fn ($bucket) => $bucket->where('total', 1)->etc())
                ->has('documentAlerts')
        );
});

test('dashboard surfaces expiring vehicle and driver documents', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    $vehicle = Vehicle::factory()->create([
        'plate' => 'ABC-123',
        'soat_due_date' => Carbon::today()->addDays(5),
        'rtm_due_date' => Carbon::today()->addYears(1),
        'operation_card_due_date' => Carbon::today()->addYears(1),
    ]);

    Driver::factory()->create([
        'first_name' => 'Juan',
        'first_lastname' => 'Pérez',
        'license_due_date' => Carbon::today()->subDays(2),
    ]);

    actingAs($user);

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('dashboard')
                ->has('documentAlerts', fn ($alerts) => $alerts
                    // Sorted by days_remaining asc — the expired license comes first.
                    ->where('0.kind', 'driver')
                    ->where('0.label', 'Licencia')
                    ->where('1.kind', 'vehicle')
                    ->where('1.label', 'SOAT')
                    ->where('1.subject', 'ABC-123')
                )
        );
});

test('drivers are redirected to the driver dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::DRIVER->value);

    actingAs($user);

    get(route('dashboard'))->assertRedirect(route('driver.dashboard'));
});

test('authenticated users without permission cannot visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertForbidden();
});
