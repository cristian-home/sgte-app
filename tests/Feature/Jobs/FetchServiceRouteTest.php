<?php

use App\Jobs\FetchServiceRoute;
use App\Models\Service;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.mapbox.token', 'pk.test-token');
});

test('successful Mapbox response populates geometry, distance, duration', function (): void {
    Http::fake([
        'api.mapbox.com/*' => Http::response([
            'routes' => [[
                'distance' => 12500.4,
                'duration' => 1234.7,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [-75.5636, 6.2518],
                        [-75.5700, 6.2600],
                        [-75.5800, 6.2700],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '6.2700,-75.5800',
    ]);

    (new FetchServiceRoute($service))->handle(app(\App\Services\Mapbox\DirectionsClient::class));

    $service->refresh();

    expect($service->route_geometry)->toBeArray();
    expect($service->route_geometry[0])->toBe([-75.5636, 6.2518]);
    expect($service->route_distance_m)->toBe(12500);
    expect($service->route_duration_s)->toBe(1235);
    expect($service->route_source)->toBe('mapbox');
    expect($service->route_fetched_at)->not->toBeNull();
});

test('failed Mapbox response marks fetched_at but leaves geometry null', function (): void {
    Http::fake([
        'api.mapbox.com/*' => Http::response(['message' => 'No route'], 422),
    ]);

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '6.2700,-75.5800',
    ]);

    (new FetchServiceRoute($service))->handle(app(\App\Services\Mapbox\DirectionsClient::class));

    $service->refresh();

    expect($service->route_geometry)->toBeNull();
    expect($service->route_distance_m)->toBeNull();
    expect($service->route_duration_s)->toBeNull();
    expect($service->route_fetched_at)->not->toBeNull();
    expect($service->route_source)->toBe('mapbox');
});

test('job is a no-op when either coord is missing', function (): void {
    Http::fake();

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => null,
    ]);

    (new FetchServiceRoute($service))->handle(app(\App\Services\Mapbox\DirectionsClient::class));

    Http::assertNothingSent();

    $service->refresh();
    expect($service->route_fetched_at)->toBeNull();
});

test('Mapbox is called with lng,lat order even though coords are stored lat,lng', function (): void {
    Http::fake([
        'api.mapbox.com/*' => Http::response([
            'routes' => [['distance' => 0, 'duration' => 0, 'geometry' => ['coordinates' => [[0, 0], [1, 1]]]]],
        ], 200),
    ]);

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '4.6097,-74.0817',
    ]);

    (new FetchServiceRoute($service))->handle(app(\App\Services\Mapbox\DirectionsClient::class));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '-75.563600,6.251800;-74.081700,4.609700');
    });
});
