<?php

namespace App\Services\Mapbox;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over Mapbox Directions v5 /driving. Used by
 * App\Jobs\FetchServiceRoute to cache route geometry per service.
 * Returns null on any failure mode (missing token, HTTP error, no route
 * found) so callers can fall back to a straight-line draw client-side.
 */
class DirectionsClient
{
    public function __construct(protected ?string $token = null)
    {
        if ($this->token === null || $this->token === '') {
            $this->token = (string) config('services.mapbox.token');
        }
    }

    /**
     * Fetch a driving route between two coordinates.
     *
     * @return array{geometry: array<int, array{0: float, 1: float}>, distance_m: int, duration_s: int}|null
     */
    public function driving(float $originLng, float $originLat, float $destLng, float $destLat): ?array
    {
        if ($this->token === '') {
            return null;
        }

        $coords = sprintf('%F,%F;%F,%F', $originLng, $originLat, $destLng, $destLat);
        $url = "https://api.mapbox.com/directions/v5/mapbox/driving/{$coords}";

        try {
            $response = Http::timeout(10)
                ->retry(2, 250, throw: false)
                ->get($url, [
                    'access_token' => $this->token,
                    'geometries' => 'geojson',
                    'overview' => 'full',
                    'steps' => 'false',
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Mapbox Directions request failed', ['exception' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            Log::warning('Mapbox Directions returned non-2xx', [
                'status' => $response->status(),
                'body' => substr((string) $response->body(), 0, 500),
            ]);

            return null;
        }

        $route = $response->json('routes.0');
        if (! is_array($route) || ! isset($route['geometry']['coordinates']) || ! is_array($route['geometry']['coordinates'])) {
            return null;
        }

        return [
            'geometry' => $route['geometry']['coordinates'],
            'distance_m' => (int) round((float) ($route['distance'] ?? 0)),
            'duration_s' => (int) round((float) ($route['duration'] ?? 0)),
        ];
    }
}
