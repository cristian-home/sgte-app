<?php

namespace App\Console\Commands;

use App\Jobs\FetchServiceRoute;
use App\Models\Service;
use Illuminate\Console\Command;

class BackfillServiceRoutes extends Command
{
    protected $signature = 'services:backfill-routes
                            {--force : Re-queue services even when a fetch was already attempted}';

    protected $description = 'Dispatch FetchServiceRoute for services that have both coords but no cached route yet.';

    public function handle(): int
    {
        $query = Service::query()
            ->whereNotNull('origin_coordinates')
            ->whereNotNull('destination_coordinates')
            ->where('origin_coordinates', '!=', '')
            ->where('destination_coordinates', '!=', '');

        if (! $this->option('force')) {
            $query->whereNull('route_fetched_at');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No services need a route backfill.');

            return self::SUCCESS;
        }

        $this->info("Dispatching FetchServiceRoute for {$total} service(s)…");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($services) use ($bar): void {
            foreach ($services as $service) {
                FetchServiceRoute::dispatch($service);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done. Jobs are queued — make sure a worker is running.');

        return self::SUCCESS;
    }
}
