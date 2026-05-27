<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use App\Services\Google\RoutesClient;
use App\Support\CuratedRoutes;
use Illuminate\Support\Facades\Cache;
use Mockery;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function (): void {
    Cache::flush();
    CuratedRoutes::flush();
});

afterEach(function (): void {
    Mockery::close();
});

test('eta returns curated cache hit without calling RoutesClient', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);

    // Spy on RoutesClient to ensure zero calls when the coords are in
    // the curated JSON. This pair lives in database/data/curated_routes.json
    // with duration_s = 861 → 14 minutes (round(861/60)).
    $mock = Mockery::mock(RoutesClient::class);
    $mock->shouldNotReceive('driving');
    $this->app->instance(RoutesClient::class, $mock);

    $response = getJson('/services-eta?origin_coordinates=4.5984000,-74.0763000&destination_coordinates=4.6291000,-74.0648000');

    $response->assertOk()
        ->assertJson([
            'eta_minutes' => 14,
            'source' => 'curated',
        ]);
});

test('eta calls RoutesClient on curated miss and returns live source', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);

    $mock = Mockery::mock(RoutesClient::class);
    $mock->shouldReceive('driving')
        ->once()
        ->andReturn([
            'geometry' => [[-75.5, 6.2], [-75.6, 6.3]],
            'distance_m' => 5000,
            'duration_s' => 1800,
        ]);
    $this->app->instance(RoutesClient::class, $mock);

    $response = getJson('/services-eta?origin_coordinates=1.111,2.222&destination_coordinates=3.333,4.444');

    $response->assertOk()
        ->assertJson([
            'eta_minutes' => 30,
            'source' => 'live',
        ]);
});

test('eta returns null minutes when RoutesClient fails', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);

    $mock = Mockery::mock(RoutesClient::class);
    $mock->shouldReceive('driving')->once()->andReturn(null);
    $this->app->instance(RoutesClient::class, $mock);

    $response = getJson('/services-eta?origin_coordinates=1.111,2.222&destination_coordinates=3.333,4.444');

    $response->assertOk()
        ->assertExactJson([
            'eta_minutes' => null,
            'source' => null,
        ]);
});

test('eta requires CREATE_SERVICES permission', function (): void {
    // Driver role does not include CREATE_SERVICES — should 403.
    $driver = User::factory()->create();
    $driver->assignRole('driver');
    actingAs($driver);

    $response = getJson('/services-eta?origin_coordinates=1.111,2.222&destination_coordinates=3.333,4.444');

    $response->assertForbidden();
});

test('eta validates coord format', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    actingAs($admin);

    $response = getJson('/services-eta?origin_coordinates=foo&destination_coordinates=3.333,4.444');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['origin_coordinates']);
});
