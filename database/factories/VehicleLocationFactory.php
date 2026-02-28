<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'recorded_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'latitude' => fake()->randomFloat(8, 4.0, 7.5),
            'longitude' => fake()->randomFloat(8, -76.5, -73.5),
            'is_manual' => fake()->boolean(20),
        ];
    }
}
