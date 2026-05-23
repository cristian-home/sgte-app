<?php

use App\Jobs\FetchServiceRoute;
use App\Models\Service;
use App\Services\Google\RoutesClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.google_maps.server_key', 'test-server-key');
});

test('successful Routes API response populates geometry, distance, duration', function (): void {
    Http::fake([
        'routes.googleapis.com/*' => Http::response([
            'routes' => [[
                'distanceMeters' => 12500,
                'duration' => '1234s',
                // Canonical Google encoded-polyline example: decodes to
                // (38.5,-120.2), (40.7,-120.95), (43.252,-126.453).
                'polyline' => ['encodedPolyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@'],
            ]],
        ], 200),
    ]);

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '6.2700,-75.5800',
    ]);

    (new FetchServiceRoute($service))->handle(app(RoutesClient::class));

    $service->refresh();

    expect($service->route_geometry)->toBeArray();
    expect($service->route_geometry)->toHaveCount(3);
    // Geometry is stored [lng, lat] (GeoJSON LineString order).
    expect($service->route_geometry[0][0])->toEqualWithDelta(-120.2, 1e-6);
    expect($service->route_geometry[0][1])->toEqualWithDelta(38.5, 1e-6);
    expect($service->route_distance_m)->toBe(12500);
    expect($service->route_duration_s)->toBe(1234);
    expect($service->route_source)->toBe('google');
    expect($service->route_fetched_at)->not->toBeNull();
});

test('failed Routes API response marks fetched_at but leaves geometry null', function (): void {
    Http::fake([
        'routes.googleapis.com/*' => Http::response(['error' => 'No route'], 422),
    ]);

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '6.2700,-75.5800',
    ]);

    (new FetchServiceRoute($service))->handle(app(RoutesClient::class));

    $service->refresh();

    expect($service->route_geometry)->toBeNull();
    expect($service->route_distance_m)->toBeNull();
    expect($service->route_duration_s)->toBeNull();
    expect($service->route_fetched_at)->not->toBeNull();
    expect($service->route_source)->toBe('google');
});

test('job is a no-op when either coord is missing', function (): void {
    Http::fake();

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => null,
    ]);

    (new FetchServiceRoute($service))->handle(app(RoutesClient::class));

    Http::assertNothingSent();

    $service->refresh();
    expect($service->route_fetched_at)->toBeNull();
});

test('Routes API is called with lat/lng even though coords are stored lat,lng', function (): void {
    Http::fake([
        'routes.googleapis.com/*' => Http::response([
            'routes' => [[
                'distanceMeters' => 0,
                'duration' => '0s',
                'polyline' => ['encodedPolyline' => '_p~iF~ps|U'],
            ]],
        ], 200),
    ]);

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '4.6097,-74.0817',
    ]);

    (new FetchServiceRoute($service))->handle(app(RoutesClient::class));

    Http::assertSent(function ($request) {
        $origin = $request['origin']['location']['latLng'];
        $destination = $request['destination']['location']['latLng'];

        return $origin['latitude'] === 6.2518
            && $origin['longitude'] === -75.5636
            && $destination['latitude'] === 4.6097
            && $destination['longitude'] === -74.0817;
    });
});
