<?php

namespace App\Console\Commands;

use App\Services\Google\RoutesClient;
use App\Support\CuratedRoutes;
use Database\Seeders\ServiceSeeder;
use Illuminate\Console\Command;

/**
 * Regenerate `database/data/curated_routes.json`.
 *
 * Walks `ServiceSeeder::dataset()` + `landmarks()` to collect every
 * unique (origin, destination) coordinate pair the curated seed needs,
 * then calls the Google Routes API once per pair and writes the
 * geometry + distance + duration to disk.
 *
 * Run this only when the dataset changes (new landmarks, new
 * service rows). The committed JSON serves every subsequent
 * `migrate:fresh --seed` without any network calls.
 *
 * Requires the same `services.google_maps.server_key` config the
 * production RoutesClient uses. Without it, the API call returns
 * null and the entry is skipped (`--force` to overwrite existing).
 */
class DumpCuratedRoutes extends Command
{
    protected $signature = 'seed:dump-routes
                            {--force : Re-fetch and overwrite entries that already exist in the cache}';

    protected $description = 'Cache driving routes for every (origin, destination) pair used by the curated service seeder.';

    public function handle(RoutesClient $client): int
    {
        $landmarks = ServiceSeeder::landmarks();
        $dataset = ServiceSeeder::dataset();

        $pairs = [];
        foreach ($dataset as $row) {
            $origin = $landmarks[$row['origin']]['coordinates'] ?? null;
            $dest = $landmarks[$row['dest']]['coordinates'] ?? null;
            if (! $origin || ! $dest) {
                continue;
            }
            $key = CuratedRoutes::key($origin, $dest);
            $pairs[$key] = ['origin' => $origin, 'dest' => $dest];
        }

        $this->info('Found '.count($pairs).' unique (origin, destination) pair(s) in the dataset.');

        $existing = CuratedRoutes::all();
        $force = (bool) $this->option('force');

        $toFetch = [];
        foreach ($pairs as $key => $pair) {
            if (! $force && array_key_exists($key, $existing)) {
                continue;
            }
            $toFetch[$key] = $pair;
        }

        if (empty($toFetch)) {
            $this->info('All pairs already cached. Use --force to refresh.');

            return self::SUCCESS;
        }

        $this->info('Fetching '.count($toFetch).' route(s) from Google…');
        $bar = $this->output->createProgressBar(count($toFetch));
        $bar->start();

        $merged = $existing;
        $failures = [];

        foreach ($toFetch as $key => $pair) {
            [$oLat, $oLng] = $this->parseCoords($pair['origin']);
            [$dLat, $dLng] = $this->parseCoords($pair['dest']);

            // RoutesClient expects lng, lat.
            $route = $client->driving($oLng, $oLat, $dLng, $dLat);

            if ($route === null) {
                $failures[] = $key;
            } else {
                $merged[$key] = [
                    'geometry' => $route['geometry'],
                    'distance_m' => $route['distance_m'],
                    'duration_s' => $route['duration_s'],
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Sort keys deterministically so the file diff stays clean.
        ksort($merged);

        $path = CuratedRoutes::path();
        $json = json_encode(
            $merged,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        if ($json === false) {
            $this->error('Failed to encode curated routes to JSON.');

            return self::FAILURE;
        }

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $json.PHP_EOL);

        $this->info("Wrote {$path}");
        $this->info('Entries total: '.count($merged));

        if (! empty($failures)) {
            $this->warn('Failed to fetch '.count($failures).' pair(s):');
            foreach ($failures as $key) {
                $this->line('  · '.$key);
            }
            $this->warn('Check the Google API key (config services.google_maps.server_key) and re-run.');

            return self::FAILURE;
        }

        CuratedRoutes::flush();

        return self::SUCCESS;
    }

    /**
     * @return array{0: float, 1: float} [lat, lng]
     */
    private function parseCoords(string $coords): array
    {
        [$lat, $lng] = array_map('floatval', explode(',', $coords));

        return [$lat, $lng];
    }
}
