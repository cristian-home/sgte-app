<?php

namespace App\Jobs;

use App\Models\Service;
use App\Services\Mapbox\DirectionsClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches a driving route from Mapbox for the given service and caches
 * geometry + distance/duration on the row so /gps/map can render
 * polylines without hitting Mapbox on every page load.
 *
 * Dispatched from Service::booted() whenever either coordinate pair
 * changes. afterCommit so an outer DB transaction (e.g. import flow)
 * has flushed before we re-read the model.
 */
class FetchServiceRoute implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public Service $service) {}

    public function handle(DirectionsClient $client): void
    {
        $origin = $this->parseCoords($this->service->origin_coordinates);
        $dest = $this->parseCoords($this->service->destination_coordinates);

        if ($origin === null || $dest === null) {
            return;
        }

        // Coords are stored as 'lat,lng' — Mapbox API expects lng,lat.
        $route = $client->driving($origin[1], $origin[0], $dest[1], $dest[0]);

        if ($route === null) {
            // Mark as attempted so the backfill command + the saved
            // hook know not to keep re-queueing this row. Geometry
            // stays null → map falls back to a straight line.
            $this->service->update([
                'route_fetched_at' => now(),
                'route_source' => 'mapbox',
            ]);

            return;
        }

        $this->service->update([
            'route_geometry' => $route['geometry'],
            'route_distance_m' => $route['distance_m'],
            'route_duration_s' => $route['duration_s'],
            'route_fetched_at' => now(),
            'route_source' => 'mapbox',
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('FetchServiceRoute failed', [
            'service_id' => $this->service->id,
            'exception' => $e->getMessage(),
        ]);
    }

    /**
     * Parse a 'lat,lng' string into a [lat, lng] float pair, returning
     * null when the input is missing or malformed.
     *
     * @return array{0: float, 1: float}|null
     */
    private function parseCoords(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parts = explode(',', $value);
        if (count($parts) !== 2) {
            return null;
        }

        $lat = trim($parts[0]);
        $lng = trim($parts[1]);
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return [(float) $lat, (float) $lng];
    }
}
