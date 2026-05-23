<?php

namespace App\Services\Google;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over the Google Routes API (computeRoutes). Used by
 * App\Jobs\FetchServiceRoute to cache route geometry per service.
 * Returns null on any failure mode (missing key, HTTP error, no route
 * found) so callers can fall back to a straight-line draw client-side.
 */
class RoutesClient
{
    private const ENDPOINT = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    public function __construct(protected ?string $token = null)
    {
        if ($this->token === null || $this->token === '') {
            $this->token = (string) config('services.google_maps.server_key');
        }
    }

    /**
     * Fetch a driving route between two coordinates.
     *
     * Coordinates are passed in lng,lat order; the returned geometry is
     * a list of [lng, lat] pairs (GeoJSON LineString order).
     *
     * @return array{geometry: array<int, array{0: float, 1: float}>, distance_m: int, duration_s: int}|null
     */
    public function driving(float $originLng, float $originLat, float $destLng, float $destLat): ?array
    {
        if ($this->token === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->retry(2, 250, throw: false)
                ->withHeaders([
                    'X-Goog-Api-Key' => $this->token,
                    'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline',
                ])
                ->post(self::ENDPOINT, [
                    'origin' => ['location' => ['latLng' => ['latitude' => $originLat, 'longitude' => $originLng]]],
                    'destination' => ['location' => ['latLng' => ['latitude' => $destLat, 'longitude' => $destLng]]],
                    'travelMode' => 'DRIVE',
                    'polylineEncoding' => 'ENCODED_POLYLINE',
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Google Routes request failed', ['exception' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            Log::warning('Google Routes returned non-2xx', [
                'status' => $response->status(),
                'body' => substr((string) $response->body(), 0, 500),
            ]);

            return null;
        }

        $route = $response->json('routes.0');
        if (! is_array($route)) {
            return null;
        }

        $encoded = $route['polyline']['encodedPolyline'] ?? null;
        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        return [
            'geometry' => $this->decodePolyline($encoded),
            'distance_m' => (int) round((float) ($route['distanceMeters'] ?? 0)),
            'duration_s' => $this->parseDuration($route['duration'] ?? null),
        ];
    }

    /**
     * Parse a Routes API duration string (e.g. "843s") into integer
     * seconds. Returns 0 when the value is missing or non-numeric.
     */
    private function parseDuration(mixed $duration): int
    {
        if (! is_string($duration)) {
            return 0;
        }

        return (int) round((float) rtrim($duration, 's'));
    }

    /**
     * Decode a Google encoded polyline into a list of [lng, lat] float
     * pairs (GeoJSON LineString order) — the geometry shape consumed by
     * VehicleLocationMapController::geometryToLatLngs().
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function decodePolyline(string $encoded): array
    {
        $points = [];
        $index = 0;
        $length = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $length) {
            $lat += $this->decodeValue($encoded, $index, $length);
            $lng += $this->decodeValue($encoded, $index, $length);

            $points[] = [
                round($lng / 1e5, 7),
                round($lat / 1e5, 7),
            ];
        }

        return $points;
    }

    /**
     * Decode a single zig-zag-encoded varint from the polyline string,
     * advancing $index past the consumed characters.
     */
    private function decodeValue(string $encoded, int &$index, int $length): int
    {
        $shift = 0;
        $result = 0;
        $byte = 0;

        do {
            if ($index >= $length) {
                break;
            }
            $byte = ord($encoded[$index++]) - 63;
            $result |= ($byte & 0x1F) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) ? ~($result >> 1) : ($result >> 1);
    }
}
