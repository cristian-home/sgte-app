<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\BillingGroup;
use App\Models\Contract;
use App\Models\Service;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
});

test('index returns the seeded billing groups', function (): void {
    $response = get(route('billing-groups.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('billing-groups/index')
            ->has('billingGroups', 5),
    );
});

test('store creates a new billing group', function (): void {
    $response = post(route('billing-groups.store'), [
        'code' => 'premium',
        'name' => 'Corporativo Premium',
        'active' => true,
        'description' => 'Cliente VIP.',
    ]);

    $response->assertRedirect();
    assertDatabaseHas('billing_groups', [
        'code' => 'premium',
        'name' => 'Corporativo Premium',
        'active' => true,
    ]);
});

test('store rejects duplicate code', function (): void {
    BillingGroup::factory()->create(['code' => 'duplicado']);

    $response = post(route('billing-groups.store'), [
        'code' => 'duplicado',
        'name' => 'Otro',
    ]);

    $response->assertSessionHasErrors('code');
});

test('store normalizes code to lowercase', function (): void {
    post(route('billing-groups.store'), [
        'code' => 'MIXED-Case',
        'name' => 'Mixed Case',
    ])->assertRedirect();

    assertDatabaseHas('billing_groups', ['code' => 'mixed-case']);
});

test('store rejects code with invalid characters', function (): void {
    $response = post(route('billing-groups.store'), [
        'code' => 'tiene espacios',
        'name' => 'X',
    ]);

    $response->assertSessionHasErrors('code');
});

test('update edits an existing billing group', function (): void {
    $group = BillingGroup::factory()->create([
        'code' => 'temp',
        'name' => 'Temporal',
        'active' => true,
    ]);

    put(route('billing-groups.update', $group), [
        'code' => 'temp',
        'name' => 'Renombrado',
        'active' => false,
    ])->assertRedirect();

    expect($group->fresh()->name)->toBe('Renombrado');
    expect($group->fresh()->active)->toBeFalse();
});

test('destroy soft-deletes an unused billing group', function (): void {
    $group = BillingGroup::factory()->create();

    delete(route('billing-groups.destroy', $group))
        ->assertRedirect(route('billing-groups.index'));

    expect($group->fresh()->trashed())->toBeTrue();
});

test('destroy blocks deletion when services are using the group', function (): void {
    $customer = \App\Models\ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $service = Service::factory()->create(['contract_id' => $contract->id]);

    $group = BillingGroup::firstWhere('code', 'salud');
    $service->billingGroups()->attach($group->id);

    $response = delete(route('billing-groups.destroy', $group));

    $response->assertSessionHasErrors('billing_group');
    expect($group->fresh()->trashed())->toBeFalse();
});

test('eligibility filter on ServiceStoreRequest rejects inactive groups', function (): void {
    $inactive = BillingGroup::factory()->create(['active' => false]);
    $customer = \App\Models\ThirdParty::factory()->create(['is_customer' => true]);
    $contract = Contract::factory()->create(['third_party_id' => $customer->id]);
    $vehicle = \App\Models\Vehicle::factory()->create();
    $driver = \App\Models\Driver::factory()->create();

    $response = post(route('services.store'), [
        'contract_id' => $contract->id,
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'service_date' => now()->toDateString(),
        'origin_address' => 'A',
        'origin_municipality_id' => \App\Models\Municipality::first()->id,
        'origin_coordinates' => '4.5816950,-74.1784720',
        'origin_coordinates_source' => 'google',
        'origin_coordinates_accuracy' => 'ROOFTOP',
        'destination_address' => 'B',
        'destination_municipality_id' => \App\Models\Municipality::first()->id,
        'destination_coordinates' => '4.6679000,-74.0541000',
        'destination_coordinates_source' => 'manual',
        'planned_start_time' => '08:00',
        'planned_duration' => 60,
        'unit_value' => 100000,
        'quantity' => 1,
        'billing_groups' => [$inactive->id],
        'payment_method' => 'credit',
        'service_status' => 'open',
    ]);

    $response->assertSessionHasErrors('billing_groups.0');
});
