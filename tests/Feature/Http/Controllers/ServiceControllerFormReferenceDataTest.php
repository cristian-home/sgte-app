<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Municipality;
use App\Models\Service;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * The service create/edit screens bias the Google Places autocomplete
 * toward the selected municipality's lat/lng. The controller must
 * therefore project both columns into the Inertia response.
 */
beforeEach(function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);
});

test('services.create exposes municipality latitude and longitude', function (): void {
    Municipality::factory()->count(3)->create();

    get(route('services.create'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('services/create')
                ->has('municipalities', 3)
                ->has(
                    'municipalities.0',
                    fn (AssertableInertia $m) => $m
                        ->has('id')
                        ->has('name')
                        ->has('code')
                        ->has('department_id')
                        ->has('latitude')
                        ->has('longitude')
                        ->etc()
                )
        );
});

test('services.edit exposes municipality latitude and longitude', function (): void {
    Municipality::factory()->count(2)->create();
    $service = Service::factory()->create();

    get(route('services.edit', $service))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('services/edit')
                ->has('municipalities', 2)
                ->has(
                    'municipalities.0',
                    fn (AssertableInertia $m) => $m
                        ->has('latitude')
                        ->has('longitude')
                        ->etc()
                )
        );
});

test('municipality coordinates serialize as numeric strings', function (): void {
    Municipality::factory()->create(['latitude' => 4.6097100, 'longitude' => -74.0817500]);

    get(route('services.create'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where(
                    'municipalities.0.latitude',
                    fn ($value): bool => is_string($value) && is_numeric($value),
                )
                ->where(
                    'municipalities.0.longitude',
                    fn ($value): bool => is_string($value) && is_numeric($value),
                )
        );
});
