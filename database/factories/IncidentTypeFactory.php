<?php

namespace Database\Factories;

use App\Enums\IncidentSeverity;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncidentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('???'),
            'name' => fake()->unique()->words(3, true),
            'severity' => fake()->randomElement(IncidentSeverity::cases()),
            'affects_billing_default' => fake()->boolean(30),
            'description' => null,
        ];
    }
}
