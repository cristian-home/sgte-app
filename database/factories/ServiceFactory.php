<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\ServiceStatus;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Municipality;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Factories\Support\RealColombianAddresses;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $timezone = (string) config('app.operation_tz', 'America/Bogota');

        // Compose the planned start as a wall-clock day + time-of-day in the
        // service's own timezone, then convert to a UTC instant. This mirrors
        // how ServiceStoreRequest::prepareForValidation persists production
        // services.
        $day = fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d');
        $time = fake()->time('H:i');
        $plannedStart = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$day} {$time}", $timezone);
        $plannedStartUtc = $plannedStart->utc();

        $actualStart = fake()->boolean(40)
            ? $plannedStart->addMinutes(fake()->numberBetween(-15, 30))->utc()
            : null;
        $actualEnd = $actualStart && fake()->boolean(70)
            ? $actualStart->addMinutes(fake()->numberBetween(30, 480))
            : null;

        // Origin / destination: pick two random landmarks from the curated
        // list. Each one already brings (address, coords, source, accuracy)
        // matching what a real operator would persist via the address
        // autocomplete or the manual pin picker. ~10% of services land
        // without an origin and ~10% without a destination, mirroring
        // ad-hoc operations where the location is not known up front.
        $originSeed = fake()->boolean(90)
            ? RealColombianAddresses::random()
            : null;
        $destinationSeed = fake()->boolean(90)
            ? RealColombianAddresses::random()
            : null;

        return [
            'contract_id' => Contract::inRandomOrder()->first()->id ?? Contract::factory(),
            'vehicle_id' => Vehicle::inRandomOrder()->first()->id ?? Vehicle::factory(),
            'driver_id' => Driver::inRandomOrder()->first()->id ?? Driver::factory(),
            'invoice_id' => null,
            'service_date_local' => $day,
            'origin_municipality_id' => $originSeed
                ? self::resolveMunicipalityId($originSeed['municipality_code'])
                : (Municipality::inRandomOrder()->first()?->id ?? Municipality::factory()),
            'origin_address' => $originSeed['address'] ?? null,
            'origin_coordinates' => $originSeed['coordinates'] ?? null,
            'origin_coordinates_source' => $originSeed['source'] ?? null,
            'origin_coordinates_accuracy' => $originSeed['accuracy'] ?? null,
            'destination_municipality_id' => $destinationSeed
                ? self::resolveMunicipalityId($destinationSeed['municipality_code'])
                : (Municipality::inRandomOrder()->first()?->id ?? Municipality::factory()),
            'destination_address' => $destinationSeed['address'] ?? null,
            'destination_coordinates' => $destinationSeed['coordinates'] ?? null,
            'destination_coordinates_source' => $destinationSeed['source'] ?? null,
            'destination_coordinates_accuracy' => $destinationSeed['accuracy'] ?? null,
            'planned_start_at' => $plannedStartUtc,
            'planned_duration' => fake()->numberBetween(30, 480),
            'actual_start_at' => $actualStart,
            'actual_end_at' => $actualEnd,
            'timezone' => $timezone,
            'unit_value' => fake()->randomFloat(2, 50000, 500000),
            'quantity' => fake()->numberBetween(1, 5),
            'billing_group' => fake()->optional()->randomElement(['Grupo A', 'Grupo B', 'Grupo C']),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'service_status' => fake()->randomElement(ServiceStatus::cases()),
        ];
    }

    /**
     * Look up a municipality id by DANE code, falling back to a random
     * municipality (or factory-created) when the test environment hasn't
     * seeded the catalog. Cached statically across the factory run to
     * avoid hitting the DB once per generated row.
     */
    private static function resolveMunicipalityId(string $code): int
    {
        static $cache = [];
        if (isset($cache[$code])) {
            return $cache[$code];
        }
        $id = Municipality::where('code', $code)->value('id');
        if ($id === null) {
            $id = Municipality::inRandomOrder()->value('id')
                ?? Municipality::factory()->create()->id;
        }

        return $cache[$code] = (int) $id;
    }
}
