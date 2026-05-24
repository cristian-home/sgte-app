<?php

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Enums\VehicleStatus;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\ThirdParty;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
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

    Invoice::factory()->create(['payment_status' => PaymentStatus::Overdue->value]);

    actingAs($user);

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('dashboard')
                ->has('kpis.vehicles', fn ($bucket) => $bucket->where('total', 2)->where('active', 1)->where('maintenance', 1)->etc())
                ->has('kpis.services_today', fn ($bucket) => $bucket->where('total', 2)->where('open', 1)->where('closed', 1)->etc())
                ->has('kpis.incidents_today', fn ($bucket) => $bucket->where('total', 0)->where('affects_billing', 0)->where('from_driver', 0))
                ->has('trends.services', 14)
                ->has('trends.incidents', 14)
                ->has('todayServices')
                ->has('activeVehicles')
                ->has('overdueInvoices', 1)
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

test('document alerts include a navigation link', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    Vehicle::factory()->create([
        'plate' => 'EXP-100',
        'soat_due_date' => Carbon::today()->subDays(5),
        'rtm_due_date' => Carbon::today()->addYears(1),
        'operation_card_due_date' => Carbon::today()->addYears(1),
    ]);

    Vehicle::factory()->create([
        'plate' => 'WRN-200',
        'soat_due_date' => Carbon::today()->addDays(10),
        'rtm_due_date' => Carbon::today()->addYears(1),
        'operation_card_due_date' => Carbon::today()->addYears(1),
    ]);

    Driver::factory()->create([
        'first_name' => 'Ana',
        'first_lastname' => 'Gómez',
        'license_due_date' => Carbon::today()->subDays(1),
    ]);

    actingAs($user);

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('documentAlerts', fn ($alerts) => $alerts->each(
                    fn ($alert) => $alert
                        ->has('kind')
                        ->has('label')
                        ->has('subject')
                        ->has('due_date')
                        ->has('days_remaining')
                        ->has('link')
                ))
        );

    // Drill in on the specific links via a second request so the
    // assertion closure above stays focused on shape coverage.
    $payload = get(route('dashboard'))->viewData('page')['props']['documentAlerts'];

    $expiredVehicle = collect($payload)->firstWhere('subject', 'EXP-100');
    expect($expiredVehicle['link'])->toBe('/vehicles?filter[docs_status]=expired');

    $warnVehicle = collect($payload)->firstWhere('subject', 'WRN-200');
    expect($warnVehicle['link'])->toBe('/vehicles?filter[docs_status]=expiring_soon');

    $driverAlert = collect($payload)->firstWhere('kind', 'driver');
    expect($driverAlert['link'])->toBe('/drivers?filter[license_status]=expired');
});

test('driver alerts use license_status link symmetric with vehicles', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    Driver::factory()->create([
        'first_name' => 'Pedro',
        'first_lastname' => 'Ramírez',
        'license_due_date' => Carbon::today()->subDays(3),
    ]);

    Driver::factory()->create([
        'first_name' => 'Lucía',
        'first_lastname' => 'Castro',
        'license_due_date' => Carbon::today()->addDays(10),
    ]);

    actingAs($user);

    $payload = get(route('dashboard'))->viewData('page')['props']['documentAlerts'];

    $expiredDriver = collect($payload)->firstWhere(fn ($a) => $a['kind'] === 'driver' && str_contains($a['subject'], 'Pedro'));
    expect($expiredDriver['link'])->toBe('/drivers?filter[license_status]=expired');

    $warnDriver = collect($payload)->firstWhere(fn ($a) => $a['kind'] === 'driver' && str_contains($a['subject'], 'Lucía'));
    expect($warnDriver['link'])->toBe('/drivers?filter[license_status]=expiring_soon');
});

test('drivers are redirected to the driver dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::DRIVER->value);

    actingAs($user);

    get(route('dashboard'))->assertRedirect(route('driver.dashboard'));
});

test('dashboard surfaces expired contracts in document alerts', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    Contract::factory()->create([
        'contract_number' => 'CT-EXPIRED-001',
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonths(6),
        'end_date' => Carbon::today()->subDays(3),
    ]);

    actingAs($user);

    $alerts = get(route('dashboard'))->viewData('page')['props']['documentAlerts'];
    $contractAlert = collect($alerts)->firstWhere('subject', 'CT-EXPIRED-001');

    expect($contractAlert)->not->toBeNull();
    expect($contractAlert['kind'])->toBe('contract');
    expect($contractAlert['label'])->toBe('Contrato');
    expect($contractAlert['days_remaining'])->toBeLessThan(0);
    expect($contractAlert['link'])->toBe('/contracts?filter[contract_status]=vencido');
});

test('dashboard surfaces contracts expiring within 60 days in document alerts', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    Contract::factory()->create([
        'contract_number' => 'CT-WARN-001',
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addDays(45),
    ]);

    actingAs($user);

    $alerts = get(route('dashboard'))->viewData('page')['props']['documentAlerts'];
    $contractAlert = collect($alerts)->firstWhere('subject', 'CT-WARN-001');

    expect($contractAlert)->not->toBeNull();
    expect($contractAlert['kind'])->toBe('contract');
    expect($contractAlert['days_remaining'])->toBeGreaterThanOrEqual(0);
    expect($contractAlert['link'])->toBe('/contracts?filter[contract_status]=expiring_soon');
});

test('dashboard does not surface contracts more than 60 days out', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    Contract::factory()->create([
        'contract_number' => 'CT-FAR-001',
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addDays(120),
    ]);

    actingAs($user);

    $alerts = get(route('dashboard'))->viewData('page')['props']['documentAlerts'];
    $contractAlert = collect($alerts)->firstWhere('subject', 'CT-FAR-001');

    expect($contractAlert)->toBeNull();
});

test('dashboard does not surface inactive contracts in alerts', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    $customer = ThirdParty::factory()->create(['is_customer' => true]);
    Contract::factory()->create([
        'contract_number' => 'CT-OFF-001',
        'third_party_id' => $customer->id,
        'active' => false,
        'start_date' => Carbon::today()->subMonths(6),
        'end_date' => Carbon::today()->subDays(3),
    ]);

    actingAs($user);

    $alerts = get(route('dashboard'))->viewData('page')['props']['documentAlerts'];
    $contractAlert = collect($alerts)->firstWhere('subject', 'CT-OFF-001');

    expect($contractAlert)->toBeNull();
});

test('dashboard sorts contract alerts into the 10-row cap alongside vehicles and drivers', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    $customer = ThirdParty::factory()->create(['is_customer' => true]);

    // 4 contracts with varying days_remaining.
    Contract::factory()->create([
        'contract_number' => 'CT-EXP',
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonths(6),
        'end_date' => Carbon::today()->subDays(10),
    ]);
    Contract::factory()->create([
        'contract_number' => 'CT-10',
        'third_party_id' => $customer->id,
        'active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addDays(10),
    ]);

    // 2 expiring vehicles + 1 expiring driver.
    Vehicle::factory()->create([
        'plate' => 'V-1',
        'soat_due_date' => Carbon::today()->addDays(5),
        'rtm_due_date' => Carbon::today()->addYears(1),
        'operation_card_due_date' => Carbon::today()->addYears(1),
    ]);
    Driver::factory()->create([
        'first_name' => 'Ana',
        'first_lastname' => 'Gómez',
        'license_due_date' => Carbon::today()->subDays(2),
    ]);

    actingAs($user);

    $alerts = get(route('dashboard'))->viewData('page')['props']['documentAlerts'];
    expect(count($alerts))->toBeLessThanOrEqual(10);

    // Sorted ascending by days_remaining.
    $days = array_map(fn ($a) => $a['days_remaining'], $alerts);
    $sorted = $days;
    sort($sorted);
    expect($days)->toBe($sorted);

    // The kinds set must include all three.
    $kinds = array_unique(array_map(fn ($a) => $a['kind'], $alerts));
    sort($kinds);
    expect($kinds)->toBe(['contract', 'driver', 'vehicle']);
});

test('trends.services returns 14 entries ending today, zero-filling empty days', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    Service::factory()->count(2)->create([
        'service_date' => Carbon::today(),
    ]);
    Service::factory()->create([
        'service_date' => Carbon::today()->subDays(3),
    ]);

    actingAs($user);

    $payload = get(route('dashboard'))->viewData('page')['props']['trends']['services'];

    expect($payload)->toHaveCount(14);
    expect(end($payload)['date'])->toBe(Carbon::today()->toDateString());
    expect(end($payload)['count'])->toBe(2);

    $threeDaysAgo = collect($payload)
        ->firstWhere('date', Carbon::today()->subDays(3)->toDateString());
    expect($threeDaysAgo['count'])->toBe(1);

    // A day with no services is still present with count=0.
    $sevenDaysAgo = collect($payload)
        ->firstWhere('date', Carbon::today()->subDays(7)->toDateString());
    expect($sevenDaysAgo['count'])->toBe(0);
});

test('todayServices lists only services scheduled for today', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    Service::factory()->create(['service_date' => Carbon::today()]);
    Service::factory()->create(['service_date' => Carbon::today()]);
    Service::factory()->create(['service_date' => Carbon::today()->subDay()]);

    actingAs($user);

    $payload = get(route('dashboard'))->viewData('page')['props']['todayServices'];

    expect($payload)->toHaveCount(2);
    foreach ($payload as $row) {
        expect($row)->toHaveKeys([
            'id', 'vehicle_plate', 'planned_start_at',
            'planned_duration_min', 'timezone', 'status',
        ]);
    }
});

test('activeVehicles surfaces vehicles with an open service today + recent location', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    $vehicle = Vehicle::factory()->create(['plate' => 'ACT-001']);
    $service = Service::factory()->create([
        'vehicle_id' => $vehicle->id,
        'service_date' => Carbon::today(),
        'service_status' => ServiceStatus::Open->value,
    ]);
    VehicleLocation::factory()->create([
        'vehicle_id' => $vehicle->id,
        'service_id' => $service->id,
        'recorded_at' => now(),
    ]);

    actingAs($user);

    $payload = get(route('dashboard'))->viewData('page')['props']['activeVehicles'];

    expect($payload)->toHaveCount(1);
    expect($payload[0]['vehicle_plate'])->toBe('ACT-001');
    expect($payload[0]['location'])->toHaveKeys(['lat', 'lng', 'recorded_at']);
});

test('overdueInvoices returns top 5 oldest-first with customer name and value', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    $customer = ThirdParty::factory()->create(['company_name' => 'Cliente X SAS']);

    $oldest = Invoice::factory()->create([
        'third_party_id' => $customer->id,
        'payment_status' => PaymentStatus::Overdue->value,
        'issue_date' => Carbon::today()->subDays(45),
    ]);
    Invoice::factory()->create([
        'third_party_id' => $customer->id,
        'payment_status' => PaymentStatus::Overdue->value,
        'issue_date' => Carbon::today()->subDays(10),
    ]);
    Invoice::factory()->create([
        'third_party_id' => $customer->id,
        'payment_status' => PaymentStatus::Pending->value,
        'issue_date' => Carbon::today()->subDays(30),
    ]);

    actingAs($user);

    $payload = get(route('dashboard'))->viewData('page')['props']['overdueInvoices'];

    expect($payload)->toHaveCount(2);
    expect($payload[0]['id'])->toBe($oldest->id);
    expect($payload[0]['customer_name'])->toBe('Cliente X SAS');
    expect($payload[0]['days_since_issue'])->toBeGreaterThanOrEqual(45);
});

test('incidents_today counts only incidents reported today in operation TZ', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::ADMIN->value);

    ServiceIncident::factory()->create([
        'reported_at' => now(),
        'affects_billing' => true,
        'is_driver_report' => false,
    ]);
    ServiceIncident::factory()->create([
        'reported_at' => now(),
        'affects_billing' => false,
        'is_driver_report' => true,
    ]);
    ServiceIncident::factory()->create([
        'reported_at' => now()->subDays(2),
    ]);

    actingAs($user);

    $payload = get(route('dashboard'))->viewData('page')['props']['kpis']['incidents_today'];

    expect($payload['total'])->toBe(2);
    expect($payload['affects_billing'])->toBe(1);
    expect($payload['from_driver'])->toBe(1);
});

test('authenticated users without permission cannot visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertForbidden();
});
