<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\IncidentSeverity;
use App\Models\Driver;
use App\Models\IncidentType;
use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\User;
use App\Notifications\BillingIncidentNotification;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
    $this->user = $user;
});

test('index behaves as expected', function (): void {
    ServiceIncident::factory()->count(3)->create();

    $response = get(route('service-incidents.index'));

    $response->assertOk();
});

test('index returns inertia page with service incidents', function (): void {
    ServiceIncident::factory()->count(3)->create();

    $response = get(route('service-incidents.index'));

    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('service-incidents/index')
            ->has('serviceIncidents.data', 3)
    );
});

test('create behaves as expected', function (): void {
    $response = get(route('service-incidents.create'));

    $response->assertOk();
});

test('create with service_id pre-fills service', function (): void {
    $service = Service::factory()->create();

    $response = get(route('service-incidents.create', ['service_id' => $service->id]));

    $response->assertOk();
    $response->assertInertia(
        fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('service-incidents/create')
            ->has('service')
            ->where('service.id', $service->id)
    );
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\ServiceIncidentController::class,
        'store',
        \App\Http\Requests\ServiceIncidentStoreRequest::class
    );

test('store saves with auto-set fields and redirects to service', function (): void {
    $service = Service::factory()->create();
    $incidentType = IncidentType::factory()->create();
    $description = fake()->text();

    $response = post(route('service-incidents.store'), [
        'service_id' => $service->id,
        'incident_type_id' => $incidentType->id,
        'description' => $description,
        'affects_billing' => true,
        'additional_value' => 50000,
    ]);

    $serviceIncident = ServiceIncident::query()
        ->where('service_id', $service->id)
        ->where('incident_type_id', $incidentType->id)
        ->where('description', $description)
        ->first();

    expect($serviceIncident)->not->toBeNull();
    expect($serviceIncident->registrar_id)->toBe($this->user->id);
    expect($serviceIncident->reported_at)->not->toBeNull();
    expect($serviceIncident->is_driver_report)->toBeFalse();
    expect($serviceIncident->affects_billing)->toBeTrue();
    expect((float) $serviceIncident->additional_value)->toBe(50000.0);

    $response->assertRedirect(route('services.show', $service));
});

test('store validates required fields', function (): void {
    $response = post(route('service-incidents.store'), []);

    $response->assertSessionHasErrors(['service_id', 'incident_type_id', 'description']);
});

test('show behaves as expected', function (): void {
    $serviceIncident = ServiceIncident::factory()->create();

    $response = get(route('service-incidents.show', $serviceIncident));

    $response->assertOk();
});

test('edit behaves as expected', function (): void {
    $serviceIncident = ServiceIncident::factory()->create();

    $response = get(route('service-incidents.edit', $serviceIncident));

    $response->assertOk();
});

test('update uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\ServiceIncidentController::class,
        'update',
        \App\Http\Requests\ServiceIncidentUpdateRequest::class
    );

test('update saves and redirects to service', function (): void {
    $serviceIncident = ServiceIncident::factory()->create();
    $incidentType = IncidentType::factory()->create();
    $description = fake()->text();

    $response = put(route('service-incidents.update', $serviceIncident), [
        'incident_type_id' => $incidentType->id,
        'description' => $description,
        'affects_billing' => false,
        'additional_value' => null,
    ]);

    $serviceIncident->refresh();

    $response->assertRedirect(route('services.show', $serviceIncident->service_id));

    expect($incidentType->id)->toEqual($serviceIncident->incident_type_id);
    expect($description)->toEqual($serviceIncident->description);
    expect($serviceIncident->affects_billing)->toBeFalse();
});

test('destroy deletes and redirects to service', function (): void {
    $serviceIncident = ServiceIncident::factory()->create();
    $serviceId = $serviceIncident->service_id;

    $response = delete(route('service-incidents.destroy', $serviceIncident));

    $response->assertRedirect(route('services.show', $serviceId));

    assertModelMissing($serviceIncident);
});

test('unauthorized user cannot access index', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = get(route('service-incidents.index'));

    $response->assertForbidden();
});

test('index returns paginated payload with eager loaded relations', function (): void {
    ServiceIncident::factory()->count(3)->create();

    $response = get(route('service-incidents.index'));
    $response->assertOk();

    $props = $response->viewData('page')['props'];

    expect($props['serviceIncidents'])->toHaveKey('data');
    expect($props['serviceIncidents'])->toHaveKey('per_page');
    expect($props['serviceIncidents'])->toHaveKey('current_page');
    expect($props['serviceIncidents']['data'])->toHaveCount(3);

    foreach ($props['serviceIncidents']['data'] as $row) {
        expect($row)->toHaveKey('service');
        expect($row)->toHaveKey('incident_type');
        expect($row)->toHaveKey('registrar');
    }
});

test('index passes incidentTypes payload for the faceted filter', function (): void {
    IncidentType::factory()->count(2)->create();

    $response = get(route('service-incidents.index'));

    $options = $response->viewData('page')['props']['incidentTypes'];
    expect(count($options))->toBeGreaterThanOrEqual(2);
    foreach ($options as $opt) {
        expect($opt)->toHaveKey('severity');
        expect($opt)->toHaveKey('affects_billing_default');
    }
});

test('index filters by severity via the new callback filter', function (): void {
    ServiceIncident::query()->delete();

    $infoType = IncidentType::factory()->create(['severity' => IncidentSeverity::Informational]);
    $minorType = IncidentType::factory()->create(['severity' => IncidentSeverity::Minor]);
    $majorType = IncidentType::factory()->create(['severity' => IncidentSeverity::Major]);

    ServiceIncident::factory()->create(['incident_type_id' => $infoType->id]);
    ServiceIncident::factory()->create(['incident_type_id' => $minorType->id]);
    $majorIncident = ServiceIncident::factory()->create(['incident_type_id' => $majorType->id]);

    $response = get(route('service-incidents.index', ['filter' => ['severity' => 'major']]));
    $response->assertOk();

    $rows = $response->viewData('page')['props']['serviceIncidents']['data'];
    expect($rows)->toHaveCount(1);
    expect($rows[0]['id'])->toBe($majorIncident->id);
});

test('index filters by affects_billing and is_driver_report', function (): void {
    ServiceIncident::query()->delete();

    $billingYes = ServiceIncident::factory()->create(['affects_billing' => true, 'is_driver_report' => true]);
    ServiceIncident::factory()->create(['affects_billing' => false, 'is_driver_report' => false]);

    $byBilling = get(route('service-incidents.index', ['filter' => ['affects_billing' => 1]]));
    expect($byBilling->viewData('page')['props']['serviceIncidents']['data'])->toHaveCount(1);
    expect($byBilling->viewData('page')['props']['serviceIncidents']['data'][0]['id'])->toBe($billingYes->id);

    $byDriver = get(route('service-incidents.index', ['filter' => ['is_driver_report' => 1]]));
    expect($byDriver->viewData('page')['props']['serviceIncidents']['data'])->toHaveCount(1);
    expect($byDriver->viewData('page')['props']['serviceIncidents']['data'][0]['id'])->toBe($billingYes->id);
});

test('index defaults to -reported_at sort', function (): void {
    ServiceIncident::query()->delete();

    $old = ServiceIncident::factory()->create(['reported_at' => now()->subDays(5)]);
    $latest = ServiceIncident::factory()->create(['reported_at' => now()]);
    $middle = ServiceIncident::factory()->create(['reported_at' => now()->subDays(2)]);

    $response = get(route('service-incidents.index'));
    $rows = $response->viewData('page')['props']['serviceIncidents']['data'];

    expect($rows[0]['id'])->toBe($latest->id);
    expect($rows[2]['id'])->toBe($old->id);
});

test('show eager loads service vehicle, contract, thirdParty, incidentType and registrar', function (): void {
    $serviceIncident = ServiceIncident::factory()->create();

    $response = get(route('service-incidents.show', $serviceIncident));
    $response->assertOk();

    $payload = $response->viewData('page')['props']['serviceIncident'];
    expect($payload)->toHaveKey('service');
    expect($payload['service'])->toHaveKey('vehicle');
    expect($payload['service'])->toHaveKey('contract');
    expect($payload['service']['contract'])->toHaveKey('third_party');
    expect($payload)->toHaveKey('incident_type');
    expect($payload)->toHaveKey('registrar');
});

test('create without service_id passes services option list', function (): void {
    Service::factory()->count(2)->create(['service_date' => now()->subDays(3)]);

    $response = get(route('service-incidents.create'));
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props)->toHaveKey('services');
    expect(count($props['services']))->toBeGreaterThanOrEqual(2);
});

test('create with service_id omits the services option list', function (): void {
    $service = Service::factory()->create();

    $response = get(route('service-incidents.create', ['service_id' => $service->id]));
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['services'])->toBeNull();
    expect($props['service']['id'])->toBe($service->id);
});

test('store rejects additional_value when negative', function (): void {
    $service = Service::factory()->create();
    $incidentType = IncidentType::factory()->create();

    $response = post(route('service-incidents.store'), [
        'service_id' => $service->id,
        'incident_type_id' => $incidentType->id,
        'description' => 'Negativo',
        'affects_billing' => false,
        'additional_value' => -50,
    ]);

    $response->assertSessionHasErrors(['additional_value']);
});

test('store accepts additional_value of zero', function (): void {
    $service = Service::factory()->create();
    $incidentType = IncidentType::factory()->create();

    $response = post(route('service-incidents.store'), [
        'service_id' => $service->id,
        'incident_type_id' => $incidentType->id,
        'description' => 'Zero value',
        'affects_billing' => true,
        'additional_value' => 0,
    ]);

    $response->assertRedirect();
});

test('store allows a driver to submit an incident for their own service', function (): void {
    $driverUser = User::factory()->create();
    $driverUser->assignRole('driver');
    $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
    $service = Service::factory()->create(['driver_id' => $driver->id]);
    $incidentType = IncidentType::factory()->create();

    $this->actingAs($driverUser);

    $response = post(route('service-incidents.store'), [
        'service_id' => $service->id,
        'incident_type_id' => $incidentType->id,
        'description' => 'Own service report',
        'affects_billing' => false,
    ]);

    $response->assertRedirect(route('driver.dashboard'));

    $incident = ServiceIncident::query()->where('service_id', $service->id)->first();
    expect($incident)->not->toBeNull();
    expect($incident->is_driver_report)->toBeTrue();
    expect($incident->registrar_id)->toBe($driverUser->id);
});

test('store rejects a driver submission for another drivers service', function (): void {
    $driverAUser = User::factory()->create();
    $driverAUser->assignRole('driver');
    $driverA = Driver::factory()->create(['user_id' => $driverAUser->id]);

    $driverBUser = User::factory()->create();
    $driverBUser->assignRole('driver');
    $driverB = Driver::factory()->create(['user_id' => $driverBUser->id]);

    $otherService = Service::factory()->create(['driver_id' => $driverA->id]);
    $incidentType = IncidentType::factory()->create();

    $this->actingAs($driverBUser);

    $response = post(route('service-incidents.store'), [
        'service_id' => $otherService->id,
        'incident_type_id' => $incidentType->id,
        'description' => 'Not my service',
        'affects_billing' => false,
    ]);

    $response->assertSessionHasErrors(['service_id']);
});

test('super admin bypasses the driver scope rule', function (): void {
    $service = Service::factory()->create();
    $incidentType = IncidentType::factory()->create();

    $response = post(route('service-incidents.store'), [
        'service_id' => $service->id,
        'incident_type_id' => $incidentType->id,
        'description' => 'Super admin audit',
        'affects_billing' => false,
    ]);

    $response->assertRedirect();
});

test('store dispatches BillingIncidentNotification when affects_billing is true', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $accounting = User::factory()->create();
    $accounting->assignRole('accounting');

    $service = Service::factory()->create();
    $incidentType = IncidentType::factory()->create();

    post(route('service-incidents.store'), [
        'service_id' => $service->id,
        'incident_type_id' => $incidentType->id,
        'description' => 'Billing impact',
        'affects_billing' => true,
        'additional_value' => 75000,
    ])->assertRedirect();

    Notification::assertSentTo($admin, BillingIncidentNotification::class);
    Notification::assertSentTo($accounting, BillingIncidentNotification::class);
});

test('store does not dispatch BillingIncidentNotification when affects_billing is false', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $service = Service::factory()->create();
    $incidentType = IncidentType::factory()->create();

    post(route('service-incidents.store'), [
        'service_id' => $service->id,
        'incident_type_id' => $incidentType->id,
        'description' => 'No impact',
        'affects_billing' => false,
    ])->assertRedirect();

    Notification::assertNothingSent();
});

test('accounting cannot update or delete incidents', function (): void {
    $accounting = User::factory()->create();
    $accounting->assignRole('accounting');
    $this->actingAs($accounting);

    $incident = ServiceIncident::factory()->create();

    put(route('service-incidents.update', $incident), [
        'incident_type_id' => $incident->incident_type_id,
        'description' => 'attempted edit',
        'affects_billing' => false,
    ])->assertForbidden();

    delete(route('service-incidents.destroy', $incident))->assertForbidden();
});

test('operator cannot update or delete incidents', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole('operator');
    $this->actingAs($operator);

    $incident = ServiceIncident::factory()->create();

    put(route('service-incidents.update', $incident), [
        'incident_type_id' => $incident->incident_type_id,
        'description' => 'attempted edit',
        'affects_billing' => false,
    ])->assertForbidden();

    delete(route('service-incidents.destroy', $incident))->assertForbidden();
});
