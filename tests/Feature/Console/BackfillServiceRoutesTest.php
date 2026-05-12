<?php

use App\Jobs\FetchServiceRoute;
use App\Models\Service;
use Illuminate\Support\Facades\Bus;

test('dispatches a job for each service that has both coords and no fetch attempted', function (): void {
    Bus::fake();

    // 3 routable, never fetched
    Service::factory()->count(3)->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '4.6097,-74.0817',
    ]);

    // 1 already fetched — should be skipped without --force. The
    // saving hook nullifies route_fetched_at when origin/destination
    // is dirty, so we have to set it via the query builder after the
    // INSERT to keep the seed value.
    $fetched = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '4.6097,-74.0817',
    ]);
    Service::query()->whereKey($fetched->id)->update(['route_fetched_at' => now()]);

    // 1 missing destination — never dispatched on create.
    Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => null,
    ]);

    // Factory-time dispatches: 3 + 1 (both coords seeded) = 4.
    Bus::assertDispatchedTimes(FetchServiceRoute::class, 4);

    $this->artisan('services:backfill-routes')->assertSuccessful();

    // Backfill adds 3 (the 4th already had route_fetched_at set).
    Bus::assertDispatchedTimes(FetchServiceRoute::class, 7);
});

test('--force re-queues services that were already fetched', function (): void {
    Bus::fake();

    $services = Service::factory()->count(2)->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '4.6097,-74.0817',
    ]);
    Service::query()
        ->whereIn('id', $services->pluck('id'))
        ->update(['route_fetched_at' => now()]);

    Bus::assertDispatchedTimes(FetchServiceRoute::class, 2);

    $this->artisan('services:backfill-routes', ['--force' => true])->assertSuccessful();

    Bus::assertDispatchedTimes(FetchServiceRoute::class, 4);
});

test('command reports gracefully when nothing needs backfilling', function (): void {
    $this->artisan('services:backfill-routes')
        ->expectsOutputToContain('No services need a route backfill.')
        ->assertSuccessful();
});
