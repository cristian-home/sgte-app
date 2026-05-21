<?php

use App\Services\Google\RoutesClient;
use Illuminate\Support\Facades\Http;

test('driving decodes polyline geometry and parses distance and duration', function (): void {
    Http::fake([
        'routes.googleapis.com/*' => Http::response([
            'routes' => [[
                'distanceMeters' => 8421,
                'duration' => '843s',
                // Canonical Google encoded-polyline example: decodes to
                // (38.5,-120.2), (40.7,-120.95), (43.252,-126.453).
                'polyline' => ['encodedPolyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@'],
            ]],
        ], 200),
    ]);

    $result = (new RoutesClient('test-key'))->driving(-75.5636, 6.2518, -75.5800, 6.2700);

    expect($result)->toBeArray();
    expect($result['distance_m'])->toBe(8421);
    expect($result['duration_s'])->toBe(843);
    expect($result['geometry'])->toHaveCount(3);
    // Geometry is [lng, lat] (GeoJSON LineString order).
    expect($result['geometry'][0][0])->toEqualWithDelta(-120.2, 1e-6);
    expect($result['geometry'][0][1])->toEqualWithDelta(38.5, 1e-6);
    expect($result['geometry'][2][0])->toEqualWithDelta(-126.453, 1e-6);
    expect($result['geometry'][2][1])->toEqualWithDelta(43.252, 1e-6);
});

test('driving returns null when the server key is empty', function (): void {
    Http::fake();
    config()->set('services.google_maps.server_key', '');

    expect((new RoutesClient)->driving(-75.5636, 6.2518, -75.5800, 6.2700))->toBeNull();

    Http::assertNothingSent();
});

test('driving returns null on a non-2xx response', function (): void {
    Http::fake([
        'routes.googleapis.com/*' => Http::response(['error' => 'bad request'], 400),
    ]);

    expect((new RoutesClient('test-key'))->driving(-75.5636, 6.2518, -75.5800, 6.2700))->toBeNull();
});

test('driving returns null when the routes array is empty', function (): void {
    Http::fake([
        'routes.googleapis.com/*' => Http::response(['routes' => []], 200),
    ]);

    expect((new RoutesClient('test-key'))->driving(-75.5636, 6.2518, -75.5800, 6.2700))->toBeNull();
});

test('driving returns null when the routes key is missing', function (): void {
    Http::fake([
        'routes.googleapis.com/*' => Http::response([], 200),
    ]);

    expect((new RoutesClient('test-key'))->driving(-75.5636, 6.2518, -75.5800, 6.2700))->toBeNull();
});

test('driving sends the api key, field mask, and DRIVE travel mode', function (): void {
    Http::fake([
        'routes.googleapis.com/*' => Http::response([
            'routes' => [[
                'distanceMeters' => 100,
                'duration' => '60s',
                'polyline' => ['encodedPolyline' => '_p~iF~ps|U'],
            ]],
        ], 200),
    ]);

    (new RoutesClient('secret-key'))->driving(-75.5636, 6.2518, -75.5800, 6.2700);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://routes.googleapis.com/directions/v2:computeRoutes'
            && $request->hasHeader('X-Goog-Api-Key', 'secret-key')
            && $request->hasHeader('X-Goog-FieldMask', 'routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline')
            && $request['travelMode'] === 'DRIVE'
            && $request['polylineEncoding'] === 'ENCODED_POLYLINE'
            && $request['origin']['location']['latLng']['latitude'] === 6.2518
            && $request['origin']['location']['latLng']['longitude'] === -75.5636;
    });
});
