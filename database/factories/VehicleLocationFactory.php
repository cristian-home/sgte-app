<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleLocation>
 */
class VehicleLocationFactory extends Factory
{
    protected $model = VehicleLocation::class;

    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'service_id' => null,
            'recorded_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'latitude' => fake()->randomFloat(8, 4.0, 7.5),
            'longitude' => fake()->randomFloat(8, -76.5, -73.5),
            'accuracy' => fake()->optional()->randomFloat(2, 3, 250),
            'is_manual' => fake()->boolean(20),
            'captured_by' => null,
        ];
    }

    public function manual(): self
    {
        return $this->state(fn () => ['is_manual' => true, 'accuracy' => null]);
    }

    public function gps(): self
    {
        return $this->state(fn () => [
            'is_manual' => false,
            'accuracy' => fake()->randomFloat(2, 3, 50),
        ]);
    }
}
