<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Static cache of driving routes for the (origin, destination)
 * coordinate pairs used by the curated seed dataset.
 *
 * Generation: the JSON file at `database/data/curated_routes.json` is
 * produced once by `php artisan seed:dump-routes`, which calls the
 * Google Routes API for every unique pair in `ServiceSeeder::dataset()`
 * and writes the result to disk. Run it again only when the dataset
 * grows or a landmark's coordinates change.
 *
 * Reuse: seeders look up `(originCoords, destinationCoords)` here at
 * `migrate:fresh --seed` time and inline the cached geometry +
 * distance + duration onto each Service row. No Google calls happen
 * during a normal seed run, and the data is offline-friendly + CI-
 * friendly.
 */
final class CuratedRoutes
{
    private const CACHE_PATH = 'database/data/curated_routes.json';

    /**
     * @var array<string, array{geometry: array<int, array{0: float, 1: float}>, distance_m: int, duration_s: int}>|null
     */
    private static ?array $cache = null;

    /**
     * Look up the cached route for an origin → destination pair.
     * Coordinates are the same `'lat,lng'` string format that Service
     * rows store. Returns null on cache miss; the caller should fall
     * back to a haversine estimate + straight-line chord so seed
     * doesn't break when a new pair hasn't been dumped yet.
     *
     * @return array{geometry: array<int, array{0: float, 1: float}>, distance_m: int, duration_s: int}|null
     */
    public static function forCoords(string $originCoords, string $destCoords): ?array
    {
        $key = self::key($originCoords, $destCoords);

        return self::load()[$key] ?? null;
    }

    /**
     * All cached entries. Used by the dump command to merge new pairs
     * into the existing file without losing previously cached ones.
     *
     * @return array<string, array{geometry: array<int, array{0: float, 1: float}>, distance_m: int, duration_s: int}>
     */
    public static function all(): array
    {
        return self::load();
    }

    /**
     * Stable lookup key for an origin → destination pair. Pure
     * string formatting so the file is git-diffable.
     */
    public static function key(string $originCoords, string $destCoords): string
    {
        return trim($originCoords).'>'.trim($destCoords);
    }

    /**
     * Absolute path to the JSON file on disk. Exposed so the dump
     * command can write to the same location the loader reads from.
     */
    public static function path(): string
    {
        return base_path(self::CACHE_PATH);
    }

    /**
     * Reset the in-process cache. Mostly for tests that rewrite the
     * JSON file mid-run.
     */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, array{geometry: array<int, array{0: float, 1: float}>, distance_m: int, duration_s: int}>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = self::path();
        if (! is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            Log::warning('CuratedRoutes failed to read cache file', ['path' => $path]);
            self::$cache = [];

            return self::$cache;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            Log::warning('CuratedRoutes cache file is not a JSON object', ['path' => $path]);
            self::$cache = [];

            return self::$cache;
        }

        /** @var array<string, array{geometry: array<int, array{0: float, 1: float}>, distance_m: int, duration_s: int}> $decoded */
        self::$cache = $decoded;

        return self::$cache;
    }
}
