<?php

use App\Jobs\FetchServiceRoute;
use App\Models\Service;
use Illuminate\Support\Facades\Bus;

test('changing origin_coordinates clears the cached route fields', function (): void {
    Bus::fake();

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '4.6097,-74.0817',
    ]);

    // Seed a cached route directly via the query builder — the model's
    // saving hook would otherwise wipe these on the first save (new
    // model: every fillable column is dirty).
    Service::query()->whereKey($service->id)->update([
        'route_geometry' => json_encode([[-75.5636, 6.2518], [-74.0817, 4.6097]]),
        'route_distance_m' => 12345,
        'route_duration_s' => 678,
        'route_fetched_at' => now(),
        'route_source' => 'google',
    ]);

    $service->refresh();
    expect($service->route_geometry)->not->toBeNull();

    $service->update(['origin_coordinates' => '6.3000,-75.6000']);

    $service->refresh();
    expect($service->route_geometry)->toBeNull();
    expect($service->route_distance_m)->toBeNull();
    expect($service->route_duration_s)->toBeNull();
    expect($service->route_fetched_at)->toBeNull();
    expect($service->route_source)->toBeNull();
});

test('coord change dispatches a FetchServiceRoute job when both coords are set', function (): void {
    Bus::fake();

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '4.6097,-74.0817',
    ]);

    Bus::assertDispatched(
        FetchServiceRoute::class,
        fn (FetchServiceRoute $job) => $job->service->is($service),
    );
});

test('creating a service with only one coord does not dispatch the job', function (): void {
    Bus::fake();

    Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => null,
    ]);

    Bus::assertNotDispatched(FetchServiceRoute::class);
});

test('updating an unrelated field does not dispatch the job', function (): void {
    Bus::fake();

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '4.6097,-74.0817',
    ]);

    Bus::assertDispatchedTimes(FetchServiceRoute::class, 1);

    // Changing the billing-group tags should not re-fetch the route;
    // the cache key is based on coordinates only.
    $service->update(['billing_groups' => ['Turismo']]);

    Bus::assertDispatchedTimes(FetchServiceRoute::class, 1);
});

test('clearing both coords clears the cache but does not dispatch', function (): void {
    Bus::fake();

    $service = Service::factory()->create([
        'origin_coordinates' => '6.2518,-75.5636',
        'destination_coordinates' => '4.6097,-74.0817',
    ]);

    Bus::assertDispatchedTimes(FetchServiceRoute::class, 1);

    $service->update([
        'origin_coordinates' => null,
        'destination_coordinates' => null,
    ]);

    Bus::assertDispatchedTimes(FetchServiceRoute::class, 1);

    $service->refresh();
    expect($service->route_geometry)->toBeNull();
});
