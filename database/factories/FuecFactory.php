<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class FuecFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'consecutive_number' => fake()->unique()->numberBetween(1, 99999),
            'generated_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'qr_code' => fake()->uuid(),
            'status' => fake()->randomElement(['active', 'cancelled']),
            'pdf_url' => fake()->optional()->url(),
        ];
    }
}
