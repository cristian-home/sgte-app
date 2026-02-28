<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceIncident;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

use function Pest\Laravel\assertModelMissing;
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
    $serviceIncidents = ServiceIncident::factory()->count(3)->create();

    $response = get(route('service-incidents.index'));

    $response->assertOk();
});

test('create behaves as expected', function (): void {
    $response = get(route('service-incidents.create'));

    $response->assertOk();
});

test('store uses form request validation')
    ->assertActionUsesFormRequest(
        \App\Http\Controllers\ServiceIncidentController::class,
        'store',
        \App\Http\Requests\ServiceIncidentStoreRequest::class
    );

test('store saves and redirects', function (): void {
    $service = Service::factory()->create();
    $incident_type = fake()->randomElement(['delay', 'accident', 'breakdown', 'traffic', 'weather', 'customer_no_show', 'other']);
    $description = fake()->text();
    $registrar = User::factory()->create();
    $is_driver_report = fake()->boolean();
    $reported_at = Carbon::parse(fake()->dateTime());
    $affects_billing = fake()->boolean();
    $additional_value = fake()->randomFloat(2, 10000, 200000);

    $response = post(route('service-incidents.store'), [
        'service_id' => $service->id,
        'incident_type' => $incident_type,
        'description' => $description,
        'registrar_id' => $registrar->id,
        'is_driver_report' => $is_driver_report,
        'reported_at' => $reported_at,
        'affects_billing' => $affects_billing,
        'additional_value' => $additional_value,
    ]);

    $serviceIncidents = ServiceIncident::query()
        ->where('service_id', $service->id)
        ->where('incident_type', $incident_type)
        ->where('description', $description)
        ->where('registrar_id', $registrar->id)
        ->where('is_driver_report', $is_driver_report)
        ->where('reported_at', $reported_at)
        ->where('affects_billing', $affects_billing)
        ->where('additional_value', $additional_value)
        ->get();
    expect($serviceIncidents)->toHaveCount(1);
    $serviceIncident = $serviceIncidents->first();

    $response->assertRedirect(route('service-incidents.index'));
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

test('update redirects', function (): void {
    $serviceIncident = ServiceIncident::factory()->create();
    $service = Service::factory()->create();
    $incident_type = fake()->randomElement(['delay', 'accident', 'breakdown', 'traffic', 'weather', 'customer_no_show', 'other']);
    $description = fake()->text();
    $registrar = User::factory()->create();
    $is_driver_report = fake()->boolean();
    $reported_at = Carbon::parse(fake()->dateTime());
    $affects_billing = fake()->boolean();
    $additional_value = fake()->randomFloat(2, 10000, 200000);

    $response = put(route('service-incidents.update', $serviceIncident), [
        'service_id' => $service->id,
        'incident_type' => $incident_type,
        'description' => $description,
        'registrar_id' => $registrar->id,
        'is_driver_report' => $is_driver_report,
        'reported_at' => $reported_at,
        'affects_billing' => $affects_billing,
        'additional_value' => $additional_value,
    ]);

    $serviceIncident->refresh();

    $response->assertRedirect(route('service-incidents.index'));

    expect($service->id)->toEqual($serviceIncident->service_id);
    expect($incident_type)->toEqual($serviceIncident->incident_type);
    expect($description)->toEqual($serviceIncident->description);
    expect($registrar->id)->toEqual($serviceIncident->registrar_id);
    expect($is_driver_report)->toEqual($serviceIncident->is_driver_report);
    expect($reported_at->timestamp)->toEqual($serviceIncident->reported_at);
    expect($affects_billing)->toEqual($serviceIncident->affects_billing);
    expect($additional_value)->toEqual($serviceIncident->additional_value);
});

test('destroy deletes and redirects', function (): void {
    $serviceIncident = ServiceIncident::factory()->create();

    $response = delete(route('service-incidents.destroy', $serviceIncident));

    $response->assertRedirect(route('service-incidents.index'));

    assertModelMissing($serviceIncident);
});
