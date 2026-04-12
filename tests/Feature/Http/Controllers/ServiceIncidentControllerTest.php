<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\IncidentType;
use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\User;

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
            ->has('serviceIncidents', 3)
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
